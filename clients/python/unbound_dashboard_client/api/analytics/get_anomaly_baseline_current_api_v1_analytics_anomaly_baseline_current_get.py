from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_anomaly_baseline_current_api_v1_analytics_anomaly_baseline_current_get_response_get_anomaly_baseline_current_api_v1_analytics_anomaly_baseline_current_get import (
    GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/anomaly/baseline/current",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
    | None
):
    if response.status_code == 200:
        response_200 = GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet.from_dict(
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
    GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
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
    GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
]:
    r"""Get Anomaly Baseline Current

     Última hora completa vs baseline do mesmo bucket. Útil pra mostrar
    \"onde estamos\" no heatmap em tempo real.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet]
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
    GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
    | None
):
    r"""Get Anomaly Baseline Current

     Última hora completa vs baseline do mesmo bucket. Útil pra mostrar
    \"onde estamos\" no heatmap em tempo real.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
]:
    r"""Get Anomaly Baseline Current

     Última hora completa vs baseline do mesmo bucket. Útil pra mostrar
    \"onde estamos\" no heatmap em tempo real.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
    | None
):
    r"""Get Anomaly Baseline Current

     Última hora completa vs baseline do mesmo bucket. Útil pra mostrar
    \"onde estamos\" no heatmap em tempo real.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGetResponseGetAnomalyBaselineCurrentApiV1AnalyticsAnomalyBaselineCurrentGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
