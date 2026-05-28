from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_summary_api_v1_analytics_summary_get_response_get_summary_api_v1_analytics_summary_get import (
    GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet,
)
from ...models.get_summary_api_v1_analytics_summary_get_window import GetSummaryApiV1AnalyticsSummaryGetWindow
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    window: GetSummaryApiV1AnalyticsSummaryGetWindow | Unset = GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_window: str | Unset = UNSET
    if not isinstance(window, Unset):
        json_window = window.value

    params["window"] = json_window

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/summary",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet.from_dict(
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
) -> Response[GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    window: GetSummaryApiV1AnalyticsSummaryGetWindow | Unset = GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1,
) -> Response[GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError]:
    """Get Summary

    Args:
        window (GetSummaryApiV1AnalyticsSummaryGetWindow | Unset):  Default:
            GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    window: GetSummaryApiV1AnalyticsSummaryGetWindow | Unset = GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1,
) -> GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError | None:
    """Get Summary

    Args:
        window (GetSummaryApiV1AnalyticsSummaryGetWindow | Unset):  Default:
            GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        window=window,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    window: GetSummaryApiV1AnalyticsSummaryGetWindow | Unset = GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1,
) -> Response[GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError]:
    """Get Summary

    Args:
        window (GetSummaryApiV1AnalyticsSummaryGetWindow | Unset):  Default:
            GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    window: GetSummaryApiV1AnalyticsSummaryGetWindow | Unset = GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1,
) -> GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError | None:
    """Get Summary

    Args:
        window (GetSummaryApiV1AnalyticsSummaryGetWindow | Unset):  Default:
            GetSummaryApiV1AnalyticsSummaryGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetSummaryApiV1AnalyticsSummaryGetResponseGetSummaryApiV1AnalyticsSummaryGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            window=window,
        )
    ).parsed
