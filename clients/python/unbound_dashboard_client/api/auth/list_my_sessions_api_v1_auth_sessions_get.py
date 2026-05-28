from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_my_sessions_api_v1_auth_sessions_get_response_list_my_sessions_api_v1_auth_sessions_get import (
    ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/auth/sessions",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet | None:
    if response.status_code == 200:
        response_200 = ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet]:
    """List My Sessions

     Lista sessões ativas (Redis tracking) do user autenticado.
    Admin pode passar `?all=1` pra listar todas as sessões do sistema.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet | None:
    """List My Sessions

     Lista sessões ativas (Redis tracking) do user autenticado.
    Admin pode passar `?all=1` pra listar todas as sessões do sistema.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet]:
    """List My Sessions

     Lista sessões ativas (Redis tracking) do user autenticado.
    Admin pode passar `?all=1` pra listar todas as sessões do sistema.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet | None:
    """List My Sessions

     Lista sessões ativas (Redis tracking) do user autenticado.
    Admin pode passar `?all=1` pra listar todas as sessões do sistema.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListMySessionsApiV1AuthSessionsGetResponseListMySessionsApiV1AuthSessionsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
