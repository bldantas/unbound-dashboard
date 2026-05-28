from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_backups_api_v1_updates_backups_get_response_list_backups_api_v1_updates_backups_get import (
    ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/updates/backups",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet | None:
    if response.status_code == 200:
        response_200 = ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet]:
    """List Backups

     Lista os últimos backups disponíveis pra restore manual.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet | None:
    """List Backups

     Lista os últimos backups disponíveis pra restore manual.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet]:
    """List Backups

     Lista os últimos backups disponíveis pra restore manual.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet | None:
    """List Backups

     Lista os últimos backups disponíveis pra restore manual.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListBackupsApiV1UpdatesBackupsGetResponseListBackupsApiV1UpdatesBackupsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
