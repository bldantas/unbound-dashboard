from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.history_summary_api_v1_history_summary_get_response_history_summary_api_v1_history_summary_get import (
    HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    limit: str | Unset = "10",
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["limit"] = limit

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/history/summary",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet | None:
    if response.status_code == 200:
        response_200 = HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet.from_dict(
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
) -> Response[HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
) -> Response[HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet]:
    """History Summary

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet]
    """

    kwargs = _get_kwargs(
        limit=limit,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
) -> HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet | None:
    """History Summary

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet
    """

    return sync_detailed(
        client=client,
        limit=limit,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
) -> Response[HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet]:
    """History Summary

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet]
    """

    kwargs = _get_kwargs(
        limit=limit,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
) -> HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet | None:
    """History Summary

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | HistorySummaryApiV1HistorySummaryGetResponseHistorySummaryApiV1HistorySummaryGet
    """

    return (
        await asyncio_detailed(
            client=client,
            limit=limit,
        )
    ).parsed
