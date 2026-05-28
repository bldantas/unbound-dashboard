from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.update_destination_api_v1_backup_offsite_destinations_dest_id_put_body import (
    UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody,
)
from ...models.update_destination_api_v1_backup_offsite_destinations_dest_id_put_response_update_destination_api_v1_backup_offsite_destinations_dest_id_put import (
    UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut,
)
from ...types import Response


def _get_kwargs(
    dest_id: int,
    *,
    body: UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "put",
        "url": "/api/v1/backup-offsite/destinations/{dest_id}".format(
            dest_id=quote(str(dest_id), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
    | None
):
    if response.status_code == 200:
        response_200 = UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut.from_dict(
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
    | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
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
    body: UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody,
) -> Response[
    HTTPValidationError
    | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
]:
    """Update Destination

    Args:
        dest_id (int):
        body (UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut]
    """

    kwargs = _get_kwargs(
        dest_id=dest_id,
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    dest_id: int,
    *,
    client: AuthenticatedClient,
    body: UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody,
) -> (
    HTTPValidationError
    | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
    | None
):
    """Update Destination

    Args:
        dest_id (int):
        body (UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
    """

    return sync_detailed(
        dest_id=dest_id,
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    dest_id: int,
    *,
    client: AuthenticatedClient,
    body: UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody,
) -> Response[
    HTTPValidationError
    | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
]:
    """Update Destination

    Args:
        dest_id (int):
        body (UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut]
    """

    kwargs = _get_kwargs(
        dest_id=dest_id,
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    dest_id: int,
    *,
    client: AuthenticatedClient,
    body: UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody,
) -> (
    HTTPValidationError
    | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
    | None
):
    """Update Destination

    Args:
        dest_id (int):
        body (UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdateDestinationApiV1BackupOffsiteDestinationsDestIdPutResponseUpdateDestinationApiV1BackupOffsiteDestinationsDestIdPut
    """

    return (
        await asyncio_detailed(
            dest_id=dest_id,
            client=client,
            body=body,
        )
    ).parsed
