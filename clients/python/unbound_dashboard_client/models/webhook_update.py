from __future__ import annotations

from collections.abc import Mapping
from typing import Any, TypeVar

from attrs import define as _attrs_define
from attrs import field as _attrs_field

from ..types import UNSET, Unset

T = TypeVar("T", bound="WebhookUpdate")


@_attrs_define
class WebhookUpdate:
    """
    Attributes:
        enabled (bool):
        url (str | Unset):  Default: ''.
        type_ (str | Unset):  Default: 'generic'.
        severity_min (str | Unset):  Default: 'critical'.
        notify_on_release (bool | Unset):  Default: False.
        telegram_chat_id (str | Unset):  Default: ''.
    """

    enabled: bool
    url: str | Unset = ""
    type_: str | Unset = "generic"
    severity_min: str | Unset = "critical"
    notify_on_release: bool | Unset = False
    telegram_chat_id: str | Unset = ""
    additional_properties: dict[str, Any] = _attrs_field(init=False, factory=dict)

    def to_dict(self) -> dict[str, Any]:
        enabled = self.enabled

        url = self.url

        type_ = self.type_

        severity_min = self.severity_min

        notify_on_release = self.notify_on_release

        telegram_chat_id = self.telegram_chat_id

        field_dict: dict[str, Any] = {}
        field_dict.update(self.additional_properties)
        field_dict.update(
            {
                "enabled": enabled,
            }
        )
        if url is not UNSET:
            field_dict["url"] = url
        if type_ is not UNSET:
            field_dict["type"] = type_
        if severity_min is not UNSET:
            field_dict["severity_min"] = severity_min
        if notify_on_release is not UNSET:
            field_dict["notify_on_release"] = notify_on_release
        if telegram_chat_id is not UNSET:
            field_dict["telegram_chat_id"] = telegram_chat_id

        return field_dict

    @classmethod
    def from_dict(cls: type[T], src_dict: Mapping[str, Any]) -> T:
        d = dict(src_dict)
        enabled = d.pop("enabled")

        url = d.pop("url", UNSET)

        type_ = d.pop("type", UNSET)

        severity_min = d.pop("severity_min", UNSET)

        notify_on_release = d.pop("notify_on_release", UNSET)

        telegram_chat_id = d.pop("telegram_chat_id", UNSET)

        webhook_update = cls(
            enabled=enabled,
            url=url,
            type_=type_,
            severity_min=severity_min,
            notify_on_release=notify_on_release,
            telegram_chat_id=telegram_chat_id,
        )

        webhook_update.additional_properties = d
        return webhook_update

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
