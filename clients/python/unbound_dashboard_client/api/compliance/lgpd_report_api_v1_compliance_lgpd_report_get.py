from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.lgpd_report_api_v1_compliance_lgpd_report_get_response_lgpd_report_api_v1_compliance_lgpd_report_get import (
    LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    client_ip: str,
    hours: int | Unset = 24,
    limit: int | Unset = 5000,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["client_ip"] = client_ip

    params["hours"] = hours

    params["limit"] = limit

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/compliance/lgpd-report",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet | None:
    if response.status_code == 200:
        response_200 = LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet.from_dict(
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
    HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    client_ip: str,
    hours: int | Unset = 24,
    limit: int | Unset = 5000,
) -> Response[
    HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet
]:
    """Lgpd Report

     Dump JSON das queries do client_ip nas últimas N horas.

    Args:
        client_ip (str):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 5000.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet]
    """

    kwargs = _get_kwargs(
        client_ip=client_ip,
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
    client_ip: str,
    hours: int | Unset = 24,
    limit: int | Unset = 5000,
) -> HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet | None:
    """Lgpd Report

     Dump JSON das queries do client_ip nas últimas N horas.

    Args:
        client_ip (str):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 5000.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet
    """

    return sync_detailed(
        client=client,
        client_ip=client_ip,
        hours=hours,
        limit=limit,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    client_ip: str,
    hours: int | Unset = 24,
    limit: int | Unset = 5000,
) -> Response[
    HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet
]:
    """Lgpd Report

     Dump JSON das queries do client_ip nas últimas N horas.

    Args:
        client_ip (str):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 5000.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet]
    """

    kwargs = _get_kwargs(
        client_ip=client_ip,
        hours=hours,
        limit=limit,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    client_ip: str,
    hours: int | Unset = 24,
    limit: int | Unset = 5000,
) -> HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet | None:
    """Lgpd Report

     Dump JSON das queries do client_ip nas últimas N horas.

    Args:
        client_ip (str):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 5000.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | LgpdReportApiV1ComplianceLgpdReportGetResponseLgpdReportApiV1ComplianceLgpdReportGet
    """

    return (
        await asyncio_detailed(
            client=client,
            client_ip=client_ip,
            hours=hours,
            limit=limit,
        )
    ).parsed
