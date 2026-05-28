from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.add_exception_api_v1_blocklist_exceptions_post_body import AddExceptionApiV1BlocklistExceptionsPostBody
from ...models.add_exception_api_v1_blocklist_exceptions_post_response_add_exception_api_v1_blocklist_exceptions_post import (
    AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    *,
    body: AddExceptionApiV1BlocklistExceptionsPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/blocklist/exceptions",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost
    | HTTPValidationError
    | None
):
    if response.status_code == 201:
        response_201 = (
            AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost.from_dict(
                response.json()
            )
        )

        return response_201

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
    AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost | HTTPValidationError
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
    body: AddExceptionApiV1BlocklistExceptionsPostBody,
) -> Response[
    AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost | HTTPValidationError
]:
    r"""Add Exception

     Adiciona exceção. Body: {\"domain\": str, \"reason\": str?, \"org_id\": int?}.

    `org_id` opcional. Admin global default = 0 (allowlist global).
    User org-scoped sempre força a própria org (body.org_id é ignorado/checado).

    Args:
        body (AddExceptionApiV1BlocklistExceptionsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost | HTTPValidationError]
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
    body: AddExceptionApiV1BlocklistExceptionsPostBody,
) -> (
    AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost
    | HTTPValidationError
    | None
):
    r"""Add Exception

     Adiciona exceção. Body: {\"domain\": str, \"reason\": str?, \"org_id\": int?}.

    `org_id` opcional. Admin global default = 0 (allowlist global).
    User org-scoped sempre força a própria org (body.org_id é ignorado/checado).

    Args:
        body (AddExceptionApiV1BlocklistExceptionsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: AddExceptionApiV1BlocklistExceptionsPostBody,
) -> Response[
    AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost | HTTPValidationError
]:
    r"""Add Exception

     Adiciona exceção. Body: {\"domain\": str, \"reason\": str?, \"org_id\": int?}.

    `org_id` opcional. Admin global default = 0 (allowlist global).
    User org-scoped sempre força a própria org (body.org_id é ignorado/checado).

    Args:
        body (AddExceptionApiV1BlocklistExceptionsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: AddExceptionApiV1BlocklistExceptionsPostBody,
) -> (
    AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost
    | HTTPValidationError
    | None
):
    r"""Add Exception

     Adiciona exceção. Body: {\"domain\": str, \"reason\": str?, \"org_id\": int?}.

    `org_id` opcional. Admin global default = 0 (allowlist global).
    User org-scoped sempre força a própria org (body.org_id é ignorado/checado).

    Args:
        body (AddExceptionApiV1BlocklistExceptionsPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        AddExceptionApiV1BlocklistExceptionsPostResponseAddExceptionApiV1BlocklistExceptionsPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
