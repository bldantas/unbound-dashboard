from enum import Enum


class ExportCsvApiV1AnalyticsQueriesExportCsvGetWindow(str, Enum):
    VALUE_0 = "1h"
    VALUE_1 = "24h"
    VALUE_2 = "7d"
    VALUE_3 = "30d"

    def __str__(self) -> str:
        return str(self.value)
