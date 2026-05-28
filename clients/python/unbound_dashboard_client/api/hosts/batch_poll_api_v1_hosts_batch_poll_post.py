from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.batch_poll_api_v1_hosts_batch_poll_post_response_batch_poll_api_v1_hosts_batch_poll_post import (
    BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/hosts/batch/poll",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost | None:
    if response.status_code == 200:
        response_200 = BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost]:
    """Batch Poll

     Força poll imediato em todos os hosts. Atualiza banco.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost | None:
    """Batch Poll

     Força poll imediato em todos os hosts. Atualiza banco.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost]:
    """Batch Poll

     Força poll imediato em todos os hosts. Atualiza banco.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost | None:
    """Batch Poll

     Força poll imediato em todos os hosts. Atualiza banco.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BatchPollApiV1HostsBatchPollPostResponseBatchPollApiV1HostsBatchPollPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
