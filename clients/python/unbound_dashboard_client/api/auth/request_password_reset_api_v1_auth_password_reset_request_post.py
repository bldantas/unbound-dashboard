from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.password_reset_request import PasswordResetRequest
from ...models.request_password_reset_api_v1_auth_password_reset_request_post_response_request_password_reset_api_v1_auth_password_reset_request_post import (
    RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost,
)
from ...types import Response


def _get_kwargs(
    *,
    body: PasswordResetRequest,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/auth/password-reset/request",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
    | None
):
    if response.status_code == 200:
        response_200 = RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost.from_dict(
            response.json()
        )

        return response_200

    if response.status_code == 422:
        response_422 = HTTPValidationError.from_dict(response.json())

        return response_422

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[
    HTTPValidationError
    | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient | Client,
    body: PasswordResetRequest,
) -> Response[
    HTTPValidationError
    | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
]:
    """Request Password Reset

     Gera token de reset se email pertence a user ativo. Retorna o token (cru)
    pra o caller (PHP) enviar por email — Python NÃO envia email diretamente.
    Resposta sempre 200 (timing-safe; não revela se email existe).

    Args:
        body (PasswordResetRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient | Client,
    body: PasswordResetRequest,
) -> (
    HTTPValidationError
    | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
    | None
):
    """Request Password Reset

     Gera token de reset se email pertence a user ativo. Retorna o token (cru)
    pra o caller (PHP) enviar por email — Python NÃO envia email diretamente.
    Resposta sempre 200 (timing-safe; não revela se email existe).

    Args:
        body (PasswordResetRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient | Client,
    body: PasswordResetRequest,
) -> Response[
    HTTPValidationError
    | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
]:
    """Request Password Reset

     Gera token de reset se email pertence a user ativo. Retorna o token (cru)
    pra o caller (PHP) enviar por email — Python NÃO envia email diretamente.
    Resposta sempre 200 (timing-safe; não revela se email existe).

    Args:
        body (PasswordResetRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient | Client,
    body: PasswordResetRequest,
) -> (
    HTTPValidationError
    | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
    | None
):
    """Request Password Reset

     Gera token de reset se email pertence a user ativo. Retorna o token (cru)
    pra o caller (PHP) enviar por email — Python NÃO envia email diretamente.
    Resposta sempre 200 (timing-safe; não revela se email existe).

    Args:
        body (PasswordResetRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RequestPasswordResetApiV1AuthPasswordResetRequestPostResponseRequestPasswordResetApiV1AuthPasswordResetRequestPost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
