from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.upload_all_destinations_api_v1_backup_offsite_destinations_upload_all_post_response_upload_all_destinations_api_v1_backup_offsite_destinations_upload_all_post import (
    UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/backup-offsite/destinations/upload-all",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
    | None
):
    if response.status_code == 200:
        response_200 = UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[
    UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
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
) -> Response[
    UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
]:
    """Upload All Destinations

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> (
    UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
    | None
):
    """Upload All Destinations

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
]:
    """Upload All Destinations

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
    | None
):
    """Upload All Destinations

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        UploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPostResponseUploadAllDestinationsApiV1BackupOffsiteDestinationsUploadAllPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
