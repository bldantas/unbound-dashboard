from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.test_destination_api_v1_backup_offsite_destinations_dest_id_test_post_response_test_destination_api_v1_backup_offsite_destinations_dest_id_test_post import (
    TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost,
)
from ...types import Response


def _get_kwargs(
    dest_id: int,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/backup-offsite/destinations/{dest_id}/test".format(
            dest_id=quote(str(dest_id), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
    | None
):
    if response.status_code == 200:
        response_200 = TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost.from_dict(
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
    | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    dest_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
]:
    """Test Destination

    Args:
        dest_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost]
    """

    kwargs = _get_kwargs(
        dest_id=dest_id,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    dest_id: int,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
    | None
):
    """Test Destination

    Args:
        dest_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
    """

    return sync_detailed(
        dest_id=dest_id,
        client=client,
    ).parsed


async def asyncio_detailed(
    dest_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
]:
    """Test Destination

    Args:
        dest_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost]
    """

    kwargs = _get_kwargs(
        dest_id=dest_id,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    dest_id: int,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
    | None
):
    """Test Destination

    Args:
        dest_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | TestDestinationApiV1BackupOffsiteDestinationsDestIdTestPostResponseTestDestinationApiV1BackupOffsiteDestinationsDestIdTestPost
    """

    return (
        await asyncio_detailed(
            dest_id=dest_id,
            client=client,
        )
    ).parsed
