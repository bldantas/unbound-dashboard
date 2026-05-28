from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_anomaly_settings_api_v1_analytics_anomaly_settings_get_response_get_anomaly_settings_api_v1_analytics_anomaly_settings_get import (
    GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/anomaly/settings",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet | None
):
    if response.status_code == 200:
        response_200 = GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet.from_dict(
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
    GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet
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
    GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet
]:
    """Get Anomaly Settings

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet]
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
    GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet | None
):
    """Get Anomaly Settings

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet
]:
    """Get Anomaly Settings

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet | None
):
    """Get Anomaly Settings

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetAnomalySettingsApiV1AnalyticsAnomalySettingsGetResponseGetAnomalySettingsApiV1AnalyticsAnomalySettingsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
