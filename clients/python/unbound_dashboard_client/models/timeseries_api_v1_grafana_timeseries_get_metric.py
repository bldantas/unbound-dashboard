from enum import Enum


class TimeseriesApiV1GrafanaTimeseriesGetMetric(str, Enum):
    BLOCKED = "blocked"
    TOTAL = "total"

    def __str__(self) -> str:
        return str(self.value)
