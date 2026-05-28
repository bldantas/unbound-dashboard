from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.summary_api_v1_stats_summary_get_response_summary_api_v1_stats_summary_get import (
    SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    window_hours: int | Unset = 24,
    top_n: int | Unset = 10,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["window_hours"] = window_hours

    params["top_n"] = top_n

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/stats/summary",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet | None:
    if response.status_code == 200:
        response_200 = SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet.from_dict(response.json())

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
) -> Response[HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient | Client,
    window_hours: int | Unset = 24,
    top_n: int | Unset = 10,
) -> Response[HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet]:
    """Summary

     Sumário de DNS na última janela: totais (total/blocked/resolved + block_rate),
    top domínios bloqueados e top clientes por volume.

    Lê do DuckDB em `/var/lib/unbound-dashboard/unbound_dash.duckdb` — snapshot
    populado por `tools/migrate_mariadb_to_duckdb.py`. Quando o worker
    `log_watcher.py` estiver ativo, será atualizado em tempo real.

    Args:
        window_hours (int | Unset): Janela retroativa em horas (1-720) Default: 24.
        top_n (int | Unset): Quantidade de top domínios/clientes Default: 10.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet]
    """

    kwargs = _get_kwargs(
        window_hours=window_hours,
        top_n=top_n,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient | Client,
    window_hours: int | Unset = 24,
    top_n: int | Unset = 10,
) -> HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet | None:
    """Summary

     Sumário de DNS na última janela: totais (total/blocked/resolved + block_rate),
    top domínios bloqueados e top clientes por volume.

    Lê do DuckDB em `/var/lib/unbound-dashboard/unbound_dash.duckdb` — snapshot
    populado por `tools/migrate_mariadb_to_duckdb.py`. Quando o worker
    `log_watcher.py` estiver ativo, será atualizado em tempo real.

    Args:
        window_hours (int | Unset): Janela retroativa em horas (1-720) Default: 24.
        top_n (int | Unset): Quantidade de top domínios/clientes Default: 10.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet
    """

    return sync_detailed(
        client=client,
        window_hours=window_hours,
        top_n=top_n,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient | Client,
    window_hours: int | Unset = 24,
    top_n: int | Unset = 10,
) -> Response[HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet]:
    """Summary

     Sumário de DNS na última janela: totais (total/blocked/resolved + block_rate),
    top domínios bloqueados e top clientes por volume.

    Lê do DuckDB em `/var/lib/unbound-dashboard/unbound_dash.duckdb` — snapshot
    populado por `tools/migrate_mariadb_to_duckdb.py`. Quando o worker
    `log_watcher.py` estiver ativo, será atualizado em tempo real.

    Args:
        window_hours (int | Unset): Janela retroativa em horas (1-720) Default: 24.
        top_n (int | Unset): Quantidade de top domínios/clientes Default: 10.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet]
    """

    kwargs = _get_kwargs(
        window_hours=window_hours,
        top_n=top_n,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient | Client,
    window_hours: int | Unset = 24,
    top_n: int | Unset = 10,
) -> HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet | None:
    """Summary

     Sumário de DNS na última janela: totais (total/blocked/resolved + block_rate),
    top domínios bloqueados e top clientes por volume.

    Lê do DuckDB em `/var/lib/unbound-dashboard/unbound_dash.duckdb` — snapshot
    populado por `tools/migrate_mariadb_to_duckdb.py`. Quando o worker
    `log_watcher.py` estiver ativo, será atualizado em tempo real.

    Args:
        window_hours (int | Unset): Janela retroativa em horas (1-720) Default: 24.
        top_n (int | Unset): Quantidade de top domínios/clientes Default: 10.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SummaryApiV1StatsSummaryGetResponseSummaryApiV1StatsSummaryGet
    """

    return (
        await asyncio_detailed(
            client=client,
            window_hours=window_hours,
            top_n=top_n,
        )
    ).parsed
