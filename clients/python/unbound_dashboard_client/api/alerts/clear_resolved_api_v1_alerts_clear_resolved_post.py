from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.clear_resolved_api_v1_alerts_clear_resolved_post_response_clear_resolved_api_v1_alerts_clear_resolved_post import (
    ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/alerts/clear-resolved",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost | None:
    if response.status_code == 200:
        response_200 = (
            ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost.from_dict(
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
) -> Response[ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost]:
    """Clear Resolved

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost | None:
    """Clear Resolved

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost]:
    """Clear Resolved

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost | None:
    """Clear Resolved

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ClearResolvedApiV1AlertsClearResolvedPostResponseClearResolvedApiV1AlertsClearResolvedPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
