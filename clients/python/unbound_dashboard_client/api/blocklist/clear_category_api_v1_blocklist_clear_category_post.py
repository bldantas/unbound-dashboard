from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.clear_category_api_v1_blocklist_clear_category_post_body import (
    ClearCategoryApiV1BlocklistClearCategoryPostBody,
)
from ...models.clear_category_api_v1_blocklist_clear_category_post_response_clear_category_api_v1_blocklist_clear_category_post import (
    ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    *,
    body: ClearCategoryApiV1BlocklistClearCategoryPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/blocklist/clear-category",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = (
            ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost.from_dict(
                response.json()
            )
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
    ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost
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
    body: ClearCategoryApiV1BlocklistClearCategoryPostBody,
) -> Response[
    ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost
    | HTTPValidationError
]:
    """Clear Category

     DELETE FROM blocklist_domains WHERE category = ?. Body: {category: str}.

    Args:
        body (ClearCategoryApiV1BlocklistClearCategoryPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost | HTTPValidationError]
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
    body: ClearCategoryApiV1BlocklistClearCategoryPostBody,
) -> (
    ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost
    | HTTPValidationError
    | None
):
    """Clear Category

     DELETE FROM blocklist_domains WHERE category = ?. Body: {category: str}.

    Args:
        body (ClearCategoryApiV1BlocklistClearCategoryPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: ClearCategoryApiV1BlocklistClearCategoryPostBody,
) -> Response[
    ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost
    | HTTPValidationError
]:
    """Clear Category

     DELETE FROM blocklist_domains WHERE category = ?. Body: {category: str}.

    Args:
        body (ClearCategoryApiV1BlocklistClearCategoryPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: ClearCategoryApiV1BlocklistClearCategoryPostBody,
) -> (
    ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost
    | HTTPValidationError
    | None
):
    """Clear Category

     DELETE FROM blocklist_domains WHERE category = ?. Body: {category: str}.

    Args:
        body (ClearCategoryApiV1BlocklistClearCategoryPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ClearCategoryApiV1BlocklistClearCategoryPostResponseClearCategoryApiV1BlocklistClearCategoryPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
