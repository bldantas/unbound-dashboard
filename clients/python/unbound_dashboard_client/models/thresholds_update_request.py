from __future__ import annotations

from collections.abc import Mapping
from typing import Any, TypeVar, cast

from attrs import define as _attrs_define
from attrs import field as _attrs_field

from ..types import UNSET, Unset

T = TypeVar("T", bound="ThresholdsUpdateRequest")


@_attrs_define
class ThresholdsUpdateRequest:
    """Aceita apenas os 6 thresholds conhecidos. Valores precisam ser >= 0.
    Campos omitidos não são alterados (PATCH-style).

        Attributes:
            alert_threshold_cpu_load1 (float | None | Unset):
            alert_threshold_mem_percent (float | None | Unset):
            alert_threshold_swap_percent (float | None | Unset):
            alert_threshold_disk_percent (float | None | Unset):
            alert_threshold_network_counters (float | None | Unset):
            alert_threshold_ssh_failed_day (float | None | Unset):
    """

    alert_threshold_cpu_load1: float | None | Unset = UNSET
    alert_threshold_mem_percent: float | None | Unset = UNSET
    alert_threshold_swap_percent: float | None | Unset = UNSET
    alert_threshold_disk_percent: float | None | Unset = UNSET
    alert_threshold_network_counters: float | None | Unset = UNSET
    alert_threshold_ssh_failed_day: float | None | Unset = UNSET
    additional_properties: dict[str, Any] = _attrs_field(init=False, factory=dict)

    def to_dict(self) -> dict[str, Any]:
        alert_threshold_cpu_load1: float | None | Unset
        if isinstance(self.alert_threshold_cpu_load1, Unset):
            alert_threshold_cpu_load1 = UNSET
        else:
            alert_threshold_cpu_load1 = self.alert_threshold_cpu_load1

        alert_threshold_mem_percent: float | None | Unset
        if isinstance(self.alert_threshold_mem_percent, Unset):
            alert_threshold_mem_percent = UNSET
        else:
            alert_threshold_mem_percent = self.alert_threshold_mem_percent

        alert_threshold_swap_percent: float | None | Unset
        if isinstance(self.alert_threshold_swap_percent, Unset):
            alert_threshold_swap_percent = UNSET
        else:
            alert_threshold_swap_percent = self.alert_threshold_swap_percent

        alert_threshold_disk_percent: float | None | Unset
        if isinstance(self.alert_threshold_disk_percent, Unset):
            alert_threshold_disk_percent = UNSET
        else:
            alert_threshold_disk_percent = self.alert_threshold_disk_percent

        alert_threshold_network_counters: float | None | Unset
        if isinstance(self.alert_threshold_network_counters, Unset):
            alert_threshold_network_counters = UNSET
        else:
            alert_threshold_network_counters = self.alert_threshold_network_counters

        alert_threshold_ssh_failed_day: float | None | Unset
        if isinstance(self.alert_threshold_ssh_failed_day, Unset):
            alert_threshold_ssh_failed_day = UNSET
        else:
            alert_threshold_ssh_failed_day = self.alert_threshold_ssh_failed_day

        field_dict: dict[str, Any] = {}
        field_dict.update(self.additional_properties)
        field_dict.update({})
        if alert_threshold_cpu_load1 is not UNSET:
            field_dict["alert_threshold_cpu_load1"] = alert_threshold_cpu_load1
        if alert_threshold_mem_percent is not UNSET:
            field_dict["alert_threshold_mem_percent"] = alert_threshold_mem_percent
        if alert_threshold_swap_percent is not UNSET:
            field_dict["alert_threshold_swap_percent"] = alert_threshold_swap_percent
        if alert_threshold_disk_percent is not UNSET:
            field_dict["alert_threshold_disk_percent"] = alert_threshold_disk_percent
        if alert_threshold_network_counters is not UNSET:
            field_dict["alert_threshold_network_counters"] = alert_threshold_network_counters
        if alert_threshold_ssh_failed_day is not UNSET:
            field_dict["alert_threshold_ssh_failed_day"] = alert_threshold_ssh_failed_day

        return field_dict

    @classmethod
    def from_dict(cls: type[T], src_dict: Mapping[str, Any]) -> T:
        d = dict(src_dict)

        def _parse_alert_threshold_cpu_load1(data: object) -> float | None | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(float | None | Unset, data)

        alert_threshold_cpu_load1 = _parse_alert_threshold_cpu_load1(d.pop("alert_threshold_cpu_load1", UNSET))

        def _parse_alert_threshold_mem_percent(data: object) -> float | None | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(float | None | Unset, data)

        alert_threshold_mem_percent = _parse_alert_threshold_mem_percent(d.pop("alert_threshold_mem_percent", UNSET))

        def _parse_alert_threshold_swap_percent(data: object) -> float | None | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(float | None | Unset, data)

        alert_threshold_swap_percent = _parse_alert_threshold_swap_percent(d.pop("alert_threshold_swap_percent", UNSET))

        def _parse_alert_threshold_disk_percent(data: object) -> float | None | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(float | None | Unset, data)

        alert_threshold_disk_percent = _parse_alert_threshold_disk_percent(d.pop("alert_threshold_disk_percent", UNSET))

        def _parse_alert_threshold_network_counters(data: object) -> float | None | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(float | None | Unset, data)

        alert_threshold_network_counters = _parse_alert_threshold_network_counters(
            d.pop("alert_threshold_network_counters", UNSET)
        )

        def _parse_alert_threshold_ssh_failed_day(data: object) -> float | None | Unset:
            if data is None:
                return data
            if isinstance(data, Unset):
                return data
            return cast(float | None | Unset, data)

        alert_threshold_ssh_failed_day = _parse_alert_threshold_ssh_failed_day(
            d.pop("alert_threshold_ssh_failed_day", UNSET)
        )

        thresholds_update_request = cls(
            alert_threshold_cpu_load1=alert_threshold_cpu_load1,
            alert_threshold_mem_percent=alert_threshold_mem_percent,
            alert_threshold_swap_percent=alert_threshold_swap_percent,
            alert_threshold_disk_percent=alert_threshold_disk_percent,
            alert_threshold_network_counters=alert_threshold_network_counters,
            alert_threshold_ssh_failed_day=alert_threshold_ssh_failed_day,
        )

        thresholds_update_request.additional_properties = d
        return thresholds_update_request

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
