from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_info_api_v1_doh_inbound_info_get_response_get_info_api_v1_doh_inbound_info_get import (
    GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/doh-inbound/info",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet | None:
    if response.status_code == 200:
        response_200 = GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet]:
    """Get Info

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet | None:
    """Get Info

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet]:
    """Get Info

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet | None:
    """Get Info

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetInfoApiV1DohInboundInfoGetResponseGetInfoApiV1DohInboundInfoGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
