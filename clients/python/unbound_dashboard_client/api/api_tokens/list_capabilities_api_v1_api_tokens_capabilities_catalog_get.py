from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_capabilities_api_v1_api_tokens_capabilities_catalog_get_response_list_capabilities_api_v1_api_tokens_capabilities_catalog_get import (
    ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/api-tokens/capabilities-catalog",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
    | None
):
    if response.status_code == 200:
        response_200 = ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[
    ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
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
) -> Response[
    ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
]:
    """List Capabilities

     Lista capabilities disponíveis pra atribuir a tokens.

    Usado pela UI pra montar checkboxes na criação de token escopado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> (
    ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
    | None
):
    """List Capabilities

     Lista capabilities disponíveis pra atribuir a tokens.

    Usado pela UI pra montar checkboxes na criação de token escopado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
]:
    """List Capabilities

     Lista capabilities disponíveis pra atribuir a tokens.

    Usado pela UI pra montar checkboxes na criação de token escopado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
    | None
):
    """List Capabilities

     Lista capabilities disponíveis pra atribuir a tokens.

    Usado pela UI pra montar checkboxes na criação de token escopado.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGetResponseListCapabilitiesApiV1ApiTokensCapabilitiesCatalogGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
