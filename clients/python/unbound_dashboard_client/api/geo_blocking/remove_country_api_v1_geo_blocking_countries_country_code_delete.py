from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.remove_country_api_v1_geo_blocking_countries_country_code_delete_response_remove_country_api_v1_geo_blocking_countries_country_code_delete import (
    RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete,
)
from ...types import Response


def _get_kwargs(
    country_code: str,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "delete",
        "url": "/api/v1/geo-blocking/countries/{country_code}".format(
            country_code=quote(str(country_code), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
    | None
):
    if response.status_code == 200:
        response_200 = RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete.from_dict(
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
    | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    country_code: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
]:
    """Remove Country

    Args:
        country_code (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete]
    """

    kwargs = _get_kwargs(
        country_code=country_code,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    country_code: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
    | None
):
    """Remove Country

    Args:
        country_code (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
    """

    return sync_detailed(
        country_code=country_code,
        client=client,
    ).parsed


async def asyncio_detailed(
    country_code: str,
    *,
    client: AuthenticatedClient,
) -> Response[
    HTTPValidationError
    | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
]:
    """Remove Country

    Args:
        country_code (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete]
    """

    kwargs = _get_kwargs(
        country_code=country_code,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    country_code: str,
    *,
    client: AuthenticatedClient,
) -> (
    HTTPValidationError
    | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
    | None
):
    """Remove Country

    Args:
        country_code (str):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveCountryApiV1GeoBlockingCountriesCountryCodeDeleteResponseRemoveCountryApiV1GeoBlockingCountriesCountryCodeDelete
    """

    return (
        await asyncio_detailed(
            country_code=country_code,
            client=client,
        )
    ).parsed
