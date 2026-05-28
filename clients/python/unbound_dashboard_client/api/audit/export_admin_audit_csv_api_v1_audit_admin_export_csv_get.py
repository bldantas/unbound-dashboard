from http import HTTPStatus
from typing import Any

import httpx

from ... import errors
from ...client import AuthenticatedClient, Client
from ...models.http_validation_error import HTTPValidationError
from ...types import UNSET, Response, Unset


def _get_kwargs(
    *,
    category: None | str | Unset = UNSET,
    actor_id: int | None | Unset = UNSET,
    action_prefix: None | str | Unset = UNSET,
    from_ts: int | None | Unset = UNSET,
    to_ts: int | None | Unset = UNSET,
) -> dict[str, Any]:

    params: dict[str, Any] = {}

    json_category: None | str | Unset
    if isinstance(category, Unset):
        json_category = UNSET
    else:
        json_category = category
    params["category"] = json_category

    json_actor_id: int | None | Unset
    if isinstance(actor_id, Unset):
        json_actor_id = UNSET
    else:
        json_actor_id = actor_id
    params["actor_id"] = json_actor_id

    json_action_prefix: None | str | Unset
    if isinstance(action_prefix, Unset):
        json_action_prefix = UNSET
    else:
        json_action_prefix = action_prefix
    params["action_prefix"] = json_action_prefix

    json_from_ts: int | None | Unset
    if isinstance(from_ts, Unset):
        json_from_ts = UNSET
    else:
        json_from_ts = from_ts
    params["from_ts"] = json_from_ts

    json_to_ts: int | None | Unset
    if isinstance(to_ts, Unset):
        json_to_ts = UNSET
    else:
        json_to_ts = to_ts
    params["to_ts"] = json_to_ts

    params = {k: v for k, v in params.items() if v is not UNSET and v is not None}

    _kwargs: dict[str, Any] = {
        "method": "get",
        "url": "/api/v1/audit/admin/export-csv",
        "params": params,
    }

    return _kwargs


def _parse_response(
    *, client: AuthenticatedClient | Client, response: httpx.Response
) -> Any | HTTPValidationError | None:
    if response.status_code == 200:
        response_200 = response.json()
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
) -> Response[Any | HTTPValidationError]:
    return Response(
        status_code=HTTPStatus(response.status_code),
        content=response.content,
        headers=response.headers,
        parsed=_parse_response(client=client, response=response),
    )


def sync_detailed(
    *,
    client: AuthenticatedClient,
    category: None | str | Unset = UNSET,
    actor_id: int | None | Unset = UNSET,
    action_prefix: None | str | Unset = UNSET,
    from_ts: int | None | Unset = UNSET,
    to_ts: int | None | Unset = UNSET,
) -> Response[Any | HTTPValidationError]:
    """Export Admin Audit Csv

     Export CSV (cap 10k linhas). Loga o próprio export no audit.

    Args:
        category (None | str | Unset):
        actor_id (int | None | Unset):
        action_prefix (None | str | Unset):
        from_ts (int | None | Unset):
        to_ts (int | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[Any | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        category=category,
        actor_id=actor_id,
        action_prefix=action_prefix,
        from_ts=from_ts,
        to_ts=to_ts,
    )

    response = client.get_httpx_client().request(
        **kwargs,
    )

    return _build_response(client=client, response=response)


def sync(
    *,
    client: AuthenticatedClient,
    category: None | str | Unset = UNSET,
    actor_id: int | None | Unset = UNSET,
    action_prefix: None | str | Unset = UNSET,
    from_ts: int | None | Unset = UNSET,
    to_ts: int | None | Unset = UNSET,
) -> Any | HTTPValidationError | None:
    """Export Admin Audit Csv

     Export CSV (cap 10k linhas). Loga o próprio export no audit.

    Args:
        category (None | str | Unset):
        actor_id (int | None | Unset):
        action_prefix (None | str | Unset):
        from_ts (int | None | Unset):
        to_ts (int | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Any | HTTPValidationError
    """

    return sync_detailed(
        client=client,
        category=category,
        actor_id=actor_id,
        action_prefix=action_prefix,
        from_ts=from_ts,
        to_ts=to_ts,
    ).parsed


async def asyncio_detailed(
    *,
    client: AuthenticatedClient,
    category: None | str | Unset = UNSET,
    actor_id: int | None | Unset = UNSET,
    action_prefix: None | str | Unset = UNSET,
    from_ts: int | None | Unset = UNSET,
    to_ts: int | None | Unset = UNSET,
) -> Response[Any | HTTPValidationError]:
    """Export Admin Audit Csv

     Export CSV (cap 10k linhas). Loga o próprio export no audit.

    Args:
        category (None | str | Unset):
        actor_id (int | None | Unset):
        action_prefix (None | str | Unset):
        from_ts (int | None | Unset):
        to_ts (int | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Response[Any | HTTPValidationError]
    """

    kwargs = _get_kwargs(
        category=category,
        actor_id=actor_id,
        action_prefix=action_prefix,
        from_ts=from_ts,
        to_ts=to_ts,
    )

    response = await client.get_async_httpx_client().request(**kwargs)

    return _build_response(client=client, response=response)


async def asyncio(
    *,
    client: AuthenticatedClient,
    category: None | str | Unset = UNSET,
    actor_id: int | None | Unset = UNSET,
    action_prefix: None | str | Unset = UNSET,
    from_ts: int | None | Unset = UNSET,
    to_ts: int | None | Unset = UNSET,
) -> Any | HTTPValidationError | None:
    """Export Admin Audit Csv

     Export CSV (cap 10k linhas). Loga o próprio export no audit.

    Args:
        category (None | str | Unset):
        actor_id (int | None | Unset):
        action_prefix (None | str | Unset):
        from_ts (int | None | Unset):
        to_ts (int | None | Unset):

    Raises:
        errors.UnexpectedStatus: If the server returns an undocumented status code and Client.raise_on_unexpected_status is True.
        httpx.TimeoutException: If the request takes longer than Client.timeout.

    Returns:
        Any | HTTPValidationError
    """

    return (
        await asyncio_detailed(
            client=client,
            category=category,
            actor_id=actor_id,
            action_prefix=action_prefix,
            from_ts=from_ts,
            to_ts=to_ts,
        )
    ).parsed
