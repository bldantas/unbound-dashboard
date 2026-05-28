from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.check_peer_api_v1_ha_peers_peer_id_check_post_response_check_peer_api_v1_ha_peers_peer_id_check_post import (
    CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    peer_id: int,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/ha/peers/{peer_id}/check".format(
            peer_id=quote(str(peer_id), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost.from_dict(
            response.json()
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
) -> Response[CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    peer_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError]:
    """Check Peer

    Args:
        peer_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        peer_id=peer_id,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    peer_id: int,
    *,
    client: AuthenticatedClient,
) -> CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError | None:
    """Check Peer

    Args:
        peer_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError
    """

    return sync_detailed(
        peer_id=peer_id,
        client=client,
    ).parsed


async def asyncio_detailed(
    peer_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError]:
    """Check Peer

    Args:
        peer_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        peer_id=peer_id,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    peer_id: int,
    *,
    client: AuthenticatedClient,
) -> CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError | None:
    """Check Peer

    Args:
        peer_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CheckPeerApiV1HaPeersPeerIdCheckPostResponseCheckPeerApiV1HaPeersPeerIdCheckPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            peer_id=peer_id,
            client=client,
        )
    ).parsed
