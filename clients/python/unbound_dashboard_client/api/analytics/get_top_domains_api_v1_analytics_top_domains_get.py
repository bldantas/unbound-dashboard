from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_top_domains_api_v1_analytics_top_domains_get_action_type_0 import (
    GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0,
)
from ...models.get_top_domains_api_v1_analytics_top_domains_get_response_get_top_domains_api_v1_analytics_top_domains_get import (
    GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet,
)
from ...models.get_top_domains_api_v1_analytics_top_domains_get_window import (
    GetTopDomainsApiV1AnalyticsTopDomainsGetWindow,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    window: GetTopDomainsApiV1AnalyticsTopDomainsGetWindow
    | Unset = GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1,
    limit: int | Unset = 20,
    action: GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset = UNSET,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_window: str | Unset = UNSET
    if not isinstance(window, Unset):
        json_window = window.value

    params["window"] = json_window

    params["limit"] = limit

    json_action: None | str | Unset
    if isinstance(action, Unset):
        json_action = UNSET
    elif isinstance(action, GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0):
        json_action = action.value
    else:
        json_action = action
    params["action"] = json_action

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/top-domains",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = (
            GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet.from_dict(
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
    GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet | HTTPValidationError
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
    window: GetTopDomainsApiV1AnalyticsTopDomainsGetWindow
    | Unset = GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1,
    limit: int | Unset = 20,
    action: GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset = UNSET,
) -> Response[
    GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet | HTTPValidationError
]:
    """Get Top Domains

    Args:
        window (GetTopDomainsApiV1AnalyticsTopDomainsGetWindow | Unset):  Default:
            GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.
        action (GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
        limit=limit,
        action=action,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    window: GetTopDomainsApiV1AnalyticsTopDomainsGetWindow
    | Unset = GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1,
    limit: int | Unset = 20,
    action: GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset = UNSET,
) -> (
    GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet
    | HTTPValidationError
    | None
):
    """Get Top Domains

    Args:
        window (GetTopDomainsApiV1AnalyticsTopDomainsGetWindow | Unset):  Default:
            GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.
        action (GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        window=window,
        limit=limit,
        action=action,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    window: GetTopDomainsApiV1AnalyticsTopDomainsGetWindow
    | Unset = GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1,
    limit: int | Unset = 20,
    action: GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset = UNSET,
) -> Response[
    GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet | HTTPValidationError
]:
    """Get Top Domains

    Args:
        window (GetTopDomainsApiV1AnalyticsTopDomainsGetWindow | Unset):  Default:
            GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.
        action (GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
        limit=limit,
        action=action,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    window: GetTopDomainsApiV1AnalyticsTopDomainsGetWindow
    | Unset = GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1,
    limit: int | Unset = 20,
    action: GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset = UNSET,
) -> (
    GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet
    | HTTPValidationError
    | None
):
    """Get Top Domains

    Args:
        window (GetTopDomainsApiV1AnalyticsTopDomainsGetWindow | Unset):  Default:
            GetTopDomainsApiV1AnalyticsTopDomainsGetWindow.VALUE_1.
        limit (int | Unset):  Default: 20.
        action (GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0 | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetTopDomainsApiV1AnalyticsTopDomainsGetResponseGetTopDomainsApiV1AnalyticsTopDomainsGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            window=window,
            limit=limit,
            action=action,
        )
    ).parsed
