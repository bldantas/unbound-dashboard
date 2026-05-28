from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.feed_api_v1_notifications_feed_get_response_feed_api_v1_notifications_feed_get import (
    FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    limit: int | Unset = 30,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["limit"] = limit

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/notifications/feed",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet.from_dict(response.json())

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
) -> Response[FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    limit: int | Unset = 30,
) -> Response[FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError]:
    """Feed

     Bell payload — só ativos + não-dismissed, mais recente primeiro.

    Args:
        limit (int | Unset):  Default: 30.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError]
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
    limit: int | Unset = 30,
) -> FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError | None:
    """Feed

     Bell payload — só ativos + não-dismissed, mais recente primeiro.

    Args:
        limit (int | Unset):  Default: 30.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        limit=limit,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    limit: int | Unset = 30,
) -> Response[FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError]:
    """Feed

     Bell payload — só ativos + não-dismissed, mais recente primeiro.

    Args:
        limit (int | Unset):  Default: 30.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        limit=limit,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    limit: int | Unset = 30,
) -> FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError | None:
    """Feed

     Bell payload — só ativos + não-dismissed, mais recente primeiro.

    Args:
        limit (int | Unset):  Default: 30.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        FeedApiV1NotificationsFeedGetResponseFeedApiV1NotificationsFeedGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            limit=limit,
        )
    ).parsed
