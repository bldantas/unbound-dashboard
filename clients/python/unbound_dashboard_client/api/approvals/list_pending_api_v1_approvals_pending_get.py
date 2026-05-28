from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_pending_api_v1_approvals_pending_get_response_list_pending_api_v1_approvals_pending_get import (
    ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/approvals/pending",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet | None:
    if response.status_code == 200:
        response_200 = ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet]:
    """List Pending

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet | None:
    """List Pending

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet]:
    """List Pending

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet | None:
    """List Pending

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListPendingApiV1ApprovalsPendingGetResponseListPendingApiV1ApprovalsPendingGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
