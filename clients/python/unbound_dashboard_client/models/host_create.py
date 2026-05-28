from __future__ import annotations

from collections.abc import Mapping
from typing import Any, TypeVar, cast

from attrs import define as _attrs_define
from attrs import field as _attrs_field

from ..types import UNSET, Unset

T = TypeVar("T", bound="HostCreate")


@_attrs_define
class HostCreate:
    """
    Attributes:
        label (str):
        base_url (str): https://host:port (sem /api/...)
        api_token (str): Token gerado em Settings → API Tokens do agent
        notes (None | str | Unset):
        org_id (int | None | Unset): Org dona do host. None = global (visível a todos os admins).
    """

    label: str
    base_url: str
    api_token: str
    notes: None | str | Unset = UNSET
    org_id: int | None | Unset = UNSET
    additional_properties: dict[str, Any] = _attrs_field(init=False, factory=dict)

    def to_dict(self) -> dict[str, Any]:
        label = self.label

        base_url = self.base_url

        api_token = self.api_token

        notes: None | str | Unset
        if isinstance(self.notes, Unset):
            notes = UNSET
        else:
            notes = self.notes

        org_id: int | None | Unset
        if isinstance(self.org_id, Unset):
            org_id = UNSET
        else:
            org_id = self.org_id

        field_dict: dict[str, Any] = {}
        field_dict.update(self.additional_properties)
        field_dict.update(
            {
                "label": label,
                "base_url": base_url,
                "api_token": api_token,
            }
        )
        if notes is not UNSET:
            field_dict["notes"] = notes
        if org_id is not UNSET:
            field_dict["org_id"] = org_id

        return field_dict

    @classmethod
    def from_dict(cls: type[T], src_dict: Mapping[str, Any]) -> T:
        d = dict(src_dict)
        label = d.pop("label")

        base_url = d.pop("base_url")

        api_token = d.pop("api_token")

        def _parse_notes(data: object) -> None | str | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(None | str | Unset, data)

        notes = _parse_notes(d.pop("notes", UNSET))

        def _parse_org_id(data: object) -> int | None | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(int | None | Unset, data)

        org_id = _parse_org_id(d.pop("org_id", UNSET))

        host_create = cls(
            label=label,
            base_url=base_url,
            api_token=api_token,
            notes=notes,
            org_id=org_id,
        )

        host_create.additional_properties = d
        return host_create

    @property
    def additional_keys(self) -> list[str]:
        return list(self.additional_properties.keys())

    def __getitem__(self, key: str) -> Any:
        return self.additional_properties[key]

    def __setitem__(self, key: str, value: Any) -> None:
        self.additional_properties[key] = value

    def __delitem__(self, key: str) -> None:
        del self.additional_properties[key]

    def __contains__(self, key: str) -> bool:
        return key in self.additional_properties
