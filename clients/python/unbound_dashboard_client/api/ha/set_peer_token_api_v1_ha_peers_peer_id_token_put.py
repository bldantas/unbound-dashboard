from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.set_peer_token_api_v1_ha_peers_peer_id_token_put_body import SetPeerTokenApiV1HaPeersPeerIdTokenPutBody
from ...models.set_peer_token_api_v1_ha_peers_peer_id_token_put_response_set_peer_token_api_v1_ha_peers_peer_id_token_put import (
    SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut,
)
from ...types import Response


def _get_kwargs(
    peer_id: int,
    *,
    body: SetPeerTokenApiV1HaPeersPeerIdTokenPutBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "put",
        "url": "/api/v1/ha/peers/{peer_id}/token".format(
            peer_id=quote(str(peer_id), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut | None:
    if response.status_code == 200:
        response_200 = SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut.from_dict(
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
) -> Response[
    HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut
]:
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
    body: SetPeerTokenApiV1HaPeersPeerIdTokenPutBody,
) -> Response[
    HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut
]:
    r"""Set Peer Token

     Substitui o token de um peer (usado pra \"fechar o link\" quando
    ambos os lados foram criados sem coordenar).

    Args:
        peer_id (int):
        body (SetPeerTokenApiV1HaPeersPeerIdTokenPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut]
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
    body: SetPeerTokenApiV1HaPeersPeerIdTokenPutBody,
) -> HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut | None:
    r"""Set Peer Token

     Substitui o token de um peer (usado pra \"fechar o link\" quando
    ambos os lados foram criados sem coordenar).

    Args:
        peer_id (int):
        body (SetPeerTokenApiV1HaPeersPeerIdTokenPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut
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
    body: SetPeerTokenApiV1HaPeersPeerIdTokenPutBody,
) -> Response[
    HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut
]:
    r"""Set Peer Token

     Substitui o token de um peer (usado pra \"fechar o link\" quando
    ambos os lados foram criados sem coordenar).

    Args:
        peer_id (int):
        body (SetPeerTokenApiV1HaPeersPeerIdTokenPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut]
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
    body: SetPeerTokenApiV1HaPeersPeerIdTokenPutBody,
) -> HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut | None:
    r"""Set Peer Token

     Substitui o token de um peer (usado pra \"fechar o link\" quando
    ambos os lados foram criados sem coordenar).

    Args:
        peer_id (int):
        body (SetPeerTokenApiV1HaPeersPeerIdTokenPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SetPeerTokenApiV1HaPeersPeerIdTokenPutResponseSetPeerTokenApiV1HaPeersPeerIdTokenPut
    """

    return (
        await asyncio_detailed(
            peer_id=peer_id,
            client=client,
            body=body,
        )
    ).parsed
