from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.sync_source_api_v1_blocklist_sources_slug_sync_post_response_sync_source_api_v1_blocklist_sources_slug_sync_post import (
    SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    slug: str,
    *,
    force: bool | Unset = True,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["force"] = force

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/blocklist/sources/{slug}/sync".format(
            slug=quote(str(slug), safe=""),
        ),
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
    | None
):
    if response.status_code == 200:
        response_200 = (
            SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost.from_dict(
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
    HTTPValidationError | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
]:
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
    force: bool | Unset = True,
) -> Response[
    HTTPValidationError | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
]:
    """Sync Source

     Dispara sync sob demanda. Retorna {status, count, error}.

    Args:
        slug (str):
        force (bool | Unset): Se true, sincroniza mesmo se last_sync recente Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost]
    """

    kwargs = _get_kwargs(
        slug=slug,
        force=force,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    slug: str,
    *,
    client: AuthenticatedClient,
    force: bool | Unset = True,
) -> (
    HTTPValidationError
    | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
    | None
):
    """Sync Source

     Dispara sync sob demanda. Retorna {status, count, error}.

    Args:
        slug (str):
        force (bool | Unset): Se true, sincroniza mesmo se last_sync recente Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
    """

    return sync_detailed(
        slug=slug,
        client=client,
        force=force,
    ).parsed


async def asyncio_detailed(
    slug: str,
    *,
    client: AuthenticatedClient,
    force: bool | Unset = True,
) -> Response[
    HTTPValidationError | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
]:
    """Sync Source

     Dispara sync sob demanda. Retorna {status, count, error}.

    Args:
        slug (str):
        force (bool | Unset): Se true, sincroniza mesmo se last_sync recente Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost]
    """

    kwargs = _get_kwargs(
        slug=slug,
        force=force,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    slug: str,
    *,
    client: AuthenticatedClient,
    force: bool | Unset = True,
) -> (
    HTTPValidationError
    | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
    | None
):
    """Sync Source

     Dispara sync sob demanda. Retorna {status, count, error}.

    Args:
        slug (str):
        force (bool | Unset): Se true, sincroniza mesmo se last_sync recente Default: True.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SyncSourceApiV1BlocklistSourcesSlugSyncPostResponseSyncSourceApiV1BlocklistSourcesSlugSyncPost
    """

    return (
        await asyncio_detailed(
            slug=slug,
            client=client,
            force=force,
        )
    ).parsed
