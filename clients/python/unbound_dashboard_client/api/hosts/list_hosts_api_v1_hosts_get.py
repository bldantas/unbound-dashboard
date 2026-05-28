from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_hosts_api_v1_hosts_get_response_list_hosts_api_v1_hosts_get import (
    ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/hosts",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet | None:
    if response.status_code == 200:
        response_200 = ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet]:
    """List Hosts

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet | None:
    """List Hosts

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet]:
    """List Hosts

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet | None:
    """List Hosts

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListHostsApiV1HostsGetResponseListHostsApiV1HostsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
