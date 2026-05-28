from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.upgrade_host_api_v1_hosts_host_id_upgrade_post_response_upgrade_host_api_v1_hosts_host_id_upgrade_post import (
    UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost,
)
from ...models.upgrade_request import UpgradeRequest
from ...types import Response


def _get_kwargs(
    host_id: int,
    *,
    body: UpgradeRequest,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/hosts/{host_id}/upgrade".format(
            host_id=quote(str(host_id), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost | None:
    if response.status_code == 202:
        response_202 = UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost.from_dict(
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
    HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost
]:
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
    body: UpgradeRequest,
) -> Response[
    HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost
]:
    """Upgrade Host

     Dispara self-update no agent pra versão informada.

    Args:
        host_id (int):
        body (UpgradeRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost]
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
    body: UpgradeRequest,
) -> HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost | None:
    """Upgrade Host

     Dispara self-update no agent pra versão informada.

    Args:
        host_id (int):
        body (UpgradeRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost
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
    body: UpgradeRequest,
) -> Response[
    HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost
]:
    """Upgrade Host

     Dispara self-update no agent pra versão informada.

    Args:
        host_id (int):
        body (UpgradeRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost]
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
    body: UpgradeRequest,
) -> HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost | None:
    """Upgrade Host

     Dispara self-update no agent pra versão informada.

    Args:
        host_id (int):
        body (UpgradeRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpgradeHostApiV1HostsHostIdUpgradePostResponseUpgradeHostApiV1HostsHostIdUpgradePost
    """

    return (
        await asyncio_detailed(
            host_id=host_id,
            client=client,
            body=body,
        )
    ).parsed
