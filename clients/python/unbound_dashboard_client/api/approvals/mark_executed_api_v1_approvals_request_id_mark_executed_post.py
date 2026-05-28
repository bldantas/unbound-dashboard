from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.mark_executed_api_v1_approvals_request_id_mark_executed_post_body import (
    MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody,
)
from ...models.mark_executed_api_v1_approvals_request_id_mark_executed_post_response_mark_executed_api_v1_approvals_request_id_mark_executed_post import (
    MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost,
)
from ...types import Response


def _get_kwargs(
    request_id: int,
    *,
    body: MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/approvals/{request_id}/mark-executed".format(
            request_id=quote(str(request_id), safe=""),
        ),
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
    | None
):
    if response.status_code == 200:
        response_200 = MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost.from_dict(
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
    HTTPValidationError
    | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    request_id: int,
    *,
    client: AuthenticatedClient,
    body: MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody,
) -> Response[
    HTTPValidationError
    | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
]:
    """Mark Executed

    Args:
        request_id (int):
        body (MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost]
    """

    kwargs = _get_kwargs(
        request_id=request_id,
        body=body,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    request_id: int,
    *,
    client: AuthenticatedClient,
    body: MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody,
) -> (
    HTTPValidationError
    | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
    | None
):
    """Mark Executed

    Args:
        request_id (int):
        body (MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
    """

    return sync_detailed(
        request_id=request_id,
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    request_id: int,
    *,
    client: AuthenticatedClient,
    body: MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody,
) -> Response[
    HTTPValidationError
    | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
]:
    """Mark Executed

    Args:
        request_id (int):
        body (MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost]
    """

    kwargs = _get_kwargs(
        request_id=request_id,
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    request_id: int,
    *,
    client: AuthenticatedClient,
    body: MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody,
) -> (
    HTTPValidationError
    | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
    | None
):
    """Mark Executed

    Args:
        request_id (int):
        body (MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | MarkExecutedApiV1ApprovalsRequestIdMarkExecutedPostResponseMarkExecutedApiV1ApprovalsRequestIdMarkExecutedPost
    """

    return (
        await asyncio_detailed(
            request_id=request_id,
            client=client,
            body=body,
        )
    ).parsed
