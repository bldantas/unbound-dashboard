from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.host_status_api_v1_host_status_get_response_host_status_api_v1_host_status_get import (
    HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/host/status",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet | None:
    if response.status_code == 200:
        response_200 = HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet]:
    """Host Status

     Snapshot agregado pro master polar. Inclui métricas que mudam em
    tempo real — não cachear no master.

    Campos:
      - version: VERSION local
      - uptime_seconds: tempo desde boot do api_service
      - alerts_active: count de alertas não-resolvidos
      - users_total: total de users cadastrados
      - sessions_active: total de sessões trackadas (Redis ou DuckDB)
      - queries_24h: total de query_logs nas últimas 24h
      - hit_ratio_24h: % de cache hits últimas 24h
      - duckdb_ok: True se SELECT 1 rolou
      - auth_kind: como o caller se autenticou (jwt | api_token)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet | None:
    """Host Status

     Snapshot agregado pro master polar. Inclui métricas que mudam em
    tempo real — não cachear no master.

    Campos:
      - version: VERSION local
      - uptime_seconds: tempo desde boot do api_service
      - alerts_active: count de alertas não-resolvidos
      - users_total: total de users cadastrados
      - sessions_active: total de sessões trackadas (Redis ou DuckDB)
      - queries_24h: total de query_logs nas últimas 24h
      - hit_ratio_24h: % de cache hits últimas 24h
      - duckdb_ok: True se SELECT 1 rolou
      - auth_kind: como o caller se autenticou (jwt | api_token)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet]:
    """Host Status

     Snapshot agregado pro master polar. Inclui métricas que mudam em
    tempo real — não cachear no master.

    Campos:
      - version: VERSION local
      - uptime_seconds: tempo desde boot do api_service
      - alerts_active: count de alertas não-resolvidos
      - users_total: total de users cadastrados
      - sessions_active: total de sessões trackadas (Redis ou DuckDB)
      - queries_24h: total de query_logs nas últimas 24h
      - hit_ratio_24h: % de cache hits últimas 24h
      - duckdb_ok: True se SELECT 1 rolou
      - auth_kind: como o caller se autenticou (jwt | api_token)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet | None:
    """Host Status

     Snapshot agregado pro master polar. Inclui métricas que mudam em
    tempo real — não cachear no master.

    Campos:
      - version: VERSION local
      - uptime_seconds: tempo desde boot do api_service
      - alerts_active: count de alertas não-resolvidos
      - users_total: total de users cadastrados
      - sessions_active: total de sessões trackadas (Redis ou DuckDB)
      - queries_24h: total de query_logs nas últimas 24h
      - hit_ratio_24h: % de cache hits últimas 24h
      - duckdb_ok: True se SELECT 1 rolou
      - auth_kind: como o caller se autenticou (jwt | api_token)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HostStatusApiV1HostStatusGetResponseHostStatusApiV1HostStatusGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
