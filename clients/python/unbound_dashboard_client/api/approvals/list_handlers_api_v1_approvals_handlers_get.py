from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_handlers_api_v1_approvals_handlers_get_response_list_handlers_api_v1_approvals_handlers_get import (
    ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/approvals/handlers",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet | None:
    if response.status_code == 200:
        response_200 = ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet]:
    """List Handlers

     Quais actions têm handler dispatchável automaticamente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet | None:
    """List Handlers

     Quais actions têm handler dispatchável automaticamente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet]:
    """List Handlers

     Quais actions têm handler dispatchável automaticamente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet | None:
    """List Handlers

     Quais actions têm handler dispatchável automaticamente.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListHandlersApiV1ApprovalsHandlersGetResponseListHandlersApiV1ApprovalsHandlersGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
