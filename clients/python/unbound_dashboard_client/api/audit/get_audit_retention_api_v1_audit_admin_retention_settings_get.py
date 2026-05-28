from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_audit_retention_api_v1_audit_admin_retention_settings_get_response_get_audit_retention_api_v1_audit_admin_retention_settings_get import (
    GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/audit/admin/retention/settings",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
    | None
):
    if response.status_code == 200:
        response_200 = GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[
    GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
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
) -> Response[
    GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
]:
    """Get Audit Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> (
    GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
    | None
):
    """Get Audit Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[
    GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
]:
    """Get Audit Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> (
    GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
    | None
):
    """Get Audit Retention

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetAuditRetentionApiV1AuditAdminRetentionSettingsGetResponseGetAuditRetentionApiV1AuditAdminRetentionSettingsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
