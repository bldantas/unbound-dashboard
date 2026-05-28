from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.distribution_api_v1_geoip_distribution_get_response_distribution_api_v1_geoip_distribution_get import (
    DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    hours: int | Unset = 24,
    limit: int | Unset = 50,
    action: str | Unset = "",
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["hours"] = hours

    params["limit"] = limit

    params["action"] = action

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/geoip/distribution",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet.from_dict(
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
) -> Response[DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError]:
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
    limit: int | Unset = 50,
    action: str | Unset = "",
) -> Response[DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError]:
    """Distribution

     Distribuição global de queries por país.

    action vazio = todas; 'blocked'/'resolved'/'cached'/'nxdomain_upstream' filtra.

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 50.
        action (str | Unset):  Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        hours=hours,
        limit=limit,
        action=action,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    limit: int | Unset = 50,
    action: str | Unset = "",
) -> DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError | None:
    """Distribution

     Distribuição global de queries por país.

    action vazio = todas; 'blocked'/'resolved'/'cached'/'nxdomain_upstream' filtra.

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 50.
        action (str | Unset):  Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        hours=hours,
        limit=limit,
        action=action,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    limit: int | Unset = 50,
    action: str | Unset = "",
) -> Response[DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError]:
    """Distribution

     Distribuição global de queries por país.

    action vazio = todas; 'blocked'/'resolved'/'cached'/'nxdomain_upstream' filtra.

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 50.
        action (str | Unset):  Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        hours=hours,
        limit=limit,
        action=action,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    limit: int | Unset = 50,
    action: str | Unset = "",
) -> DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError | None:
    """Distribution

     Distribuição global de queries por país.

    action vazio = todas; 'blocked'/'resolved'/'cached'/'nxdomain_upstream' filtra.

    Args:
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 50.
        action (str | Unset):  Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DistributionApiV1GeoipDistributionGetResponseDistributionApiV1GeoipDistributionGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            hours=hours,
            limit=limit,
            action=action,
        )
    ).parsed
