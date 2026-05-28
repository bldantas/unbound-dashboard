from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.list_tokens_api_v1_api_tokens_get_response_list_tokens_api_v1_api_tokens_get import (
    ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    include_revoked: bool | Unset = False,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["include_revoked"] = include_revoked

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/api-tokens",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet | None:
    if response.status_code == 200:
        response_200 = ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet.from_dict(response.json())

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
) -> Response[HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    include_revoked: bool | Unset = False,
) -> Response[HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet]:
    """List Tokens

    Args:
        include_revoked (bool | Unset):  Default: False.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet]
    """

    kwargs = _get_kwargs(
        include_revoked=include_revoked,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    include_revoked: bool | Unset = False,
) -> HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet | None:
    """List Tokens

    Args:
        include_revoked (bool | Unset):  Default: False.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet
    """

    return sync_detailed(
        client=client,
        include_revoked=include_revoked,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    include_revoked: bool | Unset = False,
) -> Response[HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet]:
    """List Tokens

    Args:
        include_revoked (bool | Unset):  Default: False.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet]
    """

    kwargs = _get_kwargs(
        include_revoked=include_revoked,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    include_revoked: bool | Unset = False,
) -> HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet | None:
    """List Tokens

    Args:
        include_revoked (bool | Unset):  Default: False.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ListTokensApiV1ApiTokensGetResponseListTokensApiV1ApiTokensGet
    """

    return (
        await asyncio_detailed(
            client=client,
            include_revoked=include_revoked,
        )
    ).parsed
