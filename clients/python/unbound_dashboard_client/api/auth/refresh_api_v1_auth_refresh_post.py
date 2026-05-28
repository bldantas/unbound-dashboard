from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.token_response import TokenResponse
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    authorization: None | str | Unset = UNSET,
) -> dict[str, Any]:
    headers: dict[str, Any] = {}
    if not isinstance(authorization, Unset):
        headers["authorization"] = authorization

    _kwargs: dict[str, Any] = {
        "method": "post",
        "url": "/api/v1/auth/refresh",
    }

    _kwargs["headers"] = headers
    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | TokenResponse | None:
    if response.status_code == 200:
        response_200 = TokenResponse.from_dict(response.json())

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
) -> Response[HTTPValidationError | TokenResponse]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient | Client,
    authorization: None | str | Unset = UNSET,
) -> Response[HTTPValidationError | TokenResponse]:
    """Refresh

     Renova o JWT do user. Aceita o JWT atual (ainda válido OU expirado
    nos últimos `_REFRESH_GRACE_MINUTES`) e retorna um novo com TTL
    completo. Útil pra sliding session — frontend chama proativamente
    quando o JWT está prestes a expirar.

    Segurança: como aceita JWT expirado por até N min, atacante que
    rouba JWT consegue renovar dentro dessa janela. Mantemos grace
    curto (10min) pra minimizar. Revogação real precisa de denylist
    em Redis (fora de escopo aqui).

    Validações:
      - Conta ainda existe + ativa (não-bloqueada). Se admin desativar
        o user, o JWT velho não consegue renovar mais.

    Args:
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | TokenResponse]
    """

    kwargs = _get_kwargs(
        authorization=authorization,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient | Client,
    authorization: None | str | Unset = UNSET,
) -> HTTPValidationError | TokenResponse | None:
    """Refresh

     Renova o JWT do user. Aceita o JWT atual (ainda válido OU expirado
    nos últimos `_REFRESH_GRACE_MINUTES`) e retorna um novo com TTL
    completo. Útil pra sliding session — frontend chama proativamente
    quando o JWT está prestes a expirar.

    Segurança: como aceita JWT expirado por até N min, atacante que
    rouba JWT consegue renovar dentro dessa janela. Mantemos grace
    curto (10min) pra minimizar. Revogação real precisa de denylist
    em Redis (fora de escopo aqui).

    Validações:
      - Conta ainda existe + ativa (não-bloqueada). Se admin desativar
        o user, o JWT velho não consegue renovar mais.

    Args:
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | TokenResponse
    """

    return sync_detailed(
        client=client,
        authorization=authorization,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient | Client,
    authorization: None | str | Unset = UNSET,
) -> Response[HTTPValidationError | TokenResponse]:
    """Refresh

     Renova o JWT do user. Aceita o JWT atual (ainda válido OU expirado
    nos últimos `_REFRESH_GRACE_MINUTES`) e retorna um novo com TTL
    completo. Útil pra sliding session — frontend chama proativamente
    quando o JWT está prestes a expirar.

    Segurança: como aceita JWT expirado por até N min, atacante que
    rouba JWT consegue renovar dentro dessa janela. Mantemos grace
    curto (10min) pra minimizar. Revogação real precisa de denylist
    em Redis (fora de escopo aqui).

    Validações:
      - Conta ainda existe + ativa (não-bloqueada). Se admin desativar
        o user, o JWT velho não consegue renovar mais.

    Args:
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | TokenResponse]
    """

    kwargs = _get_kwargs(
        authorization=authorization,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient | Client,
    authorization: None | str | Unset = UNSET,
) -> HTTPValidationError | TokenResponse | None:
    """Refresh

     Renova o JWT do user. Aceita o JWT atual (ainda válido OU expirado
    nos últimos `_REFRESH_GRACE_MINUTES`) e retorna um novo com TTL
    completo. Útil pra sliding session — frontend chama proativamente
    quando o JWT está prestes a expirar.

    Segurança: como aceita JWT expirado por até N min, atacante que
    rouba JWT consegue renovar dentro dessa janela. Mantemos grace
    curto (10min) pra minimizar. Revogação real precisa de denylist
    em Redis (fora de escopo aqui).

    Validações:
      - Conta ainda existe + ativa (não-bloqueada). Se admin desativar
        o user, o JWT velho não consegue renovar mais.

    Args:
        authorization (None | str | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | TokenResponse
    """

    return (
        await asyncio_detailed(
            client=client,
            authorization=authorization,
        )
    ).parsed
