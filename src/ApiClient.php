<?php
/**
 * ApiClient — wrapper minimal pra chamar a FastAPI v1 (api_service/) em 127.0.0.1:8001.
 *
 * Usado durante a transição Strangler Fig (ver docs/PLANO_MODERNIZACAO_V1.md):
 * o login PHP autentica contra MariaDB E TAMBÉM chama POST /api/v1/auth/login
 * pra obter um JWT — guardado em $_SESSION['api_jwt']. O JWT é injetado nas
 * páginas via <meta name="api-jwt"> e usado pelas chamadas fetch() do JS pra
 * endpoints novos da FastAPI (ex: /api/v1/threats/data).
 *
 * Falha graciosa: se FastAPI estiver fora ou retornar erro, o login PHP
 * continua válido (frontend cai na URL legada api/*.php até recuperar).
 */

namespace App;

class ApiClient
{
    /**
     * URL base da FastAPI. Hardcoded localhost porque o api_service roda
     * sempre em 127.0.0.1:8001 (Apache faz proxy de /api/v1/* pra cá).
     */
    private const BASE_URL = 'http://127.0.0.1:8001';

    /** Timeout curto — login não pode atrasar a tela. */
    private const TIMEOUT_SECONDS = 3;

    /**
     * Chama POST /api/v1/auth/login. Retorna ['ok' => true, 'token' => ...]
     * em sucesso ou ['ok' => false, 'reason' => ...] em qualquer falha.
     * Nunca lança — chamador decide se ignora ou propaga.
     */
    public static function login(string $username, string $password): array
    {
        $payload = json_encode(['username' => $username, 'password' => $password]);
        if ($payload === false) {
            return ['ok' => false, 'reason' => 'json_encode_failed'];
        }

        $ch = curl_init(self::BASE_URL . '/api/v1/auth/login');
        if ($ch === false) {
            return ['ok' => false, 'reason' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'reason' => 'curl_error: ' . $err];
        }

        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http !== 200) {
            return ['ok' => false, 'reason' => 'http_' . $http];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['access_token'])) {
            return ['ok' => false, 'reason' => 'invalid_response'];
        }

        return [
            'ok'    => true,
            'token' => (string) $data['access_token'],
            'role'  => (string) ($data['role'] ?? ''),
        ];
    }

    /**
     * PUT /api/v1/auth/me/password autenticado com Bearer JWT.
     * Sincroniza o hash da senha no DuckDB depois que o PHP atualizou MariaDB.
     * Falha graciosa: retorna ['ok' => false, 'reason' => ...] sem lançar.
     */
    public static function changePassword(string $jwt, string $oldPass, string $newPass): array
    {
        $payload = json_encode(['old_password' => $oldPass, 'new_password' => $newPass]);
        if ($payload === false) {
            return ['ok' => false, 'reason' => 'json_encode_failed'];
        }

        $ch = curl_init(self::BASE_URL . '/api/v1/auth/me/password');
        if ($ch === false) {
            return ['ok' => false, 'reason' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $jwt,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'reason' => 'curl_error: ' . $err];
        }

        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 204) {
            return ['ok' => true];
        }
        return ['ok' => false, 'reason' => 'http_' . $http];
    }

    /**
     * GET autenticado com Bearer JWT. Retorna ['ok' => true, 'data' => array]
     * em sucesso (HTTP 200 + JSON válido) ou ['ok' => false, 'reason' => str].
     */
    public static function get(string $path, string $jwt): array
    {
        $ch = curl_init(self::BASE_URL . $path);
        if ($ch === false) {
            return ['ok' => false, 'reason' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $jwt,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'reason' => 'curl_error: ' . $err];
        }

        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http !== 200) {
            return ['ok' => false, 'reason' => 'http_' . $http];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['ok' => false, 'reason' => 'invalid_json'];
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * POST autenticado com Bearer JWT, payload opcional. Aceita HTTP 2xx.
     */
    public static function post(string $path, string $jwt, array $payload = []): array
    {
        $body = json_encode($payload);
        if ($body === false) {
            return ['ok' => false, 'reason' => 'json_encode_failed'];
        }
        $ch = curl_init(self::BASE_URL . $path);
        if ($ch === false) {
            return ['ok' => false, 'reason' => 'curl_init_failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $jwt,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'reason' => 'curl_error: ' . $err];
        }
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http >= 200 && $http < 300) {
            return ['ok' => true, 'data' => json_decode($response, true) ?: null];
        }
        return ['ok' => false, 'reason' => 'http_' . $http];
    }

    /**
     * PUT autenticado com Bearer JWT, payload opcional. Aceita HTTP 2xx.
     */
    public static function put(string $path, string $jwt, array $payload = []): array
    {
        return self::sendCustom('PUT', $path, $jwt, $payload);
    }

    /**
     * DELETE autenticado com Bearer JWT.
     */
    public static function delete(string $path, string $jwt): array
    {
        return self::sendCustom('DELETE', $path, $jwt, null);
    }

    /**
     * Helper interno pra qualquer método HTTP custom com Bearer auth.
     */
    private static function sendCustom(string $method, string $path, string $jwt, ?array $payload): array
    {
        $ch = curl_init(self::BASE_URL . $path);
        if ($ch === false) {
            return ['ok' => false, 'reason' => 'curl_init_failed'];
        }
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $jwt,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
        ];
        if ($payload !== null) {
            $body = json_encode($payload);
            if ($body === false) {
                curl_close($ch);
                return ['ok' => false, 'reason' => 'json_encode_failed'];
            }
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'reason' => 'curl_error: ' . $err];
        }
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http >= 200 && $http < 300) {
            $decoded = $response !== '' ? json_decode($response, true) : null;
            return ['ok' => true, 'data' => is_array($decoded) ? $decoded : null, 'http' => $http];
        }
        $detail = $response !== '' ? json_decode($response, true) : null;
        return [
            'ok'     => false,
            'reason' => 'http_' . $http,
            'http'   => $http,
            'detail' => is_array($detail) ? ($detail['detail'] ?? null) : null,
        ];
    }
}
