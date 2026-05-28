from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.restore_backup_api_v1_updates_restore_post_response_restore_backup_api_v1_updates_restore_post import (
    RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost,
)
from ...models.restore_request import RestoreRequest
from ...types import Response


def _get_kwargs(
    *,
    body: RestoreRequest,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/updates/restore",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost | None:
    if response.status_code == 202:
        response_202 = RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost.from_dict(
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
) -> Response[HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: RestoreRequest,
) -> Response[HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost]:
    """Restore Backup

     Dispara restore de um backup específico (criado por update.sh anterior).
    Reusa lock global — só uma operação por vez. Job_id retornado pode
    ser usado pra acompanhar status/log via endpoints existentes.

    Args:
        body (RestoreRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost]
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
    body: RestoreRequest,
) -> HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost | None:
    """Restore Backup

     Dispara restore de um backup específico (criado por update.sh anterior).
    Reusa lock global — só uma operação por vez. Job_id retornado pode
    ser usado pra acompanhar status/log via endpoints existentes.

    Args:
        body (RestoreRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: RestoreRequest,
) -> Response[HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost]:
    """Restore Backup

     Dispara restore de um backup específico (criado por update.sh anterior).
    Reusa lock global — só uma operação por vez. Job_id retornado pode
    ser usado pra acompanhar status/log via endpoints existentes.

    Args:
        body (RestoreRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: RestoreRequest,
) -> HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost | None:
    """Restore Backup

     Dispara restore de um backup específico (criado por update.sh anterior).
    Reusa lock global — só uma operação por vez. Job_id retornado pode
    ser usado pra acompanhar status/log via endpoints existentes.

    Args:
        body (RestoreRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RestoreBackupApiV1UpdatesRestorePostResponseRestoreBackupApiV1UpdatesRestorePost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
