from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.list_full_enabled_api_v1_policies_full_enabled_get_response_list_full_enabled_api_v1_policies_full_enabled_get import (
    ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/policies/full-enabled",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet | None:
    if response.status_code == 200:
        response_200 = (
            ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet.from_dict(
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
) -> Response[ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet]:
    """List Full Enabled

     Lista enabled+ranges+blocks+allows. Consumida pelo PHP pra gerar views.conf.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet | None:
    """List Full Enabled

     Lista enabled+ranges+blocks+allows. Consumida pelo PHP pra gerar views.conf.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet]:
    """List Full Enabled

     Lista enabled+ranges+blocks+allows. Consumida pelo PHP pra gerar views.conf.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet | None:
    """List Full Enabled

     Lista enabled+ranges+blocks+allows. Consumida pelo PHP pra gerar views.conf.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ListFullEnabledApiV1PoliciesFullEnabledGetResponseListFullEnabledApiV1PoliciesFullEnabledGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
