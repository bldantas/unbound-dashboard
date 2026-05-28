from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.update_peer_api_v1_ha_peers_peer_id_put_body import UpdatePeerApiV1HaPeersPeerIdPutBody
from ...models.update_peer_api_v1_ha_peers_peer_id_put_response_update_peer_api_v1_ha_peers_peer_id_put import (
    UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut,
)
from ...types import Response


def _get_kwargs(
    peer_id: int,
    *,
    body: UpdatePeerApiV1HaPeersPeerIdPutBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "put",
        "url": "/api/v1/ha/peers/{peer_id}".format(
            peer_id=quote(str(peer_id), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut | None:
    if response.status_code == 200:
        response_200 = UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut.from_dict(response.json())

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
) -> Response[HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut]:
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
    body: UpdatePeerApiV1HaPeersPeerIdPutBody,
) -> Response[HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut]:
    """Update Peer

    Args:
        peer_id (int):
        body (UpdatePeerApiV1HaPeersPeerIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut]
    """

    kwargs = _get_kwargs(
        peer_id=peer_id,
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    peer_id: int,
    *,
    client: AuthenticatedClient,
    body: UpdatePeerApiV1HaPeersPeerIdPutBody,
) -> HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut | None:
    """Update Peer

    Args:
        peer_id (int):
        body (UpdatePeerApiV1HaPeersPeerIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut
    """

    return sync_detailed(
        peer_id=peer_id,
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    peer_id: int,
    *,
    client: AuthenticatedClient,
    body: UpdatePeerApiV1HaPeersPeerIdPutBody,
) -> Response[HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut]:
    """Update Peer

    Args:
        peer_id (int):
        body (UpdatePeerApiV1HaPeersPeerIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut]
    """

    kwargs = _get_kwargs(
        peer_id=peer_id,
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    peer_id: int,
    *,
    client: AuthenticatedClient,
    body: UpdatePeerApiV1HaPeersPeerIdPutBody,
) -> HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut | None:
    """Update Peer

    Args:
        peer_id (int):
        body (UpdatePeerApiV1HaPeersPeerIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdatePeerApiV1HaPeersPeerIdPutResponseUpdatePeerApiV1HaPeersPeerIdPut
    """

    return (
        await asyncio_detailed(
            peer_id=peer_id,
            client=client,
            body=body,
        )
    ).parsed
