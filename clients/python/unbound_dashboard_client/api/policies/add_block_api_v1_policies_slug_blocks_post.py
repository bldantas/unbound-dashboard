from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.add_block_api_v1_policies_slug_blocks_post_body import AddBlockApiV1PoliciesSlugBlocksPostBody
from ...models.add_block_api_v1_policies_slug_blocks_post_response_add_block_api_v1_policies_slug_blocks_post import (
    AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    slug: str,
    *,
    body: AddBlockApiV1PoliciesSlugBlocksPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/policies/{slug}/blocks".format(
            slug=quote(str(slug), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError | None:
    if response.status_code == 201:
        response_201 = AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost.from_dict(
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
) -> Response[AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    slug: str,
    *,
    client: AuthenticatedClient,
    body: AddBlockApiV1PoliciesSlugBlocksPostBody,
) -> Response[AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError]:
    """Add Block

    Args:
        slug (str):
        body (AddBlockApiV1PoliciesSlugBlocksPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        slug=slug,
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    slug: str,
    *,
    client: AuthenticatedClient,
    body: AddBlockApiV1PoliciesSlugBlocksPostBody,
) -> AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError | None:
    """Add Block

    Args:
        slug (str):
        body (AddBlockApiV1PoliciesSlugBlocksPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError
    """

    return sync_detailed(
        slug=slug,
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    slug: str,
    *,
    client: AuthenticatedClient,
    body: AddBlockApiV1PoliciesSlugBlocksPostBody,
) -> Response[AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError]:
    """Add Block

    Args:
        slug (str):
        body (AddBlockApiV1PoliciesSlugBlocksPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        slug=slug,
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    slug: str,
    *,
    client: AuthenticatedClient,
    body: AddBlockApiV1PoliciesSlugBlocksPostBody,
) -> AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError | None:
    """Add Block

    Args:
        slug (str):
        body (AddBlockApiV1PoliciesSlugBlocksPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AddBlockApiV1PoliciesSlugBlocksPostResponseAddBlockApiV1PoliciesSlugBlocksPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            slug=slug,
            client=client,
            body=body,
        )
    ).parsed
