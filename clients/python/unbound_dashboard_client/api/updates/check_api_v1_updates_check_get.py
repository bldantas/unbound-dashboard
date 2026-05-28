from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.check_api_v1_updates_check_get_response_check_api_v1_updates_check_get import (
    CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/updates/check",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet | None:
    if response.status_code == 200:
        response_200 = CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet.from_dict(response.json())

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet]:
    """Check

     Consulta GitHub Releases pela última versão publicada. Resposta
    sempre 200 — se GitHub off, retorna {error: ...} e has_update=false.
    Cache de 5min em Redis pra não bater GitHub a cada refresh do UI.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet | None:
    """Check

     Consulta GitHub Releases pela última versão publicada. Resposta
    sempre 200 — se GitHub off, retorna {error: ...} e has_update=false.
    Cache de 5min em Redis pra não bater GitHub a cada refresh do UI.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet]:
    """Check

     Consulta GitHub Releases pela última versão publicada. Resposta
    sempre 200 — se GitHub off, retorna {error: ...} e has_update=false.
    Cache de 5min em Redis pra não bater GitHub a cada refresh do UI.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet | None:
    """Check

     Consulta GitHub Releases pela última versão publicada. Resposta
    sempre 200 — se GitHub off, retorna {error: ...} e has_update=false.
    Cache de 5min em Redis pra não bater GitHub a cada refresh do UI.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        CheckApiV1UpdatesCheckGetResponseCheckApiV1UpdatesCheckGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
