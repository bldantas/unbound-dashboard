from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_ratelimit_api_v1_dns_security_ratelimit_settings_get_response_get_ratelimit_api_v1_dns_security_ratelimit_settings_get import (
    GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/dns-security/ratelimit/settings",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet | None:
    if response.status_code == 200:
        response_200 = GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet]:
    """Get Ratelimit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet | None:
    """Get Ratelimit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet]:
    """Get Ratelimit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet | None:
    """Get Ratelimit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetRatelimitApiV1DnsSecurityRatelimitSettingsGetResponseGetRatelimitApiV1DnsSecurityRatelimitSettingsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
