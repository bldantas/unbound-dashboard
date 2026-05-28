from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_exceptions_api_v1_blocklist_exceptions_get_response_list_exceptions_api_v1_blocklist_exceptions_get import (
    ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/blocklist/exceptions",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet | None:
    if response.status_code == 200:
        response_200 = (
            ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet.from_dict(
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
) -> Response[ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet]:
    """List Exceptions

     Lista exceções visíveis pro viewer (globais + da própria org).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet | None:
    """List Exceptions

     Lista exceções visíveis pro viewer (globais + da própria org).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet]:
    """List Exceptions

     Lista exceções visíveis pro viewer (globais + da própria org).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet | None:
    """List Exceptions

     Lista exceções visíveis pro viewer (globais + da própria org).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListExceptionsApiV1BlocklistExceptionsGetResponseListExceptionsApiV1BlocklistExceptionsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
