from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.create_destination_api_v1_backup_offsite_destinations_post_body import (
    CreateDestinationApiV1BackupOffsiteDestinationsPostBody,
)
from ...models.create_destination_api_v1_backup_offsite_destinations_post_response_create_destination_api_v1_backup_offsite_destinations_post import (
    CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    *,
    body: CreateDestinationApiV1BackupOffsiteDestinationsPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/backup-offsite/destinations",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost
    | HTTPValidationError
    | None
):
    if response.status_code == 201:
        response_201 = CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost.from_dict(
            response.json()
        )

        return response_201

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
    CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost
    | HTTPValidationError
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
    body: CreateDestinationApiV1BackupOffsiteDestinationsPostBody,
) -> Response[
    CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost
    | HTTPValidationError
]:
    """Create Destination

    Args:
        body (CreateDestinationApiV1BackupOffsiteDestinationsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost | HTTPValidationError]
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
    body: CreateDestinationApiV1BackupOffsiteDestinationsPostBody,
) -> (
    CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost
    | HTTPValidationError
    | None
):
    """Create Destination

    Args:
        body (CreateDestinationApiV1BackupOffsiteDestinationsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: CreateDestinationApiV1BackupOffsiteDestinationsPostBody,
) -> Response[
    CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost
    | HTTPValidationError
]:
    """Create Destination

    Args:
        body (CreateDestinationApiV1BackupOffsiteDestinationsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: CreateDestinationApiV1BackupOffsiteDestinationsPostBody,
) -> (
    CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost
    | HTTPValidationError
    | None
):
    """Create Destination

    Args:
        body (CreateDestinationApiV1BackupOffsiteDestinationsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CreateDestinationApiV1BackupOffsiteDestinationsPostResponseCreateDestinationApiV1BackupOffsiteDestinationsPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
