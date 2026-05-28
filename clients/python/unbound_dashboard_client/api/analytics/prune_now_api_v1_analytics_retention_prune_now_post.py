from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.prune_now_api_v1_analytics_retention_prune_now_post_response_prune_now_api_v1_analytics_retention_prune_now_post import (
    PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/analytics/retention/prune-now",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost | None:
    if response.status_code == 200:
        response_200 = (
            PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost.from_dict(
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
) -> Response[PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost]:
    """Prune Now

     Dispara prune imediato (ignora schedule).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost | None:
    """Prune Now

     Dispara prune imediato (ignora schedule).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost]:
    """Prune Now

     Dispara prune imediato (ignora schedule).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost | None:
    """Prune Now

     Dispara prune imediato (ignora schedule).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        PruneNowApiV1AnalyticsRetentionPruneNowPostResponsePruneNowApiV1AnalyticsRetentionPruneNowPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
