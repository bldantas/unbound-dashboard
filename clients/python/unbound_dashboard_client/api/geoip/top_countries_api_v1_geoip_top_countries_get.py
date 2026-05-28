from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.top_countries_api_v1_geoip_top_countries_get_response_top_countries_api_v1_geoip_top_countries_get import (
    TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    hours: int | Unset = 24,
    limit: int | Unset = 20,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["hours"] = hours

    params["limit"] = limit

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/geoip/top-countries",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet | None:
    if response.status_code == 200:
        response_200 = TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet.from_dict(
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
) -> Response[HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    limit: int | Unset = 20,
) -> Response[HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet]:
    """Top Countries

     Top países dos clientes BLOCKED (compat — mantido pra /threats.php).

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet]
    """

    kwargs = _get_kwargs(
        hours=hours,
        limit=limit,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    limit: int | Unset = 20,
) -> HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet | None:
    """Top Countries

     Top países dos clientes BLOCKED (compat — mantido pra /threats.php).

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet
    """

    return sync_detailed(
        client=client,
        hours=hours,
        limit=limit,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    limit: int | Unset = 20,
) -> Response[HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet]:
    """Top Countries

     Top países dos clientes BLOCKED (compat — mantido pra /threats.php).

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet]
    """

    kwargs = _get_kwargs(
        hours=hours,
        limit=limit,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    limit: int | Unset = 20,
) -> HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet | None:
    """Top Countries

     Top países dos clientes BLOCKED (compat — mantido pra /threats.php).

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 20.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | TopCountriesApiV1GeoipTopCountriesGetResponseTopCountriesApiV1GeoipTopCountriesGet
    """

    return (
        await asyncio_detailed(
            client=client,
            hours=hours,
            limit=limit,
        )
    ).parsed
