from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.remove_range_api_v1_policies_slug_ranges_range_id_delete_response_remove_range_api_v1_policies_slug_ranges_range_id_delete import (
    RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete,
)
from ...types import Response


def _get_kwargs(
    slug: str,
    range_id: int,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "delete",
        "url": "/api/v1/policies/{slug}/ranges/{range_id}".format(
            slug=quote(str(slug), safe=""),
            range_id=quote(str(range_id), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
    | None
):
    if response.status_code == 200:
        response_200 = RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete.from_dict(
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
    | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    slug: str,
    range_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
]:
    """Remove Range

    Args:
        slug (str):
        range_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete]
    """

    kwargs = _get_kwargs(
        slug=slug,
        range_id=range_id,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    slug: str,
    range_id: int,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
    | None
):
    """Remove Range

    Args:
        slug (str):
        range_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
    """

    return sync_detailed(
        slug=slug,
        range_id=range_id,
        client=client,
    ).parsed


async def asyncio_detailed(
    slug: str,
    range_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
]:
    """Remove Range

    Args:
        slug (str):
        range_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete]
    """

    kwargs = _get_kwargs(
        slug=slug,
        range_id=range_id,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    slug: str,
    range_id: int,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
    | None
):
    """Remove Range

    Args:
        slug (str):
        range_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveRangeApiV1PoliciesSlugRangesRangeIdDeleteResponseRemoveRangeApiV1PoliciesSlugRangesRangeIdDelete
    """

    return (
        await asyncio_detailed(
            slug=slug,
            range_id=range_id,
            client=client,
        )
    ).parsed
