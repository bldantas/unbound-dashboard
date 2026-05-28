from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.host_storage_api_v1_host_storage_get_response_host_storage_api_v1_host_storage_get import (
    HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/host/storage",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet | None:
    if response.status_code == 200:
        response_200 = HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet]:
    """Host Storage

     Storage + Redis health pra widget do dashboard.

    - duckdb_bytes / duckdb_size_human: tamanho do arquivo principal
    - disk_total / disk_free / disk_used_pct: do mount onde o DuckDB está
    - redis_ok / redis_latency_ms: ping síncrono
    - workers_dir_bytes: total dos logs/WAL aux (best-effort)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet | None:
    """Host Storage

     Storage + Redis health pra widget do dashboard.

    - duckdb_bytes / duckdb_size_human: tamanho do arquivo principal
    - disk_total / disk_free / disk_used_pct: do mount onde o DuckDB está
    - redis_ok / redis_latency_ms: ping síncrono
    - workers_dir_bytes: total dos logs/WAL aux (best-effort)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet]:
    """Host Storage

     Storage + Redis health pra widget do dashboard.

    - duckdb_bytes / duckdb_size_human: tamanho do arquivo principal
    - disk_total / disk_free / disk_used_pct: do mount onde o DuckDB está
    - redis_ok / redis_latency_ms: ping síncrono
    - workers_dir_bytes: total dos logs/WAL aux (best-effort)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet | None:
    """Host Storage

     Storage + Redis health pra widget do dashboard.

    - duckdb_bytes / duckdb_size_human: tamanho do arquivo principal
    - disk_total / disk_free / disk_used_pct: do mount onde o DuckDB está
    - redis_ok / redis_latency_ms: ping síncrono
    - workers_dir_bytes: total dos logs/WAL aux (best-effort)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HostStorageApiV1HostStorageGetResponseHostStorageApiV1HostStorageGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
