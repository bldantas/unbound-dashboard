from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.login_api_v1_auth_login_post_response_login_api_v1_auth_login_post import (
    LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost,
)
from ...models.login_request import LoginRequest
from ...types import Response


def _get_kwargs(
    *,
    body: LoginRequest,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/auth/login",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost | None:
    if response.status_code == 200:
        response_200 = LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost.from_dict(response.json())

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
) -> Response[HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient | Client,
    body: LoginRequest,
) -> Response[HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost]:
    """Login

     Retorna TokenResponse normal OU `{requires_totp: true, challenge_token}`
    se o user tem 2FA habilitado. Frontend (login.php) precisa detectar
    `requires_totp` e redirecionar pro fluxo de 2FA.

    Args:
        body (LoginRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost]
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
    body: LoginRequest,
) -> HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost | None:
    """Login

     Retorna TokenResponse normal OU `{requires_totp: true, challenge_token}`
    se o user tem 2FA habilitado. Frontend (login.php) precisa detectar
    `requires_totp` e redirecionar pro fluxo de 2FA.

    Args:
        body (LoginRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient | Client,
    body: LoginRequest,
) -> Response[HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost]:
    """Login

     Retorna TokenResponse normal OU `{requires_totp: true, challenge_token}`
    se o user tem 2FA habilitado. Frontend (login.php) precisa detectar
    `requires_totp` e redirecionar pro fluxo de 2FA.

    Args:
        body (LoginRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient | Client,
    body: LoginRequest,
) -> HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost | None:
    """Login

     Retorna TokenResponse normal OU `{requires_totp: true, challenge_token}`
    se o user tem 2FA habilitado. Frontend (login.php) precisa detectar
    `requires_totp` e redirecionar pro fluxo de 2FA.

    Args:
        body (LoginRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LoginApiV1AuthLoginPostResponseLoginApiV1AuthLoginPost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
