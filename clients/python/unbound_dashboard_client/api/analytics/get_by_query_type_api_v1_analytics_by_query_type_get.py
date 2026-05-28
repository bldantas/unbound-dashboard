from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_by_query_type_api_v1_analytics_by_query_type_get_response_get_by_query_type_api_v1_analytics_by_query_type_get import (
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet,
)
from ...models.get_by_query_type_api_v1_analytics_by_query_type_get_window import (
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    window: GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow
    | Unset = GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_window: str | Unset = UNSET
    if not isinstance(window, Unset):
        json_window = window.value

    params["window"] = json_window

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/analytics/by-query-type",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = (
            GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet.from_dict(
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
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet | HTTPValidationError
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
    window: GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow
    | Unset = GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1,
) -> Response[
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet | HTTPValidationError
]:
    """Get By Query Type

    Args:
        window (GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow | Unset):  Default:
            GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet | HTTPValidationError]
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
    window: GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow
    | Unset = GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1,
) -> (
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet
    | HTTPValidationError
    | None
):
    """Get By Query Type

    Args:
        window (GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow | Unset):  Default:
            GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        window=window,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    window: GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow
    | Unset = GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1,
) -> Response[
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet | HTTPValidationError
]:
    """Get By Query Type

    Args:
        window (GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow | Unset):  Default:
            GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        window=window,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    window: GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow
    | Unset = GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1,
) -> (
    GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet
    | HTTPValidationError
    | None
):
    """Get By Query Type

    Args:
        window (GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow | Unset):  Default:
            GetByQueryTypeApiV1AnalyticsByQueryTypeGetWindow.VALUE_1.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetByQueryTypeApiV1AnalyticsByQueryTypeGetResponseGetByQueryTypeApiV1AnalyticsByQueryTypeGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            window=window,
        )
    ).parsed
