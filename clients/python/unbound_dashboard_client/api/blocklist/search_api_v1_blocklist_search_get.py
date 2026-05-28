from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...models.search_api_v1_blocklist_search_get_category_type_0 import SearchApiV1BlocklistSearchGetCategoryType0
from ...models.search_api_v1_blocklist_search_get_response_search_api_v1_blocklist_search_get import (
    SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet,
)
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    q: str | Unset = "",
    category: None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset = UNSET,
    tld: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    params["q"] = q

    json_category: None | str | Unset
    if isinstance(category, Unset):
        json_category = UNSET
    elif isinstance(category, SearchApiV1BlocklistSearchGetCategoryType0):
        json_category = category.value
    else:
        json_category = category
    params["category"] = json_category

    params["tld"] = tld

    params["page"] = page

    params["per_page"] = per_page

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/blocklist/search",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet | None:
    if response.status_code == 200:
        response_200 = SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet.from_dict(response.json())

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
) -> Response[HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    q: str | Unset = "",
    category: None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset = UNSET,
    tld: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> Response[HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet]:
    """Search

     Busca paginada em `blocklist_domains` (DuckDB). Substitui o antigo
    `api/blocklist_search.php`, que lia o arquivo flat e só via ANATEL.

    Retorna estrutura compatível com o JS atual de `/blocklist.php`:
    `{success, total, filtered, page, per_page, total_pages, domains, top_tlds, by_category}`.

    Args:
        q (str | Unset): Termo a buscar em domain (LIKE %q%) Default: ''.
        category (None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset): Filtra por
            categoria; ausente = todas
        tld (str | Unset): Filtra por TLD (sufixo após o último ponto) Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet]
    """

    kwargs = _get_kwargs(
        q=q,
        category=category,
        tld=tld,
        page=page,
        per_page=per_page,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    q: str | Unset = "",
    category: None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset = UNSET,
    tld: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet | None:
    """Search

     Busca paginada em `blocklist_domains` (DuckDB). Substitui o antigo
    `api/blocklist_search.php`, que lia o arquivo flat e só via ANATEL.

    Retorna estrutura compatível com o JS atual de `/blocklist.php`:
    `{success, total, filtered, page, per_page, total_pages, domains, top_tlds, by_category}`.

    Args:
        q (str | Unset): Termo a buscar em domain (LIKE %q%) Default: ''.
        category (None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset): Filtra por
            categoria; ausente = todas
        tld (str | Unset): Filtra por TLD (sufixo após o último ponto) Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet
    """

    return sync_detailed(
        client=client,
        q=q,
        category=category,
        tld=tld,
        page=page,
        per_page=per_page,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    q: str | Unset = "",
    category: None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset = UNSET,
    tld: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> Response[HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet]:
    """Search

     Busca paginada em `blocklist_domains` (DuckDB). Substitui o antigo
    `api/blocklist_search.php`, que lia o arquivo flat e só via ANATEL.

    Retorna estrutura compatível com o JS atual de `/blocklist.php`:
    `{success, total, filtered, page, per_page, total_pages, domains, top_tlds, by_category}`.

    Args:
        q (str | Unset): Termo a buscar em domain (LIKE %q%) Default: ''.
        category (None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset): Filtra por
            categoria; ausente = todas
        tld (str | Unset): Filtra por TLD (sufixo após o último ponto) Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet]
    """

    kwargs = _get_kwargs(
        q=q,
        category=category,
        tld=tld,
        page=page,
        per_page=per_page,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    q: str | Unset = "",
    category: None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset = UNSET,
    tld: str | Unset = "",
    page: int | Unset = 1,
    per_page: int | Unset = 50,
) -> HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet | None:
    """Search

     Busca paginada em `blocklist_domains` (DuckDB). Substitui o antigo
    `api/blocklist_search.php`, que lia o arquivo flat e só via ANATEL.

    Retorna estrutura compatível com o JS atual de `/blocklist.php`:
    `{success, total, filtered, page, per_page, total_pages, domains, top_tlds, by_category}`.

    Args:
        q (str | Unset): Termo a buscar em domain (LIKE %q%) Default: ''.
        category (None | SearchApiV1BlocklistSearchGetCategoryType0 | Unset): Filtra por
            categoria; ausente = todas
        tld (str | Unset): Filtra por TLD (sufixo após o último ponto) Default: ''.
        page (int | Unset):  Default: 1.
        per_page (int | Unset):  Default: 50.

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        HTTPValidationError | SearchApiV1BlocklistSearchGetResponseSearchApiV1BlocklistSearchGet
    """

    return (
        await asyncio_detailed(
            client=client,
            q=q,
            category=category,
            tld=tld,
            page=page,
            per_page=per_page,
        )
    ).parsed
