from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.restore_test_endpoint_api_v1_backup_offsite_restore_test_post_body_type_0 import (
    RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0,
)
from ...models.restore_test_endpoint_api_v1_backup_offsite_restore_test_post_response_restore_test_endpoint_api_v1_backup_offsite_restore_test_post import (
    RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    body: None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset = UNSET,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/backup-offsite/restore-test",
    }

    if isinstance(body, RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0):
        _kwargs["json"] = body.to_dict()
    else:
        _kwargs["json"] = body

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
    | None
):
    if response.status_code == 200:
        response_200 = RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost.from_dict(
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
    HTTPValidationError
    | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset = UNSET,
) -> Response[
    HTTPValidationError
    | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
]:
    r"""Restore Test Endpoint

     Baixa um backup recente (ou key específica se passada) e valida
    integridade do DuckDB sem restaurar no DB real.

    Body opcional: `{\"key\": \"s3-key.tar.gz\"}` pra testar uma versão específica.

    Args:
        body (None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    body: None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset = UNSET,
) -> (
    HTTPValidationError
    | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
    | None
):
    r"""Restore Test Endpoint

     Baixa um backup recente (ou key específica se passada) e valida
    integridade do DuckDB sem restaurar no DB real.

    Body opcional: `{\"key\": \"s3-key.tar.gz\"}` pra testar uma versão específica.

    Args:
        body (None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset = UNSET,
) -> Response[
    HTTPValidationError
    | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
]:
    r"""Restore Test Endpoint

     Baixa um backup recente (ou key específica se passada) e valida
    integridade do DuckDB sem restaurar no DB real.

    Body opcional: `{\"key\": \"s3-key.tar.gz\"}` pra testar uma versão específica.

    Args:
        body (None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset = UNSET,
) -> (
    HTTPValidationError
    | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
    | None
):
    r"""Restore Test Endpoint

     Baixa um backup recente (ou key específica se passada) e valida
    integridade do DuckDB sem restaurar no DB real.

    Body opcional: `{\"key\": \"s3-key.tar.gz\"}` pra testar uma versão específica.

    Args:
        body (None | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostBodyType0 | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestoreTestEndpointApiV1BackupOffsiteRestoreTestPostResponseRestoreTestEndpointApiV1BackupOffsiteRestoreTestPost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
