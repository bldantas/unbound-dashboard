from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.update_policy_api_v1_policies_slug_patch_body import UpdatePolicyApiV1PoliciesSlugPatchBody
from ...models.update_policy_api_v1_policies_slug_patch_response_update_policy_api_v1_policies_slug_patch import (
    UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch,
)
from ...types import Response


def _get_kwargs(
    slug: str,
    *,
    body: UpdatePolicyApiV1PoliciesSlugPatchBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "patch",
        "url": "/api/v1/policies/{slug}".format(
            slug=quote(str(slug), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch | None:
    if response.status_code == 200:
        response_200 = UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch.from_dict(
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
) -> Response[HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch]:
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
    body: UpdatePolicyApiV1PoliciesSlugPatchBody,
) -> Response[HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch]:
    """Update Policy

    Args:
        slug (str):
        body (UpdatePolicyApiV1PoliciesSlugPatchBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch]
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
    body: UpdatePolicyApiV1PoliciesSlugPatchBody,
) -> HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch | None:
    """Update Policy

    Args:
        slug (str):
        body (UpdatePolicyApiV1PoliciesSlugPatchBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch
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
    body: UpdatePolicyApiV1PoliciesSlugPatchBody,
) -> Response[HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch]:
    """Update Policy

    Args:
        slug (str):
        body (UpdatePolicyApiV1PoliciesSlugPatchBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch]
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
    body: UpdatePolicyApiV1PoliciesSlugPatchBody,
) -> HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch | None:
    """Update Policy

    Args:
        slug (str):
        body (UpdatePolicyApiV1PoliciesSlugPatchBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdatePolicyApiV1PoliciesSlugPatchResponseUpdatePolicyApiV1PoliciesSlugPatch
    """

    return (
        await asyncio_detailed(
            slug=slug,
            client=client,
            body=body,
        )
    ).parsed
