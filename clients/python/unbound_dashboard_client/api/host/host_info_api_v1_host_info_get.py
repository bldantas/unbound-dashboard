from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.host_info_api_v1_host_info_get_response_host_info_api_v1_host_info_get import (
    HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/host/info",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet | None:
    if response.status_code == 200:
        response_200 = HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet]:
    """Host Info

     Info estática do host. Cacheável aggressively no master — só muda
    se a máquina for renomeada ou OS for atualizado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet | None:
    """Host Info

     Info estática do host. Cacheável aggressively no master — só muda
    se a máquina for renomeada ou OS for atualizado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet]:
    """Host Info

     Info estática do host. Cacheável aggressively no master — só muda
    se a máquina for renomeada ou OS for atualizado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet | None:
    """Host Info

     Info estática do host. Cacheável aggressively no master — só muda
    se a máquina for renomeada ou OS for atualizado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HostInfoApiV1HostInfoGetResponseHostInfoApiV1HostInfoGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
