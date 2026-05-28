from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.batch_restart_api_v1_hosts_batch_restart_service_post_response_batch_restart_api_v1_hosts_batch_restart_service_post import (
    BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    service: str,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/hosts/batch/restart/{service}".format(
            service=quote(str(service), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost
    | HTTPValidationError
    | None
):
    if response.status_code == 202:
        response_202 = BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost.from_dict(
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
    BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost
    | HTTPValidationError
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
    BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost
    | HTTPValidationError
]:
    """Batch Restart

     Restart em todos os hosts. Sequencial — fail isolado por host.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost | HTTPValidationError]
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
    BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost
    | HTTPValidationError
    | None
):
    """Batch Restart

     Restart em todos os hosts. Sequencial — fail isolado por host.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost | HTTPValidationError
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
    BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost
    | HTTPValidationError
]:
    """Batch Restart

     Restart em todos os hosts. Sequencial — fail isolado por host.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost | HTTPValidationError]
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
    BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost
    | HTTPValidationError
    | None
):
    """Batch Restart

     Restart em todos os hosts. Sequencial — fail isolado por host.

    Args:
        service (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BatchRestartApiV1HostsBatchRestartServicePostResponseBatchRestartApiV1HostsBatchRestartServicePost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            service=service,
            client=client,
        )
    ).parsed
