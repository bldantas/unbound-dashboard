from __future__ import annotations

from collections.abc import Mapping
from typing import Any, TypeVar

from attrs import define as _attrs_define
from attrs import field as _attrs_field

from ..types import UNSET, Unset

T = TypeVar("T", bound="PushConfigRequest")


@_attrs_define
class PushConfigRequest:
    """
    Attributes:
        include_blocklists (bool | Unset):  Default: True.
        include_policies (bool | Unset):  Default: True.
    """

    include_blocklists: bool | Unset = True
    include_policies: bool | Unset = True
    additional_properties: dict[str, Any] = _attrs_field(init=False, factory=dict)

    def to_dict(self) -> dict[str, Any]:
        include_blocklists = self.include_blocklists

        include_policies = self.include_policies

        field_dict: dict[str, Any] = {}
        field_dict.update(self.additional_properties)
        field_dict.update({})
        if include_blocklists is not UNSET:
            field_dict["include_blocklists"] = include_blocklists
        if include_policies is not UNSET:
            field_dict["include_policies"] = include_policies

        return field_dict

    @classmethod
    def from_dict(cls: type[T], src_dict: Mapping[str, Any]) -> T:
        d = dict(src_dict)
        include_blocklists = d.pop("include_blocklists", UNSET)

        include_policies = d.pop("include_policies", UNSET)

        push_config_request = cls(
            include_blocklists=include_blocklists,
            include_policies=include_policies,
        )

        push_config_request.additional_properties = d
        return push_config_request

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
