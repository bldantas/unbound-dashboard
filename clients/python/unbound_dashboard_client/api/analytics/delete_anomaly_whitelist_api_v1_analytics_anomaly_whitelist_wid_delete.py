from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.delete_anomaly_whitelist_api_v1_analytics_anomaly_whitelist_wid_delete_response_delete_anomaly_whitelist_api_v1_analytics_anomaly_whitelist_wid_delete import (
    DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    wid: int,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "delete",
        "url": "/api/v1/analytics/anomaly/whitelist/{wid}".format(
            wid=quote(str(wid), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete.from_dict(
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
    DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete
    | HTTPValidationError
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    wid: int,
    *,
    client: AuthenticatedClient,
) -> Response[
    DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete
    | HTTPValidationError
]:
    """Delete Anomaly Whitelist

    Args:
        wid (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        wid=wid,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    wid: int,
    *,
    client: AuthenticatedClient,
) -> (
    DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete
    | HTTPValidationError
    | None
):
    """Delete Anomaly Whitelist

    Args:
        wid (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete | HTTPValidationError
    """

    return sync_detailed(
        wid=wid,
        client=client,
    ).parsed


async def asyncio_detailed(
    wid: int,
    *,
    client: AuthenticatedClient,
) -> Response[
    DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete
    | HTTPValidationError
]:
    """Delete Anomaly Whitelist

    Args:
        wid (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        wid=wid,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    wid: int,
    *,
    client: AuthenticatedClient,
) -> (
    DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete
    | HTTPValidationError
    | None
):
    """Delete Anomaly Whitelist

    Args:
        wid (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        DeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDeleteResponseDeleteAnomalyWhitelistApiV1AnalyticsAnomalyWhitelistWidDelete | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            wid=wid,
            client=client,
        )
    ).parsed
