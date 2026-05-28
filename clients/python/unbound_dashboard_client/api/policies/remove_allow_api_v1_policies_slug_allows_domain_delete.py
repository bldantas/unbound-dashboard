from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.remove_allow_api_v1_policies_slug_allows_domain_delete_response_remove_allow_api_v1_policies_slug_allows_domain_delete import (
    RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete,
)
from ...types import Response


def _get_kwargs(
    slug: str,
    domain: str,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "delete",
        "url": "/api/v1/policies/{slug}/allows/{domain}".format(
            slug=quote(str(slug), safe=""),
            domain=quote(str(domain), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
    | None
):
    if response.status_code == 200:
        response_200 = RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete.from_dict(
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
    | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    slug: str,
    domain: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
]:
    """Remove Allow

    Args:
        slug (str):
        domain (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete]
    """

    kwargs = _get_kwargs(
        slug=slug,
        domain=domain,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    slug: str,
    domain: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
    | None
):
    """Remove Allow

    Args:
        slug (str):
        domain (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
    """

    return sync_detailed(
        slug=slug,
        domain=domain,
        client=client,
    ).parsed


async def asyncio_detailed(
    slug: str,
    domain: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
]:
    """Remove Allow

    Args:
        slug (str):
        domain (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete]
    """

    kwargs = _get_kwargs(
        slug=slug,
        domain=domain,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    slug: str,
    domain: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
    | None
):
    """Remove Allow

    Args:
        slug (str):
        domain (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveAllowApiV1PoliciesSlugAllowsDomainDeleteResponseRemoveAllowApiV1PoliciesSlugAllowsDomainDelete
    """

    return (
        await asyncio_detailed(
            slug=slug,
            domain=domain,
            client=client,
        )
    ).parsed
