from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_action_breakdown_api_v1_analytics_action_breakdown_get_response_get_action_breakdown_api_v1_analytics_action_breakdown_get import (
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet,
)
from ...models.get_action_breakdown_api_v1_analytics_action_breakdown_get_window import (
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    window: GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow
    | Unset = GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_window: str | Unset = UNSET
    if not isinstance(window, Unset):
        json_window = window.value

    params["window"] = json_window

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/action-breakdown",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet.from_dict(
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
) -> Response[
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet
    | HTTPValidationError
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
    window: GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow
    | Unset = GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1,
) -> Response[
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet
    | HTTPValidationError
]:
    """Get Action Breakdown

    Args:
        window (GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow | Unset):  Default:
            GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet | HTTPValidationError]
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
    window: GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow
    | Unset = GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1,
) -> (
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet
    | HTTPValidationError
    | None
):
    """Get Action Breakdown

    Args:
        window (GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow | Unset):  Default:
            GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        window=window,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    window: GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow
    | Unset = GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1,
) -> Response[
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet
    | HTTPValidationError
]:
    """Get Action Breakdown

    Args:
        window (GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow | Unset):  Default:
            GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    window: GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow
    | Unset = GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1,
) -> (
    GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet
    | HTTPValidationError
    | None
):
    """Get Action Breakdown

    Args:
        window (GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow | Unset):  Default:
            GetActionBreakdownApiV1AnalyticsActionBreakdownGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetActionBreakdownApiV1AnalyticsActionBreakdownGetResponseGetActionBreakdownApiV1AnalyticsActionBreakdownGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            window=window,
        )
    ).parsed
