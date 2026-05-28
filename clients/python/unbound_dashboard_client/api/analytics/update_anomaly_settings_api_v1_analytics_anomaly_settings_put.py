from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.update_anomaly_settings_api_v1_analytics_anomaly_settings_put_body import (
    UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody,
)
from ...models.update_anomaly_settings_api_v1_analytics_anomaly_settings_put_response_update_anomaly_settings_api_v1_analytics_anomaly_settings_put import (
    UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut,
)
from ...types import Response


def _get_kwargs(
    *,
    body: UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "put",
        "url": "/api/v1/analytics/anomaly/settings",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
    | None
):
    if response.status_code == 200:
        response_200 = UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut.from_dict(
            response.json()
        )

        return response_200

    if response.status_code == 422:
        response_422 = HTTPValidationError.from_dict(response.json())

        return response_422

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[
    HTTPValidationError
    | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
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
    body: UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody,
) -> Response[
    HTTPValidationError
    | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
]:
    """Update Anomaly Settings

    Args:
        body (UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    body: UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody,
) -> (
    HTTPValidationError
    | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
    | None
):
    """Update Anomaly Settings

    Args:
        body (UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody,
) -> Response[
    HTTPValidationError
    | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
]:
    """Update Anomaly Settings

    Args:
        body (UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody,
) -> (
    HTTPValidationError
    | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
    | None
):
    """Update Anomaly Settings

    Args:
        body (UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPutResponseUpdateAnomalySettingsApiV1AnalyticsAnomalySettingsPut
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
