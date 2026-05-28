from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_orgs_api_v1_organizations_get_response_list_orgs_api_v1_organizations_get import (
    ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/organizations/",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet | None:
    if response.status_code == 200:
        response_200 = ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet]:
    """List Orgs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet | None:
    """List Orgs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet]:
    """List Orgs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet | None:
    """List Orgs

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListOrgsApiV1OrganizationsGetResponseListOrgsApiV1OrganizationsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
