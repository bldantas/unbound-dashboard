from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.timeseries_api_v1_grafana_timeseries_get_metric import TimeseriesApiV1GrafanaTimeseriesGetMetric
from ...models.timeseries_api_v1_grafana_timeseries_get_response_200_item import (
    TimeseriesApiV1GrafanaTimeseriesGetResponse200Item,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    metric: TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset = TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL,
    hours: int | Unset = 24,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_metric: str | Unset = UNSET
    if not isinstance(metric, Unset):
        json_metric = metric.value

    params["metric"] = json_metric

    params["hours"] = hours

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/grafana/timeseries",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item] | None:
    if response.status_code == 200:
        response_200 = []
        _response_200 = response.json()
        for response_200_item_data in _response_200:
            response_200_item = TimeseriesApiV1GrafanaTimeseriesGetResponse200Item.from_dict(response_200_item_data)

            response_200.append(response_200_item)

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
) -> Response[HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item]]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    metric: TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset = TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL,
    hours: int | Unset = 24,
) -> Response[HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item]]:
    """Timeseries

     Pontos do hourly_stats no formato `[{time: ISO, value: int}]`.
    Pronto pra Grafana series visualization.

    Args:
        metric (TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset): total|blocked Default:
            TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL.
        hours (int | Unset): Janela em horas (1..720) Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item]]
    """

    kwargs = _get_kwargs(
        metric=metric,
        hours=hours,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    metric: TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset = TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL,
    hours: int | Unset = 24,
) -> HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item] | None:
    """Timeseries

     Pontos do hourly_stats no formato `[{time: ISO, value: int}]`.
    Pronto pra Grafana series visualization.

    Args:
        metric (TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset): total|blocked Default:
            TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL.
        hours (int | Unset): Janela em horas (1..720) Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item]
    """

    return sync_detailed(
        client=client,
        metric=metric,
        hours=hours,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    metric: TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset = TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL,
    hours: int | Unset = 24,
) -> Response[HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item]]:
    """Timeseries

     Pontos do hourly_stats no formato `[{time: ISO, value: int}]`.
    Pronto pra Grafana series visualization.

    Args:
        metric (TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset): total|blocked Default:
            TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL.
        hours (int | Unset): Janela em horas (1..720) Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item]]
    """

    kwargs = _get_kwargs(
        metric=metric,
        hours=hours,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    metric: TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset = TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL,
    hours: int | Unset = 24,
) -> HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item] | None:
    """Timeseries

     Pontos do hourly_stats no formato `[{time: ISO, value: int}]`.
    Pronto pra Grafana series visualization.

    Args:
        metric (TimeseriesApiV1GrafanaTimeseriesGetMetric | Unset): total|blocked Default:
            TimeseriesApiV1GrafanaTimeseriesGetMetric.TOTAL.
        hours (int | Unset): Janela em horas (1..720) Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | list[TimeseriesApiV1GrafanaTimeseriesGetResponse200Item]
    """

    return (
        await asyncio_detailed(
            client=client,
            metric=metric,
            hours=hours,
        )
    ).parsed
