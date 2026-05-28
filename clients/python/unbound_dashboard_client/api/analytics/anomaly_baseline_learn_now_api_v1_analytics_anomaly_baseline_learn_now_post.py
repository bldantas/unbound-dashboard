from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.anomaly_baseline_learn_now_api_v1_analytics_anomaly_baseline_learn_now_post_response_anomaly_baseline_learn_now_api_v1_analytics_anomaly_baseline_learn_now_post import (
    AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/analytics/anomaly/baseline/learn-now",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
    | None
):
    if response.status_code == 200:
        response_200 = AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost.from_dict(
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
    AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
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
    AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
]:
    """Anomaly Baseline Learn Now

     Força re-treino do BaselineLearner (1 ciclo). Idempotente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost]
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
    AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
    | None
):
    """Anomaly Baseline Learn Now

     Força re-treino do BaselineLearner (1 ciclo). Idempotente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
]:
    """Anomaly Baseline Learn Now

     Força re-treino do BaselineLearner (1 ciclo). Idempotente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
    | None
):
    """Anomaly Baseline Learn Now

     Força re-treino do BaselineLearner (1 ciclo). Idempotente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPostResponseAnomalyBaselineLearnNowApiV1AnalyticsAnomalyBaselineLearnNowPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
