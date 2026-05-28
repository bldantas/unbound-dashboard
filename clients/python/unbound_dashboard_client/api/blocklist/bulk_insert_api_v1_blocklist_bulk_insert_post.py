from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.bulk_insert_api_v1_blocklist_bulk_insert_post_body_item import (
    BulkInsertApiV1BlocklistBulkInsertPostBodyItem,
)
from ...models.bulk_insert_api_v1_blocklist_bulk_insert_post_response_bulk_insert_api_v1_blocklist_bulk_insert_post import (
    BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    *,
    body: list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem],
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/blocklist/bulk-insert",
    }

    _kwargs["json"] = []
    for body_item_data in body:
        body_item = body_item_data.to_dict()
        _kwargs["json"].append(body_item)

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost.from_dict(
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
    BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError
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
    body: list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem],
) -> Response[
    BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError
]:
    """Bulk Insert

     Bulk UPSERT (legacy shim). Body: [{domain, category, severity}].

    Pós-V9 mapeia category → primeira source que casa. Novos chamadores devem
    usar POST /sources/{slug}/sync no lugar.

    Args:
        body (list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError]
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
    body: list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem],
) -> BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError | None:
    """Bulk Insert

     Bulk UPSERT (legacy shim). Body: [{domain, category, severity}].

    Pós-V9 mapeia category → primeira source que casa. Novos chamadores devem
    usar POST /sources/{slug}/sync no lugar.

    Args:
        body (list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem],
) -> Response[
    BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError
]:
    """Bulk Insert

     Bulk UPSERT (legacy shim). Body: [{domain, category, severity}].

    Pós-V9 mapeia category → primeira source que casa. Novos chamadores devem
    usar POST /sources/{slug}/sync no lugar.

    Args:
        body (list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem],
) -> BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError | None:
    """Bulk Insert

     Bulk UPSERT (legacy shim). Body: [{domain, category, severity}].

    Pós-V9 mapeia category → primeira source que casa. Novos chamadores devem
    usar POST /sources/{slug}/sync no lugar.

    Args:
        body (list[BulkInsertApiV1BlocklistBulkInsertPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BulkInsertApiV1BlocklistBulkInsertPostResponseBulkInsertApiV1BlocklistBulkInsertPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
