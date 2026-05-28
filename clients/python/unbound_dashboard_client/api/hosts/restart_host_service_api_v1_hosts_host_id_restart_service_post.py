from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.restart_host_service_api_v1_hosts_host_id_restart_service_post_response_restart_host_service_api_v1_hosts_host_id_restart_service_post import (
    RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost,
)
from ...types import Response


def _get_kwargs(
    host_id: int,
    service: str,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/hosts/{host_id}/restart/{service}".format(
            host_id=quote(str(host_id), safe=""),
            service=quote(str(service), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
    | None
):
    if response.status_code == 202:
        response_202 = RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost.from_dict(
            response.json()
        )

        return response_202

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
    HTTPValidationError
    | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    host_id: int,
    service: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
]:
    """Restart Host Service

     Reinicia api ou unbound no agent específico.

    Args:
        host_id (int):
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost]
    """

    kwargs = _get_kwargs(
        host_id=host_id,
        service=service,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    host_id: int,
    service: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
    | None
):
    """Restart Host Service

     Reinicia api ou unbound no agent específico.

    Args:
        host_id (int):
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
    """

    return sync_detailed(
        host_id=host_id,
        service=service,
        client=client,
    ).parsed


async def asyncio_detailed(
    host_id: int,
    service: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
]:
    """Restart Host Service

     Reinicia api ou unbound no agent específico.

    Args:
        host_id (int):
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost]
    """

    kwargs = _get_kwargs(
        host_id=host_id,
        service=service,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    host_id: int,
    service: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
    | None
):
    """Restart Host Service

     Reinicia api ou unbound no agent específico.

    Args:
        host_id (int):
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestartHostServiceApiV1HostsHostIdRestartServicePostResponseRestartHostServiceApiV1HostsHostIdRestartServicePost
    """

    return (
        await asyncio_detailed(
            host_id=host_id,
            service=service,
            client=client,
        )
    ).parsed
