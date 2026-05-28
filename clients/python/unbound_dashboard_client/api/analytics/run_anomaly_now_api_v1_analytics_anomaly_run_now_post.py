from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.run_anomaly_now_api_v1_analytics_anomaly_run_now_post_response_run_anomaly_now_api_v1_analytics_anomaly_run_now_post import (
    RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/analytics/anomaly/run-now",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost | None:
    if response.status_code == 200:
        response_200 = (
            RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost.from_dict(
                response.json()
            )
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost]:
    """Run Anomaly Now

     Roda todos os checks uma vez (independente de anomaly_enabled). Útil pra teste.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost | None:
    """Run Anomaly Now

     Roda todos os checks uma vez (independente de anomaly_enabled). Útil pra teste.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost]:
    """Run Anomaly Now

     Roda todos os checks uma vez (independente de anomaly_enabled). Útil pra teste.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost | None:
    """Run Anomaly Now

     Roda todos os checks uma vez (independente de anomaly_enabled). Útil pra teste.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        RunAnomalyNowApiV1AnalyticsAnomalyRunNowPostResponseRunAnomalyNowApiV1AnalyticsAnomalyRunNowPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
