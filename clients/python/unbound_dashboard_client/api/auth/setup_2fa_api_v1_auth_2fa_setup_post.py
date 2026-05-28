from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.setup_2_fa_api_v1_auth_2_fa_setup_post_response_setup_2_fa_api_v1_auth_2_fa_setup_post import (
    Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/auth/2fa/setup",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost | None:
    if response.status_code == 200:
        response_200 = Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost]:
    """Setup 2Fa

     Gera secret novo + URI provisionamento. NÃO persiste — user precisa
    confirmar com code via /2fa/confirm pra ativar de fato.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost | None:
    """Setup 2Fa

     Gera secret novo + URI provisionamento. NÃO persiste — user precisa
    confirmar com code via /2fa/confirm pra ativar de fato.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost]:
    """Setup 2Fa

     Gera secret novo + URI provisionamento. NÃO persiste — user precisa
    confirmar com code via /2fa/confirm pra ativar de fato.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost | None:
    """Setup 2Fa

     Gera secret novo + URI provisionamento. NÃO persiste — user precisa
    confirmar com code via /2fa/confirm pra ativar de fato.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Setup2FaApiV1Auth2FaSetupPostResponseSetup2FaApiV1Auth2FaSetupPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
