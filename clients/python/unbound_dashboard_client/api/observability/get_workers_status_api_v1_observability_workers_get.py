from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_workers_status_api_v1_observability_workers_get_response_get_workers_status_api_v1_observability_workers_get import (
    GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/observability/workers",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet | None:
    if response.status_code == 200:
        response_200 = (
            GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet.from_dict(
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
) -> Response[GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet]:
    """Get Workers Status

     Status agregado dos workers. Combina:
      - tasks vivas (do app.state via lifespan)
      - last_run conhecido (settings ou tabelas próprias)
      - próximas execuções estimadas (best-effort, baseado no tick)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet | None:
    """Get Workers Status

     Status agregado dos workers. Combina:
      - tasks vivas (do app.state via lifespan)
      - last_run conhecido (settings ou tabelas próprias)
      - próximas execuções estimadas (best-effort, baseado no tick)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet]:
    """Get Workers Status

     Status agregado dos workers. Combina:
      - tasks vivas (do app.state via lifespan)
      - last_run conhecido (settings ou tabelas próprias)
      - próximas execuções estimadas (best-effort, baseado no tick)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet | None:
    """Get Workers Status

     Status agregado dos workers. Combina:
      - tasks vivas (do app.state via lifespan)
      - last_run conhecido (settings ou tabelas próprias)
      - próximas execuções estimadas (best-effort, baseado no tick)

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetWorkersStatusApiV1ObservabilityWorkersGetResponseGetWorkersStatusApiV1ObservabilityWorkersGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
