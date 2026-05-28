from enum import Enum


class GetTopDomainsApiV1AnalyticsTopDomainsGetActionType0(str, Enum):
    BLOCKED = "blocked"
    CACHED = "cached"
    NXDOMAIN_UPSTREAM = "nxdomain_upstream"
    RESOLVED = "resolved"

    def __str__(self) -> str:
        return str(self.value)
