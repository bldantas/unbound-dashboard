from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.dismiss_all_api_v1_notifications_dismiss_all_post_response_dismiss_all_api_v1_notifications_dismiss_all_post import (
    DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/notifications/dismiss-all",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost | None:
    if response.status_code == 200:
        response_200 = (
            DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost.from_dict(
                response.json()
            )
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost]:
    """Dismiss All

     Mark-all-as-read. Usado pelo botão 'Marcar todas como lidas' do bell.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost | None:
    """Dismiss All

     Mark-all-as-read. Usado pelo botão 'Marcar todas como lidas' do bell.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost]:
    """Dismiss All

     Mark-all-as-read. Usado pelo botão 'Marcar todas como lidas' do bell.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost | None:
    """Dismiss All

     Mark-all-as-read. Usado pelo botão 'Marcar todas como lidas' do bell.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DismissAllApiV1NotificationsDismissAllPostResponseDismissAllApiV1NotificationsDismissAllPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
