from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.revoke_user_api_v1_auth_revoke_user_id_post_response_revoke_user_api_v1_auth_revoke_user_id_post import (
    RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost,
)
from ...types import Response


def _get_kwargs(
    user_id: int,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/auth/revoke/{user_id}".format(
            user_id=quote(str(user_id), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost | None:
    if response.status_code == 200:
        response_200 = RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost.from_dict(
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
) -> Response[HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    user_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost]:
    """Revoke User

     Force-revoke todos os tokens emitidos pra `user_id` até este momento.

    Permissões:
      - Admin pode revogar qualquer user
      - User pode revogar a SI MESMO (auto-logout-everywhere)

    Args:
        user_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost]
    """

    kwargs = _get_kwargs(
        user_id=user_id,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    user_id: int,
    *,
    client: AuthenticatedClient,
) -> HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost | None:
    """Revoke User

     Force-revoke todos os tokens emitidos pra `user_id` até este momento.

    Permissões:
      - Admin pode revogar qualquer user
      - User pode revogar a SI MESMO (auto-logout-everywhere)

    Args:
        user_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost
    """

    return sync_detailed(
        user_id=user_id,
        client=client,
    ).parsed


async def asyncio_detailed(
    user_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost]:
    """Revoke User

     Force-revoke todos os tokens emitidos pra `user_id` até este momento.

    Permissões:
      - Admin pode revogar qualquer user
      - User pode revogar a SI MESMO (auto-logout-everywhere)

    Args:
        user_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost]
    """

    kwargs = _get_kwargs(
        user_id=user_id,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    user_id: int,
    *,
    client: AuthenticatedClient,
) -> HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost | None:
    """Revoke User

     Force-revoke todos os tokens emitidos pra `user_id` até este momento.

    Permissões:
      - Admin pode revogar qualquer user
      - User pode revogar a SI MESMO (auto-logout-everywhere)

    Args:
        user_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RevokeUserApiV1AuthRevokeUserIdPostResponseRevokeUserApiV1AuthRevokeUserIdPost
    """

    return (
        await asyncio_detailed(
            user_id=user_id,
            client=client,
        )
    ).parsed
