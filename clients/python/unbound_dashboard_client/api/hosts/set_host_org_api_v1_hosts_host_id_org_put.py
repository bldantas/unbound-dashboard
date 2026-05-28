from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.host_set_org import HostSetOrg
from ...models.http_validation_error import HTTPValidationError
from ...models.set_host_org_api_v1_hosts_host_id_org_put_response_set_host_org_api_v1_hosts_host_id_org_put import (
    SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut,
)
from ...types import Response


def _get_kwargs(
    host_id: int,
    *,
    body: HostSetOrg,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "put",
        "url": "/api/v1/hosts/{host_id}/org".format(
            host_id=quote(str(host_id), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut | None:
    if response.status_code == 200:
        response_200 = SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut.from_dict(
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
) -> Response[HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut]:
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
    body: HostSetOrg,
) -> Response[HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut]:
    """Set Host Org

     Admin global pode mover qualquer host. User org-scoped só remaneja
    hosts da própria org (e só pra própria org ou pra global=None se quiser
    publicar — mas evitamos publicar). Aqui simplificado: só admin global.

    Args:
        host_id (int):
        body (HostSetOrg):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut]
    """

    kwargs = _get_kwargs(
        host_id=host_id,
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    host_id: int,
    *,
    client: AuthenticatedClient,
    body: HostSetOrg,
) -> HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut | None:
    """Set Host Org

     Admin global pode mover qualquer host. User org-scoped só remaneja
    hosts da própria org (e só pra própria org ou pra global=None se quiser
    publicar — mas evitamos publicar). Aqui simplificado: só admin global.

    Args:
        host_id (int):
        body (HostSetOrg):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut
    """

    return sync_detailed(
        host_id=host_id,
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    host_id: int,
    *,
    client: AuthenticatedClient,
    body: HostSetOrg,
) -> Response[HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut]:
    """Set Host Org

     Admin global pode mover qualquer host. User org-scoped só remaneja
    hosts da própria org (e só pra própria org ou pra global=None se quiser
    publicar — mas evitamos publicar). Aqui simplificado: só admin global.

    Args:
        host_id (int):
        body (HostSetOrg):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut]
    """

    kwargs = _get_kwargs(
        host_id=host_id,
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    host_id: int,
    *,
    client: AuthenticatedClient,
    body: HostSetOrg,
) -> HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut | None:
    """Set Host Org

     Admin global pode mover qualquer host. User org-scoped só remaneja
    hosts da própria org (e só pra própria org ou pra global=None se quiser
    publicar — mas evitamos publicar). Aqui simplificado: só admin global.

    Args:
        host_id (int):
        body (HostSetOrg):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SetHostOrgApiV1HostsHostIdOrgPutResponseSetHostOrgApiV1HostsHostIdOrgPut
    """

    return (
        await asyncio_detailed(
            host_id=host_id,
            client=client,
            body=body,
        )
    ).parsed
