from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_top_clients_api_v1_analytics_top_clients_get_response_get_top_clients_api_v1_analytics_top_clients_get import (
    GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet,
)
from ...models.get_top_clients_api_v1_analytics_top_clients_get_window import (
    GetTopClientsApiV1AnalyticsTopClientsGetWindow,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    window: GetTopClientsApiV1AnalyticsTopClientsGetWindow
    | Unset = GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1,
    limit: int | Unset = 20,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_window: str | Unset = UNSET
    if not isinstance(window, Unset):
        json_window = window.value

    params["window"] = json_window

    params["limit"] = limit

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/top-clients",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = (
            GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet.from_dict(
                response.json()
            )
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
) -> Response[
    GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet | HTTPValidationError
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
    window: GetTopClientsApiV1AnalyticsTopClientsGetWindow
    | Unset = GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1,
    limit: int | Unset = 20,
) -> Response[
    GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet | HTTPValidationError
]:
    """Get Top Clients

    Args:
        window (GetTopClientsApiV1AnalyticsTopClientsGetWindow | Unset):  Default:
            GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
        limit=limit,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    window: GetTopClientsApiV1AnalyticsTopClientsGetWindow
    | Unset = GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1,
    limit: int | Unset = 20,
) -> (
    GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet
    | HTTPValidationError
    | None
):
    """Get Top Clients

    Args:
        window (GetTopClientsApiV1AnalyticsTopClientsGetWindow | Unset):  Default:
            GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        window=window,
        limit=limit,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    window: GetTopClientsApiV1AnalyticsTopClientsGetWindow
    | Unset = GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1,
    limit: int | Unset = 20,
) -> Response[
    GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet | HTTPValidationError
]:
    """Get Top Clients

    Args:
        window (GetTopClientsApiV1AnalyticsTopClientsGetWindow | Unset):  Default:
            GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
        limit=limit,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    window: GetTopClientsApiV1AnalyticsTopClientsGetWindow
    | Unset = GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1,
    limit: int | Unset = 20,
) -> (
    GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet
    | HTTPValidationError
    | None
):
    """Get Top Clients

    Args:
        window (GetTopClientsApiV1AnalyticsTopClientsGetWindow | Unset):  Default:
            GetTopClientsApiV1AnalyticsTopClientsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetTopClientsApiV1AnalyticsTopClientsGetResponseGetTopClientsApiV1AnalyticsTopClientsGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            window=window,
            limit=limit,
        )
    ).parsed
