from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.import_settings_bulk_api_v1_exports_settings_bulk_post_body_item import (
    ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem,
)
from ...models.import_settings_bulk_api_v1_exports_settings_bulk_post_response_import_settings_bulk_api_v1_exports_settings_bulk_post import (
    ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost,
)
from ...types import Response


def _get_kwargs(
    *,
    body: list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem],
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/exports/settings/bulk",
    }

    _kwargs["json"] = []
    for body_item_data in body:
        body_item = body_item_data.to_dict()
        _kwargs["json"].append(body_item)

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
    | None
):
    if response.status_code == 200:
        response_200 = ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost.from_dict(
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
    | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
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
    body: list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem],
) -> Response[
    HTTPValidationError
    | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
]:
    """Import Settings Bulk

     Bulk upsert de settings — usado pelo restore de config backup.

    Args:
        body (list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost]
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
    body: list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem],
) -> (
    HTTPValidationError
    | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
    | None
):
    """Import Settings Bulk

     Bulk upsert de settings — usado pelo restore de config backup.

    Args:
        body (list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem],
) -> Response[
    HTTPValidationError
    | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
]:
    """Import Settings Bulk

     Bulk upsert de settings — usado pelo restore de config backup.

    Args:
        body (list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem],
) -> (
    HTTPValidationError
    | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
    | None
):
    """Import Settings Bulk

     Bulk upsert de settings — usado pelo restore de config backup.

    Args:
        body (list[ImportSettingsBulkApiV1ExportsSettingsBulkPostBodyItem]):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ImportSettingsBulkApiV1ExportsSettingsBulkPostResponseImportSettingsBulkApiV1ExportsSettingsBulkPost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
