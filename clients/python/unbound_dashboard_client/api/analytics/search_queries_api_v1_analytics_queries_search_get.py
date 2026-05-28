from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.search_queries_api_v1_analytics_queries_search_get_response_search_queries_api_v1_analytics_queries_search_get import (
    SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet,
)
from ...models.search_queries_api_v1_analytics_queries_search_get_window import (
    SearchQueriesApiV1AnalyticsQueriesSearchGetWindow,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    window: SearchQueriesApiV1AnalyticsQueriesSearchGetWindow
    | Unset = SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1,
    client_ip: str | Unset = "",
    domain: str | Unset = "",
    query_type: str | Unset = "",
    action: str | Unset = "",
    country: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_window: str | Unset = UNSET
    if not isinstance(window, Unset):
        json_window = window.value

    params["window"] = json_window

    params["client_ip"] = client_ip

    params["domain"] = domain

    params["query_type"] = query_type

    params["action"] = action

    params["country"] = country

    params["page"] = page

    params["per_page"] = per_page

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/queries/search",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
    | None
):
    if response.status_code == 200:
        response_200 = (
            SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet.from_dict(
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
    HTTPValidationError | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
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
    window: SearchQueriesApiV1AnalyticsQueriesSearchGetWindow
    | Unset = SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1,
    client_ip: str | Unset = "",
    domain: str | Unset = "",
    query_type: str | Unset = "",
    action: str | Unset = "",
    country: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> Response[
    HTTPValidationError | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
]:
    """Search Queries

    Args:
        window (SearchQueriesApiV1AnalyticsQueriesSearchGetWindow | Unset):  Default:
            SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1.
        client_ip (str | Unset):  Default: ''.
        domain (str | Unset):  Default: ''.
        query_type (str | Unset):  Default: ''.
        action (str | Unset):  Default: ''.
        country (str | Unset):  Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet]
    """

    kwargs = _get_kwargs(
        window=window,
        client_ip=client_ip,
        domain=domain,
        query_type=query_type,
        action=action,
        country=country,
        page=page,
        per_page=per_page,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    window: SearchQueriesApiV1AnalyticsQueriesSearchGetWindow
    | Unset = SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1,
    client_ip: str | Unset = "",
    domain: str | Unset = "",
    query_type: str | Unset = "",
    action: str | Unset = "",
    country: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> (
    HTTPValidationError
    | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
    | None
):
    """Search Queries

    Args:
        window (SearchQueriesApiV1AnalyticsQueriesSearchGetWindow | Unset):  Default:
            SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1.
        client_ip (str | Unset):  Default: ''.
        domain (str | Unset):  Default: ''.
        query_type (str | Unset):  Default: ''.
        action (str | Unset):  Default: ''.
        country (str | Unset):  Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
    """

    return sync_detailed(
        client=client,
        window=window,
        client_ip=client_ip,
        domain=domain,
        query_type=query_type,
        action=action,
        country=country,
        page=page,
        per_page=per_page,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    window: SearchQueriesApiV1AnalyticsQueriesSearchGetWindow
    | Unset = SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1,
    client_ip: str | Unset = "",
    domain: str | Unset = "",
    query_type: str | Unset = "",
    action: str | Unset = "",
    country: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> Response[
    HTTPValidationError | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
]:
    """Search Queries

    Args:
        window (SearchQueriesApiV1AnalyticsQueriesSearchGetWindow | Unset):  Default:
            SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1.
        client_ip (str | Unset):  Default: ''.
        domain (str | Unset):  Default: ''.
        query_type (str | Unset):  Default: ''.
        action (str | Unset):  Default: ''.
        country (str | Unset):  Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet]
    """

    kwargs = _get_kwargs(
        window=window,
        client_ip=client_ip,
        domain=domain,
        query_type=query_type,
        action=action,
        country=country,
        page=page,
        per_page=per_page,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    window: SearchQueriesApiV1AnalyticsQueriesSearchGetWindow
    | Unset = SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1,
    client_ip: str | Unset = "",
    domain: str | Unset = "",
    query_type: str | Unset = "",
    action: str | Unset = "",
    country: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> (
    HTTPValidationError
    | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
    | None
):
    """Search Queries

    Args:
        window (SearchQueriesApiV1AnalyticsQueriesSearchGetWindow | Unset):  Default:
            SearchQueriesApiV1AnalyticsQueriesSearchGetWindow.VALUE_1.
        client_ip (str | Unset):  Default: ''.
        domain (str | Unset):  Default: ''.
        query_type (str | Unset):  Default: ''.
        action (str | Unset):  Default: ''.
        country (str | Unset):  Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SearchQueriesApiV1AnalyticsQueriesSearchGetResponseSearchQueriesApiV1AnalyticsQueriesSearchGet
    """

    return (
        await asyncio_detailed(
            client=client,
            window=window,
            client_ip=client_ip,
            domain=domain,
            query_type=query_type,
            action=action,
            country=country,
            page=page,
            per_page=per_page,
        )
    ).parsed
