from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.anomaly_resolve_all_api_v1_analytics_anomaly_resolve_all_post_response_anomaly_resolve_all_api_v1_analytics_anomaly_resolve_all_post import (
    AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/analytics/anomaly/resolve-all",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
    | None
):
    if response.status_code == 200:
        response_200 = AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost.from_dict(
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
    AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
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
    AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
]:
    """Anomaly Resolve All

     Marca todas as detecções anomaly_* ativas como resolved_at=NOW().

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost]
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
    AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
    | None
):
    """Anomaly Resolve All

     Marca todas as detecções anomaly_* ativas como resolved_at=NOW().

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
]:
    """Anomaly Resolve All

     Marca todas as detecções anomaly_* ativas como resolved_at=NOW().

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
    | None
):
    """Anomaly Resolve All

     Marca todas as detecções anomaly_* ativas como resolved_at=NOW().

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPostResponseAnomalyResolveAllApiV1AnalyticsAnomalyResolveAllPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
