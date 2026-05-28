from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_anomaly_whitelist_api_v1_analytics_anomaly_whitelist_get_response_list_anomaly_whitelist_api_v1_analytics_anomaly_whitelist_get import (
    ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/anomaly/whitelist",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
    | None
):
    if response.status_code == 200:
        response_200 = ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet.from_dict(
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
    ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
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
    ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
]:
    """List Anomaly Whitelist

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet]
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
    ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
    | None
):
    """List Anomaly Whitelist

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
]:
    """List Anomaly Whitelist

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
    | None
):
    """List Anomaly Whitelist

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGetResponseListAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
