from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.apply_config_api_v1_host_apply_config_post_body import ApplyConfigApiV1HostApplyConfigPostBody
from ...models.apply_config_api_v1_host_apply_config_post_response_apply_config_api_v1_host_apply_config_post import (
    ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost,
)
from ...models.http_validation_error import HTTPValidationError
from ...types import Response


def _get_kwargs(
    *,
    body: ApplyConfigApiV1HostApplyConfigPostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/host/apply-config",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost.from_dict(
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
) -> Response[ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: ApplyConfigApiV1HostApplyConfigPostBody,
) -> Response[ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError]:
    r"""Apply Config

     Recebe payload de config do master e aplica.

    Body shape:
        {
          \"blocklists\": [{slug, url, index_enabled, block_enabled}, ...],
          \"policies\":   [{slug, name, enabled, ranges, blocks, allows}, ...]
        }

    Retorno: counts por seção. Aplicação é aditiva (não remove o que
    não estiver no payload). Re-sync das blocklists e re-gen das views
    do Unbound não é disparada aqui — o agent tem seus workers/jobs
    próprios pra isso (BlocklistSyncer roda 1x/h).

    Args:
        body (ApplyConfigApiV1HostApplyConfigPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError]
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
    body: ApplyConfigApiV1HostApplyConfigPostBody,
) -> ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError | None:
    r"""Apply Config

     Recebe payload de config do master e aplica.

    Body shape:
        {
          \"blocklists\": [{slug, url, index_enabled, block_enabled}, ...],
          \"policies\":   [{slug, name, enabled, ranges, blocks, allows}, ...]
        }

    Retorno: counts por seção. Aplicação é aditiva (não remove o que
    não estiver no payload). Re-sync das blocklists e re-gen das views
    do Unbound não é disparada aqui — o agent tem seus workers/jobs
    próprios pra isso (BlocklistSyncer roda 1x/h).

    Args:
        body (ApplyConfigApiV1HostApplyConfigPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: ApplyConfigApiV1HostApplyConfigPostBody,
) -> Response[ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError]:
    r"""Apply Config

     Recebe payload de config do master e aplica.

    Body shape:
        {
          \"blocklists\": [{slug, url, index_enabled, block_enabled}, ...],
          \"policies\":   [{slug, name, enabled, ranges, blocks, allows}, ...]
        }

    Retorno: counts por seção. Aplicação é aditiva (não remove o que
    não estiver no payload). Re-sync das blocklists e re-gen das views
    do Unbound não é disparada aqui — o agent tem seus workers/jobs
    próprios pra isso (BlocklistSyncer roda 1x/h).

    Args:
        body (ApplyConfigApiV1HostApplyConfigPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: ApplyConfigApiV1HostApplyConfigPostBody,
) -> ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError | None:
    r"""Apply Config

     Recebe payload de config do master e aplica.

    Body shape:
        {
          \"blocklists\": [{slug, url, index_enabled, block_enabled}, ...],
          \"policies\":   [{slug, name, enabled, ranges, blocks, allows}, ...]
        }

    Retorno: counts por seção. Aplicação é aditiva (não remove o que
    não estiver no payload). Re-sync das blocklists e re-gen das views
    do Unbound não é disparada aqui — o agent tem seus workers/jobs
    próprios pra isso (BlocklistSyncer roda 1x/h).

    Args:
        body (ApplyConfigApiV1HostApplyConfigPostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        ApplyConfigApiV1HostApplyConfigPostResponseApplyConfigApiV1HostApplyConfigPost | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
