from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.apply_api_v1_updates_apply_post_response_apply_api_v1_updates_apply_post import (
    ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost,
)
from ...models.apply_request import ApplyRequest
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    *,
    body: ApplyRequest,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/updates/apply",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError | None:
    if response.status_code == 202:
        response_202 = ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost.from_dict(response.json())

        return response_202

    if response.status_code == 422:
        response_422 = HTTPValidationError.from_dict(response.json())

        return response_422

    if client.raise_on_unexpected_status:
        raise errors.UnexpectedStatus(response.status_code, response.content)
    else:
        return None


def _build_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Response[ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: ApplyRequest,
) -> Response[ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError]:
    """Apply

     Dispara o update. Não bloqueia — retorna job_id imediato pra cliente
    pollear `/status/{job_id}` ou abrir SSE em `/log/{job_id}`.

    Pipeline completo em `services/updater.apply_update`:
      - lock global (Redis)
      - refresh release do GitHub (anti-replay)
      - download + verifica SHA256
      - spawn `sudo bash update.sh <tar>` detachado
      - registra job em Redis + audit trail no DuckDB

    Args:
        body (ApplyRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError]
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
    body: ApplyRequest,
) -> ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError | None:
    """Apply

     Dispara o update. Não bloqueia — retorna job_id imediato pra cliente
    pollear `/status/{job_id}` ou abrir SSE em `/log/{job_id}`.

    Pipeline completo em `services/updater.apply_update`:
      - lock global (Redis)
      - refresh release do GitHub (anti-replay)
      - download + verifica SHA256
      - spawn `sudo bash update.sh <tar>` detachado
      - registra job em Redis + audit trail no DuckDB

    Args:
        body (ApplyRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: ApplyRequest,
) -> Response[ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError]:
    """Apply

     Dispara o update. Não bloqueia — retorna job_id imediato pra cliente
    pollear `/status/{job_id}` ou abrir SSE em `/log/{job_id}`.

    Pipeline completo em `services/updater.apply_update`:
      - lock global (Redis)
      - refresh release do GitHub (anti-replay)
      - download + verifica SHA256
      - spawn `sudo bash update.sh <tar>` detachado
      - registra job em Redis + audit trail no DuckDB

    Args:
        body (ApplyRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: ApplyRequest,
) -> ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError | None:
    """Apply

     Dispara o update. Não bloqueia — retorna job_id imediato pra cliente
    pollear `/status/{job_id}` ou abrir SSE em `/log/{job_id}`.

    Pipeline completo em `services/updater.apply_update`:
      - lock global (Redis)
      - refresh release do GitHub (anti-replay)
      - download + verifica SHA256
      - spawn `sudo bash update.sh <tar>` detachado
      - registra job em Redis + audit trail no DuckDB

    Args:
        body (ApplyRequest):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ApplyApiV1UpdatesApplyPostResponseApplyApiV1UpdatesApplyPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
