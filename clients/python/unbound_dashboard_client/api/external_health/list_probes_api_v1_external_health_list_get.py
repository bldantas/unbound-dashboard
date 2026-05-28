from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.list_probes_api_v1_external_health_list_get_response_list_probes_api_v1_external_health_list_get import (
    ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    probe_source: None | str | Unset = UNSET,
    hours: int | Unset = 24,
    limit: int | Unset = 200,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_probe_source: None | str | Unset
    if isinstance(probe_source, Unset):
        json_probe_source = UNSET
    else:
        json_probe_source = probe_source
    params["probe_source"] = json_probe_source

    params["hours"] = hours

    params["limit"] = limit

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/external-health/list",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet | None:
    if response.status_code == 200:
        response_200 = ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet.from_dict(
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
) -> Response[HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    probe_source: None | str | Unset = UNSET,
    hours: int | Unset = 24,
    limit: int | Unset = 200,
) -> Response[HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet]:
    """List Probes

    Args:
        probe_source (None | str | Unset):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 200.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet]
    """

    kwargs = _get_kwargs(
        probe_source=probe_source,
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
    probe_source: None | str | Unset = UNSET,
    hours: int | Unset = 24,
    limit: int | Unset = 200,
) -> HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet | None:
    """List Probes

    Args:
        probe_source (None | str | Unset):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 200.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet
    """

    return sync_detailed(
        client=client,
        probe_source=probe_source,
        hours=hours,
        limit=limit,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    probe_source: None | str | Unset = UNSET,
    hours: int | Unset = 24,
    limit: int | Unset = 200,
) -> Response[HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet]:
    """List Probes

    Args:
        probe_source (None | str | Unset):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 200.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet]
    """

    kwargs = _get_kwargs(
        probe_source=probe_source,
        hours=hours,
        limit=limit,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    probe_source: None | str | Unset = UNSET,
    hours: int | Unset = 24,
    limit: int | Unset = 200,
) -> HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet | None:
    """List Probes

    Args:
        probe_source (None | str | Unset):
        hours (int | Unset):  Default: 24.
        limit (int | Unset):  Default: 200.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListProbesApiV1ExternalHealthListGetResponseListProbesApiV1ExternalHealthListGet
    """

    return (
        await asyncio_detailed(
            client=client,
            probe_source=probe_source,
            hours=hours,
            limit=limit,
        )
    ).parsed
