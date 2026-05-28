from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.probe_issuer_api_v1_auth_oidc_probe_post_body import ProbeIssuerApiV1AuthOidcProbePostBody
from ...models.probe_issuer_api_v1_auth_oidc_probe_post_response_probe_issuer_api_v1_auth_oidc_probe_post import (
    ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost,
)
from ...types import Response


def _get_kwargs(
    *,
    body: ProbeIssuerApiV1AuthOidcProbePostBody,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/auth/oidc/probe",
    }

    _kwargs["json"] = body.to_dict()

    headers["Content-Type"] = "application/json"

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost | None:
    if response.status_code == 200:
        response_200 = ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost.from_dict(
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
) -> Response[HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    body: ProbeIssuerApiV1AuthOidcProbePostBody,
) -> Response[HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost]:
    """Probe Issuer

     Valida um issuer URL: fetch do `.well-known/openid-configuration`
    + JWKS (best-effort). Retorna metadata descoberto pra UI mostrar e
    pra admin confirmar antes de salvar.

    Não persiste nada — só faz GETs HTTP. Não exige scopes/client_id.

    Args:
        body (ProbeIssuerApiV1AuthOidcProbePostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost]
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
    body: ProbeIssuerApiV1AuthOidcProbePostBody,
) -> HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost | None:
    """Probe Issuer

     Valida um issuer URL: fetch do `.well-known/openid-configuration`
    + JWKS (best-effort). Retorna metadata descoberto pra UI mostrar e
    pra admin confirmar antes de salvar.

    Não persiste nada — só faz GETs HTTP. Não exige scopes/client_id.

    Args:
        body (ProbeIssuerApiV1AuthOidcProbePostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost
    """

    return sync_detailed(
        client=client,
        body=body,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    body: ProbeIssuerApiV1AuthOidcProbePostBody,
) -> Response[HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost]:
    """Probe Issuer

     Valida um issuer URL: fetch do `.well-known/openid-configuration`
    + JWKS (best-effort). Retorna metadata descoberto pra UI mostrar e
    pra admin confirmar antes de salvar.

    Não persiste nada — só faz GETs HTTP. Não exige scopes/client_id.

    Args:
        body (ProbeIssuerApiV1AuthOidcProbePostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost]
    """

    kwargs = _get_kwargs(
        body=body,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    body: ProbeIssuerApiV1AuthOidcProbePostBody,
) -> HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost | None:
    """Probe Issuer

     Valida um issuer URL: fetch do `.well-known/openid-configuration`
    + JWKS (best-effort). Retorna metadata descoberto pra UI mostrar e
    pra admin confirmar antes de salvar.

    Não persiste nada — só faz GETs HTTP. Não exige scopes/client_id.

    Args:
        body (ProbeIssuerApiV1AuthOidcProbePostBody):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | ProbeIssuerApiV1AuthOidcProbePostResponseProbeIssuerApiV1AuthOidcProbePost
    """

    return (
        await asyncio_detailed(
            client=client,
            body=body,
        )
    ).parsed
