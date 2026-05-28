from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.get_thresholds_api_v1_alerts_thresholds_get_response_get_thresholds_api_v1_alerts_thresholds_get import (
    GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet,
)
from ...types import Response


def _get_kwargs() -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/alerts/thresholds",
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet | None:
    if response.status_code == 200:
        response_200 = GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet.from_dict(
            response.json()
        )

        return response_200

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet]:
    """Get Thresholds

     Retorna os 6 thresholds atuais + defaults. Aberto a qualquer user
    autenticado (a alerts.php precisa pra exibir os números nos cards de
    hardware mesmo pra viewer).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet]
    """

    kwargs = _get_kwargs()

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
) -> GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet | None:
    """Get Thresholds

     Retorna os 6 thresholds atuais + defaults. Aberto a qualquer user
    autenticado (a alerts.php precisa pra exibir os números nos cards de
    hardware mesmo pra viewer).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet
    """

    return sync_detailed(
        client=client,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
) -> Response[GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet]:
    """Get Thresholds

     Retorna os 6 thresholds atuais + defaults. Aberto a qualquer user
    autenticado (a alerts.php precisa pra exibir os números nos cards de
    hardware mesmo pra viewer).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet]
    """

    kwargs = _get_kwargs()

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
) -> GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet | None:
    """Get Thresholds

     Retorna os 6 thresholds atuais + defaults. Aberto a qualquer user
    autenticado (a alerts.php precisa pra exibir os números nos cards de
    hardware mesmo pra viewer).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        GetThresholdsApiV1AlertsThresholdsGetResponseGetThresholdsApiV1AlertsThresholdsGet
    """

    return (
        await asyncio_detailed(
            client=client,
        )
    ).parsed
