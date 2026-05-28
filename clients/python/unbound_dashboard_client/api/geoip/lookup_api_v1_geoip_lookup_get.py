from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.lookup_api_v1_geoip_lookup_get_response_lookup_api_v1_geoip_lookup_get import (
    LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet,
)
from ...types import UNSET, Response


def _get_kwargs(
    *,
    ip: str,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["ip"] = ip

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/geoip/lookup",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet | None:
    if response.status_code == 200:
        response_200 = LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet.from_dict(response.json())

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
) -> Response[HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    ip: str,
) -> Response[HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet]:
    """Lookup

    Args:
        ip (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet]
    """

    kwargs = _get_kwargs(
        ip=ip,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    ip: str,
) -> HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet | None:
    """Lookup

    Args:
        ip (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet
    """

    return sync_detailed(
        client=client,
        ip=ip,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    ip: str,
) -> Response[HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet]:
    """Lookup

    Args:
        ip (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet]
    """

    kwargs = _get_kwargs(
        ip=ip,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    ip: str,
) -> HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet | None:
    """Lookup

    Args:
        ip (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LookupApiV1GeoipLookupGetResponseLookupApiV1GeoipLookupGet
    """

    return (
        await asyncio_detailed(
            client=client,
            ip=ip,
        )
    ).parsed
