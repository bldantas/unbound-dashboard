from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_time_series_api_v1_observability_time_series_get_response_get_time_series_api_v1_observability_time_series_get import (
    GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/observability/time-series",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet | None:
    if response.status_code == 200:
        response_200 = (
            GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet.from_dict(
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
) -> Response[GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet]:
    """Get Time Series

     Série temporal de até 60 samples (1h, 1/min) escrita pelo UnboundCollector.
    Inclui latência média/mediana, QPS, hits/miss, secure/bogus.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet | None:
    """Get Time Series

     Série temporal de até 60 samples (1h, 1/min) escrita pelo UnboundCollector.
    Inclui latência média/mediana, QPS, hits/miss, secure/bogus.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet]:
    """Get Time Series

     Série temporal de até 60 samples (1h, 1/min) escrita pelo UnboundCollector.
    Inclui latência média/mediana, QPS, hits/miss, secure/bogus.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet | None:
    """Get Time Series

     Série temporal de até 60 samples (1h, 1/min) escrita pelo UnboundCollector.
    Inclui latência média/mediana, QPS, hits/miss, secure/bogus.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetTimeSeriesApiV1ObservabilityTimeSeriesGetResponseGetTimeSeriesApiV1ObservabilityTimeSeriesGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
