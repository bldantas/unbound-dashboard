from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.peer_ping_api_v1_cluster_peer_ping_get_response_peer_ping_api_v1_cluster_peer_ping_get import (
    PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    x_api_token: None | str | Unset = UNSET,
    authorization: None | str | Unset = UNSET,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}
    if not isinstance(x_api_token, Unset):
        headers["X-Api-Token"] = x_api_token

    if not isinstance(authorization, Unset):
        headers["authorization"] = authorization

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/cluster/peer-ping",
    }

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet | None:
    if response.status_code == 200:
        response_200 = PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet.from_dict(response.json())

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
) -> Response[HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient | Client,
    x_api_token: None | str | Unset = UNSET,
    authorization: None | str | Unset = UNSET,
) -> Response[HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet]:
    """Peer Ping

     Healthcheck autenticado entre peers HA.

    Validado contra ha_peers.api_token_hash (bcrypt). Retorna info do
    servidor pra o peer chamador saber com quem está falando.

    Args:
        x_api_token (None | str | Unset):
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet]
    """

    kwargs = _get_kwargs(
        x_api_token=x_api_token,
        authorization=authorization,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient | Client,
    x_api_token: None | str | Unset = UNSET,
    authorization: None | str | Unset = UNSET,
) -> HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet | None:
    """Peer Ping

     Healthcheck autenticado entre peers HA.

    Validado contra ha_peers.api_token_hash (bcrypt). Retorna info do
    servidor pra o peer chamador saber com quem está falando.

    Args:
        x_api_token (None | str | Unset):
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet
    """

    return sync_detailed(
        client=client,
        x_api_token=x_api_token,
        authorization=authorization,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient | Client,
    x_api_token: None | str | Unset = UNSET,
    authorization: None | str | Unset = UNSET,
) -> Response[HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet]:
    """Peer Ping

     Healthcheck autenticado entre peers HA.

    Validado contra ha_peers.api_token_hash (bcrypt). Retorna info do
    servidor pra o peer chamador saber com quem está falando.

    Args:
        x_api_token (None | str | Unset):
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet]
    """

    kwargs = _get_kwargs(
        x_api_token=x_api_token,
        authorization=authorization,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient | Client,
    x_api_token: None | str | Unset = UNSET,
    authorization: None | str | Unset = UNSET,
) -> HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet | None:
    """Peer Ping

     Healthcheck autenticado entre peers HA.

    Validado contra ha_peers.api_token_hash (bcrypt). Retorna info do
    servidor pra o peer chamador saber com quem está falando.

    Args:
        x_api_token (None | str | Unset):
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | PeerPingApiV1ClusterPeerPingGetResponsePeerPingApiV1ClusterPeerPingGet
    """

    return (
        await asyncio_detailed(
            client=client,
            x_api_token=x_api_token,
            authorization=authorization,
        )
    ).parsed
