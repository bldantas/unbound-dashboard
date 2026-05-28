from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.bulk_add_exceptions_api_v1_blocklist_exceptions_bulk_post_body import (
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody,
)
from ...models.bulk_add_exceptions_api_v1_blocklist_exceptions_bulk_post_response_bulk_add_exceptions_api_v1_blocklist_exceptions_bulk_post import (
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    *,
    body: BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/blocklist/exceptions/bulk",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost.from_dict(
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
) -> Response[
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost
    | HTTPValidationError
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
    body: BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody,
) -> Response[
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost
    | HTTPValidationError
]:
    r"""Bulk Add Exceptions

     Bulk add. Body: `{\"domains\": [...], \"reason\": str?, \"org_id\": int?}`.
    Aceita até 50.000 domínios. Pula inválidos (sem ponto, vazio, com espaço) e
    duplicados (já na tabela ou repetidos no payload).

    Args:
        body (BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost | HTTPValidationError]
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
    body: BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody,
) -> (
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost
    | HTTPValidationError
    | None
):
    r"""Bulk Add Exceptions

     Bulk add. Body: `{\"domains\": [...], \"reason\": str?, \"org_id\": int?}`.
    Aceita até 50.000 domínios. Pula inválidos (sem ponto, vazio, com espaço) e
    duplicados (já na tabela ou repetidos no payload).

    Args:
        body (BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody,
) -> Response[
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost
    | HTTPValidationError
]:
    r"""Bulk Add Exceptions

     Bulk add. Body: `{\"domains\": [...], \"reason\": str?, \"org_id\": int?}`.
    Aceita até 50.000 domínios. Pula inválidos (sem ponto, vazio, com espaço) e
    duplicados (já na tabela ou repetidos no payload).

    Args:
        body (BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody,
) -> (
    BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost
    | HTTPValidationError
    | None
):
    r"""Bulk Add Exceptions

     Bulk add. Body: `{\"domains\": [...], \"reason\": str?, \"org_id\": int?}`.
    Aceita até 50.000 domínios. Pula inválidos (sem ponto, vazio, com espaço) e
    duplicados (já na tabela ou repetidos no payload).

    Args:
        body (BulkAddExceptionsApiV1BlocklistExceptionsBulkPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        BulkAddExceptionsApiV1BlocklistExceptionsBulkPostResponseBulkAddExceptionsApiV1BlocklistExceptionsBulkPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
