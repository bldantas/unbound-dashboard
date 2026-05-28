from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.list_update_audit_api_v1_audit_updates_get_response_list_update_audit_api_v1_audit_updates_get import (
    ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    limit: int | Unset = 50,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["limit"] = limit

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/audit/updates",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet | None:
    if response.status_code == 200:
        response_200 = ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet.from_dict(
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
) -> Response[HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    limit: int | Unset = 50,
) -> Response[HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet]:
    """List Update Audit

     Histórico de updates/restores aplicados via UI.

    Args:
        limit (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet]
    """

    kwargs = _get_kwargs(
        limit=limit,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    limit: int | Unset = 50,
) -> HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet | None:
    """List Update Audit

     Histórico de updates/restores aplicados via UI.

    Args:
        limit (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet
    """

    return sync_detailed(
        client=client,
        limit=limit,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    limit: int | Unset = 50,
) -> Response[HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet]:
    """List Update Audit

     Histórico de updates/restores aplicados via UI.

    Args:
        limit (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet]
    """

    kwargs = _get_kwargs(
        limit=limit,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    limit: int | Unset = 50,
) -> HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet | None:
    """List Update Audit

     Histórico de updates/restores aplicados via UI.

    Args:
        limit (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListUpdateAuditApiV1AuditUpdatesGetResponseListUpdateAuditApiV1AuditUpdatesGet
    """

    return (
        await asyncio_detailed(
            client=client,
            limit=limit,
        )
    ).parsed
