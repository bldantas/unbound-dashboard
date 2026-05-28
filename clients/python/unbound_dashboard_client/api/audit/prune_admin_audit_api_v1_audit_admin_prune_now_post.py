from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.prune_admin_audit_api_v1_audit_admin_prune_now_post_response_prune_admin_audit_api_v1_audit_admin_prune_now_post import (
    PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/audit/admin/prune-now",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost | None:
    if response.status_code == 200:
        response_200 = (
            PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost.from_dict(
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
) -> Response[PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost]:
    """Prune Admin Audit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost | None:
    """Prune Admin Audit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost]:
    """Prune Admin Audit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost | None:
    """Prune Admin Audit

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        PruneAdminAuditApiV1AuditAdminPruneNowPostResponsePruneAdminAuditApiV1AuditAdminPruneNowPost
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
