<?php

namespace App;

class ShellHelper
{
    public static function buildCommand(string $binary, array $args = [], bool $useSudo = false, bool $captureStderr = true): string
    {
        $parts = [];
        if ($useSudo) {
            $parts[] = '/usr/bin/sudo';
        }

        $parts[] = escapeshellcmd($binary);

        foreach ($args as $arg) {
            $parts[] = escapeshellarg((string)$arg);
        }

        $command = implode(' ', $parts);
        if ($captureStderr) {
            $command .= ' 2>&1';
        }

        return $command;
    }

    public static function exec(string $binary, array $args = [], array &$output = null, int &$returnVar = null, bool $useSudo = true): string
    {
        $command = self::buildCommand($binary, $args, $useSudo, true);
        if ($output !== null) {
            $output = [];
        }
        exec($command, $output, $returnVar);
        return $command;
    }

    /** Helper root com allowlist de origem/destino (ver tools/system/bin/). */
    public const PRIV_HELPER = '/usr/local/bin/unbound-dashboard-priv.sh';

    /**
     * Instala um arquivo do tmp do dashboard (src/data/tmp) num destino
     * gerenciado (/etc/unbound/..., /etc/network/interfaces, /etc/hosts...).
     * O helper fixa dono e modo do destino — por isso não usamos `sudo cp`
     * nem `sudo mv` (o mv deixava o arquivo em /etc com dono www-data).
     */
    public static function installFile(string $src, string $dest, array &$output = null, int &$returnVar = null): string
    {
        return self::exec(self::PRIV_HELPER, ['install-file', $src, $dest], $output, $returnVar, true);
    }

    /** Como installFile(), mas remove a origem depois (semântica de mv). */
    public static function moveFile(string $src, string $dest, array &$output = null, int &$returnVar = null): string
    {
        $cmd = self::installFile($src, $dest, $output, $returnVar);
        if ($returnVar === 0) {
            @unlink($src);
        }
        return $cmd;
    }

    public static function shell(string $command, array &$output = null, int &$returnVar = null): string
    {
        $fullCommand = $command . ' 2>&1';
        exec($fullCommand, $output, $returnVar);
        return $fullCommand;
    }
}
