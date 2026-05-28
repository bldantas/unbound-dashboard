from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_retention_api_v1_notifications_retention_settings_get_response_get_retention_api_v1_notifications_retention_settings_get import (
    GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/notifications/retention/settings",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet | None
):
    if response.status_code == 200:
        response_200 = GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[
    GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet
]:
    """Get Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> (
    GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet | None
):
    """Get Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet
]:
    """Get Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet | None
):
    """Get Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetRetentionApiV1NotificationsRetentionSettingsGetResponseGetRetentionApiV1NotificationsRetentionSettingsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
