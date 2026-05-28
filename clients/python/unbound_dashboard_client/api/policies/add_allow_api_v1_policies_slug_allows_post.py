from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.add_allow_api_v1_policies_slug_allows_post_body import AddAllowApiV1PoliciesSlugAllowsPostBody
from ...models.add_allow_api_v1_policies_slug_allows_post_response_add_allow_api_v1_policies_slug_allows_post import (
    AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    slug: str,
    *,
    body: AddAllowApiV1PoliciesSlugAllowsPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/policies/{slug}/allows".format(
            slug=quote(str(slug), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError | None:
    if response.status_code == 201:
        response_201 = AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost.from_dict(
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
) -> Response[AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError]:
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
    body: AddAllowApiV1PoliciesSlugAllowsPostBody,
) -> Response[AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError]:
    """Add Allow

    Args:
        slug (str):
        body (AddAllowApiV1PoliciesSlugAllowsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError]
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
    body: AddAllowApiV1PoliciesSlugAllowsPostBody,
) -> AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError | None:
    """Add Allow

    Args:
        slug (str):
        body (AddAllowApiV1PoliciesSlugAllowsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError
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
    body: AddAllowApiV1PoliciesSlugAllowsPostBody,
) -> Response[AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError]:
    """Add Allow

    Args:
        slug (str):
        body (AddAllowApiV1PoliciesSlugAllowsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError]
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
    body: AddAllowApiV1PoliciesSlugAllowsPostBody,
) -> AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError | None:
    """Add Allow

    Args:
        slug (str):
        body (AddAllowApiV1PoliciesSlugAllowsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AddAllowApiV1PoliciesSlugAllowsPostResponseAddAllowApiV1PoliciesSlugAllowsPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            slug=slug,
            client=client,
            body=body,
        )
    ).parsed
