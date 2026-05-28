from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.approve_api_v1_approvals_request_id_approve_post_response_approve_api_v1_approvals_request_id_approve_post import (
    ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    request_id: int,
) -> dict[str, Any]:

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/approvals/{request_id}/approve".format(
            request_id=quote(str(request_id), safe=""),
        ),
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost
    | HTTPValidationError
    | None
):
    if response.status_code == 200:
        response_200 = (
            ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost.from_dict(
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
    ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost | HTTPValidationError
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
) -> Response[
    ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost | HTTPValidationError
]:
    """Approve

    Args:
        request_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        request_id=request_id,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    request_id: int,
    *,
    client: AuthenticatedClient,
) -> (
    ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost
    | HTTPValidationError
    | None
):
    """Approve

    Args:
        request_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost | HTTPValidationError
    """

    return sync_detailed(
        request_id=request_id,
        client=client,
    ).parsed


async def asyncio_detailed(
    request_id: int,
    *,
    client: AuthenticatedClient,
) -> Response[
    ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost | HTTPValidationError
]:
    """Approve

    Args:
        request_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        request_id=request_id,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    request_id: int,
    *,
    client: AuthenticatedClient,
) -> (
    ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost
    | HTTPValidationError
    | None
):
    """Approve

    Args:
        request_id (int):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ApproveApiV1ApprovalsRequestIdApprovePostResponseApproveApiV1ApprovalsRequestIdApprovePost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            request_id=request_id,
            client=client,
        )
    ).parsed
