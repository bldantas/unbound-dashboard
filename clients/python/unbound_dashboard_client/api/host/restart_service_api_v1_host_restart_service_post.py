from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.restart_service_api_v1_host_restart_service_post_response_restart_service_api_v1_host_restart_service_post import (
    RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost,
)
from ...types import Response


def _get_kwargs(
    service: str,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/host/restart/{service}".format(
            service=quote(str(service), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
    | None
):
    if response.status_code == 202:
        response_202 = (
            RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost.from_dict(
                response.json()
            )
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
    HTTPValidationError | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    service: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
]:
    """Restart Service

     Reinicia um serviço whitelisted (api | unbound). Spawn detachado:
    o systemctl roda em session group novo, sobrevive se o caller for o
    próprio api_service sendo morto.

    Pedida pelo master multi-host nos batch ops; também útil localmente.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost]
    """

    kwargs = _get_kwargs(
        service=service,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    service: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
    | None
):
    """Restart Service

     Reinicia um serviço whitelisted (api | unbound). Spawn detachado:
    o systemctl roda em session group novo, sobrevive se o caller for o
    próprio api_service sendo morto.

    Pedida pelo master multi-host nos batch ops; também útil localmente.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
    """

    return sync_detailed(
        service=service,
        client=client,
    ).parsed


async def asyncio_detailed(
    service: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
]:
    """Restart Service

     Reinicia um serviço whitelisted (api | unbound). Spawn detachado:
    o systemctl roda em session group novo, sobrevive se o caller for o
    próprio api_service sendo morto.

    Pedida pelo master multi-host nos batch ops; também útil localmente.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost]
    """

    kwargs = _get_kwargs(
        service=service,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    service: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
    | None
):
    """Restart Service

     Reinicia um serviço whitelisted (api | unbound). Spawn detachado:
    o systemctl roda em session group novo, sobrevive se o caller for o
    próprio api_service sendo morto.

    Pedida pelo master multi-host nos batch ops; também útil localmente.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestartServiceApiV1HostRestartServicePostResponseRestartServiceApiV1HostRestartServicePost
    """

    return (
        await asyncio_detailed(
            service=service,
            client=client,
        )
    ).parsed
