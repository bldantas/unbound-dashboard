from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_my_prefs_api_v1_notifications_prefs_get_response_get_my_prefs_api_v1_notifications_prefs_get import (
    GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/notifications/prefs",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet | None:
    if response.status_code == 200:
        response_200 = GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet]:
    """Get My Prefs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet | None:
    """Get My Prefs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet]:
    """Get My Prefs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet | None:
    """Get My Prefs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetMyPrefsApiV1NotificationsPrefsGetResponseGetMyPrefsApiV1NotificationsPrefsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
