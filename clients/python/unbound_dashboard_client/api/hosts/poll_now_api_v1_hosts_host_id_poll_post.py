from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.poll_now_api_v1_hosts_host_id_poll_post_response_poll_now_api_v1_hosts_host_id_poll_post import (
    PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost,
)
from ...types import Response


def _get_kwargs(
    host_id: int,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/hosts/{host_id}/poll".format(
            host_id=quote(str(host_id), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost | None:
    if response.status_code == 200:
        response_200 = PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost.from_dict(response.json())

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
) -> Response[HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    host_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost]:
    """Poll Now

     Força poll imediato do host. Retorna resultado.

    Args:
        host_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost]
    """

    kwargs = _get_kwargs(
        host_id=host_id,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    host_id: int,
    *,
    client: AuthenticatedClient,
) -> HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost | None:
    """Poll Now

     Força poll imediato do host. Retorna resultado.

    Args:
        host_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost
    """

    return sync_detailed(
        host_id=host_id,
        client=client,
    ).parsed


async def asyncio_detailed(
    host_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost]:
    """Poll Now

     Força poll imediato do host. Retorna resultado.

    Args:
        host_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost]
    """

    kwargs = _get_kwargs(
        host_id=host_id,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    host_id: int,
    *,
    client: AuthenticatedClient,
) -> HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost | None:
    """Poll Now

     Força poll imediato do host. Retorna resultado.

    Args:
        host_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | PollNowApiV1HostsHostIdPollPostResponsePollNowApiV1HostsHostIdPollPost
    """

    return (
        await asyncio_detailed(
            host_id=host_id,
            client=client,
        )
    ).parsed
