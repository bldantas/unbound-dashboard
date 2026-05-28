from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.refresh_all_api_v1_geo_blocking_refresh_all_post_response_refresh_all_api_v1_geo_blocking_refresh_all_post import (
    RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    only_blocked: bool | Unset = True,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["only_blocked"] = only_blocked

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/geo-blocking/refresh-all",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
    | None
):
    if response.status_code == 200:
        response_200 = (
            RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost.from_dict(
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
    HTTPValidationError | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
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
    only_blocked: bool | Unset = True,
) -> Response[
    HTTPValidationError | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
]:
    """Refresh All

    Args:
        only_blocked (bool | Unset):  Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost]
    """

    kwargs = _get_kwargs(
        only_blocked=only_blocked,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    only_blocked: bool | Unset = True,
) -> (
    HTTPValidationError
    | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
    | None
):
    """Refresh All

    Args:
        only_blocked (bool | Unset):  Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
    """

    return sync_detailed(
        client=client,
        only_blocked=only_blocked,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    only_blocked: bool | Unset = True,
) -> Response[
    HTTPValidationError | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
]:
    """Refresh All

    Args:
        only_blocked (bool | Unset):  Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost]
    """

    kwargs = _get_kwargs(
        only_blocked=only_blocked,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    only_blocked: bool | Unset = True,
) -> (
    HTTPValidationError
    | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
    | None
):
    """Refresh All

    Args:
        only_blocked (bool | Unset):  Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RefreshAllApiV1GeoBlockingRefreshAllPostResponseRefreshAllApiV1GeoBlockingRefreshAllPost
    """

    return (
        await asyncio_detailed(
            client=client,
            only_blocked=only_blocked,
        )
    ).parsed
