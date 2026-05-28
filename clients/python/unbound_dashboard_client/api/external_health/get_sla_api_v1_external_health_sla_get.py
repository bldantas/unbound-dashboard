from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_sla_api_v1_external_health_sla_get_response_get_sla_api_v1_external_health_sla_get import (
    GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    hours: int | Unset = 24,
    probe_source: None | str | Unset = UNSET,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["hours"] = hours

    json_probe_source: None | str | Unset
    if isinstance(probe_source, Unset):
        json_probe_source = UNSET
    else:
        json_probe_source = probe_source
    params["probe_source"] = json_probe_source

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/external-health/sla",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet.from_dict(response.json())

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
) -> Response[GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    probe_source: None | str | Unset = UNSET,
) -> Response[GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError]:
    """Get Sla

    Args:
        hours (int | Unset):  Default: 24.
        probe_source (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        hours=hours,
        probe_source=probe_source,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    probe_source: None | str | Unset = UNSET,
) -> GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError | None:
    """Get Sla

    Args:
        hours (int | Unset):  Default: 24.
        probe_source (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        hours=hours,
        probe_source=probe_source,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    probe_source: None | str | Unset = UNSET,
) -> Response[GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError]:
    """Get Sla

    Args:
        hours (int | Unset):  Default: 24.
        probe_source (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        hours=hours,
        probe_source=probe_source,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    hours: int | Unset = 24,
    probe_source: None | str | Unset = UNSET,
) -> GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError | None:
    """Get Sla

    Args:
        hours (int | Unset):  Default: 24.
        probe_source (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetSlaApiV1ExternalHealthSlaGetResponseGetSlaApiV1ExternalHealthSlaGet | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            hours=hours,
            probe_source=probe_source,
        )
    ).parsed
