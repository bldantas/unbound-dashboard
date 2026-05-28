from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.report_api_v1_external_health_report_post_body import ReportApiV1ExternalHealthReportPostBody
from ...models.report_api_v1_external_health_report_post_response_report_api_v1_external_health_report_post import (
    ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost,
)
from ...types import Response


def _get_kwargs(
    *,
    body: ReportApiV1ExternalHealthReportPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/external-health/report",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost | None:
    if response.status_code == 200:
        response_200 = ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost.from_dict(
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
) -> Response[HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: ReportApiV1ExternalHealthReportPostBody,
) -> Response[HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost]:
    """Report

    Args:
        body (ReportApiV1ExternalHealthReportPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    body: ReportApiV1ExternalHealthReportPostBody,
) -> HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost | None:
    """Report

    Args:
        body (ReportApiV1ExternalHealthReportPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: ReportApiV1ExternalHealthReportPostBody,
) -> Response[HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost]:
    """Report

    Args:
        body (ReportApiV1ExternalHealthReportPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: ReportApiV1ExternalHealthReportPostBody,
) -> HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost | None:
    """Report

    Args:
        body (ReportApiV1ExternalHealthReportPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ReportApiV1ExternalHealthReportPostResponseReportApiV1ExternalHealthReportPost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
