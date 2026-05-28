from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.set_blocked_api_v1_geo_blocking_countries_country_code_blocked_put_body import (
    SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody,
)
from ...models.set_blocked_api_v1_geo_blocking_countries_country_code_blocked_put_response_set_blocked_api_v1_geo_blocking_countries_country_code_blocked_put import (
    SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut,
)
from ...types import Response


def _get_kwargs(
    country_code: str,
    *,
    body: SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "put",
        "url": "/api/v1/geo-blocking/countries/{country_code}/blocked".format(
            country_code=quote(str(country_code), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
    | None
):
    if response.status_code == 200:
        response_200 = SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut.from_dict(
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
    | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
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
    body: SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody,
) -> Response[
    HTTPValidationError
    | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
]:
    """Set Blocked

    Args:
        country_code (str):
        body (SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut]
    """

    kwargs = _get_kwargs(
        country_code=country_code,
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    country_code: str,
    *,
    client: AuthenticatedClient,
    body: SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody,
) -> (
    HTTPValidationError
    | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
    | None
):
    """Set Blocked

    Args:
        country_code (str):
        body (SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
    """

    return sync_detailed(
        country_code=country_code,
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    country_code: str,
    *,
    client: AuthenticatedClient,
    body: SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody,
) -> Response[
    HTTPValidationError
    | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
]:
    """Set Blocked

    Args:
        country_code (str):
        body (SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut]
    """

    kwargs = _get_kwargs(
        country_code=country_code,
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    country_code: str,
    *,
    client: AuthenticatedClient,
    body: SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody,
) -> (
    HTTPValidationError
    | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
    | None
):
    """Set Blocked

    Args:
        country_code (str):
        body (SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPutResponseSetBlockedApiV1GeoBlockingCountriesCountryCodeBlockedPut
    """

    return (
        await asyncio_detailed(
            country_code=country_code,
            client=client,
            body=body,
        )
    ).parsed
