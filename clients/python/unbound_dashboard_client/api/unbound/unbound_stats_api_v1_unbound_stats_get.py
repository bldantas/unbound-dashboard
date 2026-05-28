from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.unbound_stats_api_v1_unbound_stats_get_response_unbound_stats_api_v1_unbound_stats_get import (
    UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/unbound/stats",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet | None:
    if response.status_code == 200:
        response_200 = UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet]:
    """Unbound Stats

     Sumário do daemon Unbound — qps, hit_ratio, latência, DNSSEC, blocks, etc.

    Cache TTL 60s (idêntico ao cron `aggregate_stats.php`). Múltiplas requests
    em paralelo esperam o mesmo build (lock interno).

    Substitui leitura direta de `data/latest_stats.json` via `api/stats.php`.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet | None:
    """Unbound Stats

     Sumário do daemon Unbound — qps, hit_ratio, latência, DNSSEC, blocks, etc.

    Cache TTL 60s (idêntico ao cron `aggregate_stats.php`). Múltiplas requests
    em paralelo esperam o mesmo build (lock interno).

    Substitui leitura direta de `data/latest_stats.json` via `api/stats.php`.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet]:
    """Unbound Stats

     Sumário do daemon Unbound — qps, hit_ratio, latência, DNSSEC, blocks, etc.

    Cache TTL 60s (idêntico ao cron `aggregate_stats.php`). Múltiplas requests
    em paralelo esperam o mesmo build (lock interno).

    Substitui leitura direta de `data/latest_stats.json` via `api/stats.php`.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet | None:
    """Unbound Stats

     Sumário do daemon Unbound — qps, hit_ratio, latência, DNSSEC, blocks, etc.

    Cache TTL 60s (idêntico ao cron `aggregate_stats.php`). Múltiplas requests
    em paralelo esperam o mesmo build (lock interno).

    Substitui leitura direta de `data/latest_stats.json` via `api/stats.php`.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        UnboundStatsApiV1UnboundStatsGetResponseUnboundStatsApiV1UnboundStatsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
