from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.domains_to_block_api_v1_blocklist_domains_to_block_get_response_domains_to_block_api_v1_blocklist_domains_to_block_get import (
    DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/blocklist/domains-to-block",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet | None:
    if response.status_code == 200:
        response_200 = DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet]:
    """Domains To Block

     União dos domínios em sources com block_enabled=true MENOS exceptions.

    Consumido pelo PHP UnboundConfigManager pra regerar
    /etc/unbound/includes/blocked_domains.conf. Resposta pode ser pesada
    (centenas de milhares); não pagina.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet | None:
    """Domains To Block

     União dos domínios em sources com block_enabled=true MENOS exceptions.

    Consumido pelo PHP UnboundConfigManager pra regerar
    /etc/unbound/includes/blocked_domains.conf. Resposta pode ser pesada
    (centenas de milhares); não pagina.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet]:
    """Domains To Block

     União dos domínios em sources com block_enabled=true MENOS exceptions.

    Consumido pelo PHP UnboundConfigManager pra regerar
    /etc/unbound/includes/blocked_domains.conf. Resposta pode ser pesada
    (centenas de milhares); não pagina.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet | None:
    """Domains To Block

     União dos domínios em sources com block_enabled=true MENOS exceptions.

    Consumido pelo PHP UnboundConfigManager pra regerar
    /etc/unbound/includes/blocked_domains.conf. Resposta pode ser pesada
    (centenas de milhares); não pagina.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DomainsToBlockApiV1BlocklistDomainsToBlockGetResponseDomainsToBlockApiV1BlocklistDomainsToBlockGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
