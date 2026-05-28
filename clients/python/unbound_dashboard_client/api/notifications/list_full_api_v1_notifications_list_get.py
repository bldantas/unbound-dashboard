from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.list_full_api_v1_notifications_list_get_response_list_full_api_v1_notifications_list_get import (
    ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    severity: None | str | Unset = UNSET,
    type_prefix: None | str | Unset = UNSET,
    resolved: bool | None | Unset = UNSET,
    dismissed: bool | None | Unset = UNSET,
    limit: int | Unset = 50,
    offset: int | Unset = 0,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_severity: None | str | Unset
    if isinstance(severity, Unset):
        json_severity = UNSET
    else:
        json_severity = severity
    params["severity"] = json_severity

    json_type_prefix: None | str | Unset
    if isinstance(type_prefix, Unset):
        json_type_prefix = UNSET
    else:
        json_type_prefix = type_prefix
    params["type_prefix"] = json_type_prefix

    json_resolved: bool | None | Unset
    if isinstance(resolved, Unset):
        json_resolved = UNSET
    else:
        json_resolved = resolved
    params["resolved"] = json_resolved

    json_dismissed: bool | None | Unset
    if isinstance(dismissed, Unset):
        json_dismissed = UNSET
    else:
        json_dismissed = dismissed
    params["dismissed"] = json_dismissed

    params["limit"] = limit

    params["offset"] = offset

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/notifications/list",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet | None:
    if response.status_code == 200:
        response_200 = ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet.from_dict(
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
) -> Response[HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    severity: None | str | Unset = UNSET,
    type_prefix: None | str | Unset = UNSET,
    resolved: bool | None | Unset = UNSET,
    dismissed: bool | None | Unset = UNSET,
    limit: int | Unset = 50,
    offset: int | Unset = 0,
) -> Response[HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet]:
    """List Full

     Feed completo pra página dedicada — com filtros e paginação.

    Args:
        severity (None | str | Unset):
        type_prefix (None | str | Unset):
        resolved (bool | None | Unset):
        dismissed (bool | None | Unset):
        limit (int | Unset):  Default: 50.
        offset (int | Unset):  Default: 0.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet]
    """

    kwargs = _get_kwargs(
        severity=severity,
        type_prefix=type_prefix,
        resolved=resolved,
        dismissed=dismissed,
        limit=limit,
        offset=offset,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    severity: None | str | Unset = UNSET,
    type_prefix: None | str | Unset = UNSET,
    resolved: bool | None | Unset = UNSET,
    dismissed: bool | None | Unset = UNSET,
    limit: int | Unset = 50,
    offset: int | Unset = 0,
) -> HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet | None:
    """List Full

     Feed completo pra página dedicada — com filtros e paginação.

    Args:
        severity (None | str | Unset):
        type_prefix (None | str | Unset):
        resolved (bool | None | Unset):
        dismissed (bool | None | Unset):
        limit (int | Unset):  Default: 50.
        offset (int | Unset):  Default: 0.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet
    """

    return sync_detailed(
        client=client,
        severity=severity,
        type_prefix=type_prefix,
        resolved=resolved,
        dismissed=dismissed,
        limit=limit,
        offset=offset,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    severity: None | str | Unset = UNSET,
    type_prefix: None | str | Unset = UNSET,
    resolved: bool | None | Unset = UNSET,
    dismissed: bool | None | Unset = UNSET,
    limit: int | Unset = 50,
    offset: int | Unset = 0,
) -> Response[HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet]:
    """List Full

     Feed completo pra página dedicada — com filtros e paginação.

    Args:
        severity (None | str | Unset):
        type_prefix (None | str | Unset):
        resolved (bool | None | Unset):
        dismissed (bool | None | Unset):
        limit (int | Unset):  Default: 50.
        offset (int | Unset):  Default: 0.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet]
    """

    kwargs = _get_kwargs(
        severity=severity,
        type_prefix=type_prefix,
        resolved=resolved,
        dismissed=dismissed,
        limit=limit,
        offset=offset,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    severity: None | str | Unset = UNSET,
    type_prefix: None | str | Unset = UNSET,
    resolved: bool | None | Unset = UNSET,
    dismissed: bool | None | Unset = UNSET,
    limit: int | Unset = 50,
    offset: int | Unset = 0,
) -> HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet | None:
    """List Full

     Feed completo pra página dedicada — com filtros e paginação.

    Args:
        severity (None | str | Unset):
        type_prefix (None | str | Unset):
        resolved (bool | None | Unset):
        dismissed (bool | None | Unset):
        limit (int | Unset):  Default: 50.
        offset (int | Unset):  Default: 0.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListFullApiV1NotificationsListGetResponseListFullApiV1NotificationsListGet
    """

    return (
        await asyncio_detailed(
            client=client,
            severity=severity,
            type_prefix=type_prefix,
            resolved=resolved,
            dismissed=dismissed,
            limit=limit,
            offset=offset,
        )
    ).parsed
