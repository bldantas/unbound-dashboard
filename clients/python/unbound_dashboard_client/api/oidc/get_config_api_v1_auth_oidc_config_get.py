from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_config_api_v1_auth_oidc_config_get_response_get_config_api_v1_auth_oidc_config_get import (
    GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/auth/oidc/config",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet | None:
    if response.status_code == 200:
        response_200 = GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet]:
    """Get Config

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet | None:
    """Get Config

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet]:
    """Get Config

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet | None:
    """Get Config

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetConfigApiV1AuthOidcConfigGetResponseGetConfigApiV1AuthOidcConfigGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
