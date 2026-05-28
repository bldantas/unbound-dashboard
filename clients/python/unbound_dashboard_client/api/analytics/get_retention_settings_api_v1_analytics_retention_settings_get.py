from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_retention_settings_api_v1_analytics_retention_settings_get_response_get_retention_settings_api_v1_analytics_retention_settings_get import (
    GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/retention/settings",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
    | None
):
    if response.status_code == 200:
        response_200 = GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet.from_dict(
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
    GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
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
    GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
]:
    """Get Retention Settings

     Settings + estado da última execução do pruner.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet]
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
    GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
    | None
):
    """Get Retention Settings

     Settings + estado da última execução do pruner.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
]:
    """Get Retention Settings

     Settings + estado da última execução do pruner.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
    | None
):
    """Get Retention Settings

     Settings + estado da última execução do pruner.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetRetentionSettingsApiV1AnalyticsRetentionSettingsGetResponseGetRetentionSettingsApiV1AnalyticsRetentionSettingsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
