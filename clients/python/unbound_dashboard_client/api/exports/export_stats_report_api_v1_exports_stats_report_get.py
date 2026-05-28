from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.export_stats_report_api_v1_exports_stats_report_get_response_export_stats_report_api_v1_exports_stats_report_get import (
    ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/exports/stats-report",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet | None:
    if response.status_code == 200:
        response_200 = (
            ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet.from_dict(
                response.json()
            )
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet]:
    """Export Stats Report

     Sumário pra o JSON de stats: daily_history (90d) + top_domains_24h +
    top_clients_24h. NÃO inclui current_metrics (PHP lê data/latest_stats.json).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet | None:
    """Export Stats Report

     Sumário pra o JSON de stats: daily_history (90d) + top_domains_24h +
    top_clients_24h. NÃO inclui current_metrics (PHP lê data/latest_stats.json).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet]:
    """Export Stats Report

     Sumário pra o JSON de stats: daily_history (90d) + top_domains_24h +
    top_clients_24h. NÃO inclui current_metrics (PHP lê data/latest_stats.json).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet | None:
    """Export Stats Report

     Sumário pra o JSON de stats: daily_history (90d) + top_domains_24h +
    top_clients_24h. NÃO inclui current_metrics (PHP lê data/latest_stats.json).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ExportStatsReportApiV1ExportsStatsReportGetResponseExportStatsReportApiV1ExportsStatsReportGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
