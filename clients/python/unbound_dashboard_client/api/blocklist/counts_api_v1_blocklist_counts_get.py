from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.counts_api_v1_blocklist_counts_get_response_counts_api_v1_blocklist_counts_get import (
    CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/blocklist/counts",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet | None:
    if response.status_code == 200:
        response_200 = CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet]:
    """Counts

     Retorna count por categoria (Malware/Adware, Phishing, Judicial).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet | None:
    """Counts

     Retorna count por categoria (Malware/Adware, Phishing, Judicial).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet]:
    """Counts

     Retorna count por categoria (Malware/Adware, Phishing, Judicial).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet | None:
    """Counts

     Retorna count por categoria (Malware/Adware, Phishing, Judicial).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CountsApiV1BlocklistCountsGetResponseCountsApiV1BlocklistCountsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
