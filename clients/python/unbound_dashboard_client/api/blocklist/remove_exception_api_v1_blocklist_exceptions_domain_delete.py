from http import HTTPStatus
from typing import Any
from urllib.parse import quote

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.remove_exception_api_v1_blocklist_exceptions_domain_delete_response_remove_exception_api_v1_blocklist_exceptions_domain_delete import (
    RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    domain: str,
    *,
    org_id: int | None | Unset = UNSET,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_org_id: int | None | Unset
    if isinstance(org_id, Unset):
        json_org_id = UNSET
    else:
        json_org_id = org_id
    params["org_id"] = json_org_id

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "delete",
        "url": "/api/v1/blocklist/exceptions/{domain}".format(
            domain=quote(str(domain), safe=""),
        ),
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> (
    HTTPValidationError
    | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
    | None
):
    if response.status_code == 200:
        response_200 = RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete.from_dict(
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
    | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    domain: str,
    *,
    client: AuthenticatedClient,
    org_id: int | None | Unset = UNSET,
) -> Response[
    HTTPValidationError
    | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
]:
    """Remove Exception

     Remove exceção. Sem org_id explícito, admin global apaga a global (0);
    user org-scoped apaga a da própria org.

    Args:
        domain (str):
        org_id (int | None | Unset): 0=global, N=org. Default = própria org do viewer ou 0 pra
            admin global.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete]
    """

    kwargs = _get_kwargs(
        domain=domain,
        org_id=org_id,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    domain: str,
    *,
    client: AuthenticatedClient,
    org_id: int | None | Unset = UNSET,
) -> (
    HTTPValidationError
    | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
    | None
):
    """Remove Exception

     Remove exceção. Sem org_id explícito, admin global apaga a global (0);
    user org-scoped apaga a da própria org.

    Args:
        domain (str):
        org_id (int | None | Unset): 0=global, N=org. Default = própria org do viewer ou 0 pra
            admin global.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
    """

    return sync_detailed(
        domain=domain,
        client=client,
        org_id=org_id,
    ).parsed


async def asyncio_detailed(
    domain: str,
    *,
    client: AuthenticatedClient,
    org_id: int | None | Unset = UNSET,
) -> Response[
    HTTPValidationError
    | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
]:
    """Remove Exception

     Remove exceção. Sem org_id explícito, admin global apaga a global (0);
    user org-scoped apaga a da própria org.

    Args:
        domain (str):
        org_id (int | None | Unset): 0=global, N=org. Default = própria org do viewer ou 0 pra
            admin global.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete]
    """

    kwargs = _get_kwargs(
        domain=domain,
        org_id=org_id,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    domain: str,
    *,
    client: AuthenticatedClient,
    org_id: int | None | Unset = UNSET,
) -> (
    HTTPValidationError
    | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
    | None
):
    """Remove Exception

     Remove exceção. Sem org_id explícito, admin global apaga a global (0);
    user org-scoped apaga a da própria org.

    Args:
        domain (str):
        org_id (int | None | Unset): 0=global, N=org. Default = própria org do viewer ou 0 pra
            admin global.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | RemoveExceptionApiV1BlocklistExceptionsDomainDeleteResponseRemoveExceptionApiV1BlocklistExceptionsDomainDelete
    """

    return (
        await asyncio_detailed(
            domain=domain,
            client=client,
            org_id=org_id,
        )
    ).parsed
