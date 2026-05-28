from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.threats_data_api_v1_threats_data_get_response_threats_data_api_v1_threats_data_get import (
    ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    limit: str | Unset = "10",
    client_ip: str | Unset = "",
    domain: str | Unset = "",
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["limit"] = limit

    params["client_ip"] = client_ip

    params["domain"] = domain

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/threats/data",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet | None:
    if response.status_code == 200:
        response_200 = ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet.from_dict(response.json())

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
) -> Response[HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
    client_ip: str | Unset = "",
    domain: str | Unset = "",
) -> Response[HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet]:
    """Threats Data

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.
        client_ip (str | Unset): Filtro exato por IP cliente — clica no chip do Top Default: ''.
        domain (str | Unset): Filtro exato por domínio — clica no chip do Top Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet]
    """

    kwargs = _get_kwargs(
        limit=limit,
        client_ip=client_ip,
        domain=domain,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
    client_ip: str | Unset = "",
    domain: str | Unset = "",
) -> HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet | None:
    """Threats Data

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.
        client_ip (str | Unset): Filtro exato por IP cliente — clica no chip do Top Default: ''.
        domain (str | Unset): Filtro exato por domínio — clica no chip do Top Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet
    """

    return sync_detailed(
        client=client,
        limit=limit,
        client_ip=client_ip,
        domain=domain,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
    client_ip: str | Unset = "",
    domain: str | Unset = "",
) -> Response[HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet]:
    """Threats Data

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.
        client_ip (str | Unset): Filtro exato por IP cliente — clica no chip do Top Default: ''.
        domain (str | Unset): Filtro exato por domínio — clica no chip do Top Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet]
    """

    kwargs = _get_kwargs(
        limit=limit,
        client_ip=client_ip,
        domain=domain,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    limit: str | Unset = "10",
    client_ip: str | Unset = "",
    domain: str | Unset = "",
) -> HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet | None:
    """Threats Data

    Args:
        limit (str | Unset): 10|20|50|100|'todos' Default: '10'.
        client_ip (str | Unset): Filtro exato por IP cliente — clica no chip do Top Default: ''.
        domain (str | Unset): Filtro exato por domínio — clica no chip do Top Default: ''.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ThreatsDataApiV1ThreatsDataGetResponseThreatsDataApiV1ThreatsDataGet
    """

    return (
        await asyncio_detailed(
            client=client,
            limit=limit,
            client_ip=client_ip,
            domain=domain,
        )
    ).parsed
