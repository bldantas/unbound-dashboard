from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.lookup_bulk_api_v1_geoip_lookup_bulk_post_body import LookupBulkApiV1GeoipLookupBulkPostBody
from ...models.lookup_bulk_api_v1_geoip_lookup_bulk_post_response_lookup_bulk_api_v1_geoip_lookup_bulk_post import (
    LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost,
)
from ...types import Response


def _get_kwargs(
    *,
    body: LookupBulkApiV1GeoipLookupBulkPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/geoip/lookup-bulk",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost | None:
    if response.status_code == 200:
        response_200 = LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost.from_dict(
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
) -> Response[HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: LookupBulkApiV1GeoipLookupBulkPostBody,
) -> Response[HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost]:
    """Lookup Bulk

    Args:
        body (LookupBulkApiV1GeoipLookupBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost]
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
    body: LookupBulkApiV1GeoipLookupBulkPostBody,
) -> HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost | None:
    """Lookup Bulk

    Args:
        body (LookupBulkApiV1GeoipLookupBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: LookupBulkApiV1GeoipLookupBulkPostBody,
) -> Response[HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost]:
    """Lookup Bulk

    Args:
        body (LookupBulkApiV1GeoipLookupBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: LookupBulkApiV1GeoipLookupBulkPostBody,
) -> HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost | None:
    """Lookup Bulk

    Args:
        body (LookupBulkApiV1GeoipLookupBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LookupBulkApiV1GeoipLookupBulkPostResponseLookupBulkApiV1GeoipLookupBulkPost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
