from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_hourly_stats_api_v1_analytics_hourly_get_response_get_hourly_stats_api_v1_analytics_hourly_get import (
    GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    hours: int | Unset = 24,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["hours"] = hours

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/hourly",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet.from_dict(
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
) -> Response[GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
) -> Response[GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError]:
    """Get Hourly Stats

     Últimas N horas de hourly_stats. Usado em /observability.

    Args:
        hours (int | Unset):  Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        hours=hours,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
) -> GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError | None:
    """Get Hourly Stats

     Últimas N horas de hourly_stats. Usado em /observability.

    Args:
        hours (int | Unset):  Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        hours=hours,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
) -> Response[GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError]:
    """Get Hourly Stats

     Últimas N horas de hourly_stats. Usado em /observability.

    Args:
        hours (int | Unset):  Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        hours=hours,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
) -> GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError | None:
    """Get Hourly Stats

     Últimas N horas de hourly_stats. Usado em /observability.

    Args:
        hours (int | Unset):  Default: 24.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetHourlyStatsApiV1AnalyticsHourlyGetResponseGetHourlyStatsApiV1AnalyticsHourlyGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            hours=hours,
        )
    ).parsed
