from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.delete_policy_api_v1_policies_slug_delete_response_delete_policy_api_v1_policies_slug_delete import (
    DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    slug: str,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "delete",
        "url": "/api/v1/policies/{slug}".format(
            slug=quote(str(slug), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete.from_dict(
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
) -> Response[DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError]:
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
) -> Response[DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError]:
    """Delete Policy

    Args:
        slug (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        slug=slug,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    slug: str,
    *,
    client: AuthenticatedClient,
) -> DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError | None:
    """Delete Policy

    Args:
        slug (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError
    """

    return sync_detailed(
        slug=slug,
        client=client,
    ).parsed


async def asyncio_detailed(
    slug: str,
    *,
    client: AuthenticatedClient,
) -> Response[DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError]:
    """Delete Policy

    Args:
        slug (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        slug=slug,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    slug: str,
    *,
    client: AuthenticatedClient,
) -> DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError | None:
    """Delete Policy

    Args:
        slug (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DeletePolicyApiV1PoliciesSlugDeleteResponseDeletePolicyApiV1PoliciesSlugDelete | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            slug=slug,
            client=client,
        )
    ).parsed
