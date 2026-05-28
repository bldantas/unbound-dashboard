from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.thresholds_update_request import ThresholdsUpdateRequest
from ...models.update_thresholds_api_v1_alerts_thresholds_put_response_update_thresholds_api_v1_alerts_thresholds_put import (
    UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut,
)
from ...types import Response


def _get_kwargs(
    *,
    body: ThresholdsUpdateRequest,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "put",
        "url": "/api/v1/alerts/thresholds",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
    | None
):
    if response.status_code == 200:
        response_200 = (
            UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut.from_dict(
                response.json()
            )
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
) -> Response[
    HTTPValidationError | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: ThresholdsUpdateRequest,
) -> Response[
    HTTPValidationError | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
]:
    """Update Thresholds

     UPSERT dos thresholds editáveis. Aceita parcial — só campos
    presentes no body são gravados. Retorna o estado final atualizado.

    Args:
        body (ThresholdsUpdateRequest): Aceita apenas os 6 thresholds conhecidos. Valores precisam
            ser >= 0.
            Campos omitidos não são alterados (PATCH-style).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    body: ThresholdsUpdateRequest,
) -> (
    HTTPValidationError
    | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
    | None
):
    """Update Thresholds

     UPSERT dos thresholds editáveis. Aceita parcial — só campos
    presentes no body são gravados. Retorna o estado final atualizado.

    Args:
        body (ThresholdsUpdateRequest): Aceita apenas os 6 thresholds conhecidos. Valores precisam
            ser >= 0.
            Campos omitidos não são alterados (PATCH-style).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: ThresholdsUpdateRequest,
) -> Response[
    HTTPValidationError | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
]:
    """Update Thresholds

     UPSERT dos thresholds editáveis. Aceita parcial — só campos
    presentes no body são gravados. Retorna o estado final atualizado.

    Args:
        body (ThresholdsUpdateRequest): Aceita apenas os 6 thresholds conhecidos. Valores precisam
            ser >= 0.
            Campos omitidos não são alterados (PATCH-style).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: ThresholdsUpdateRequest,
) -> (
    HTTPValidationError
    | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
    | None
):
    """Update Thresholds

     UPSERT dos thresholds editáveis. Aceita parcial — só campos
    presentes no body são gravados. Retorna o estado final atualizado.

    Args:
        body (ThresholdsUpdateRequest): Aceita apenas os 6 thresholds conhecidos. Valores precisam
            ser >= 0.
            Campos omitidos não são alterados (PATCH-style).

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | UpdateThresholdsApiV1AlertsThresholdsPutResponseUpdateThresholdsApiV1AlertsThresholdsPut
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
