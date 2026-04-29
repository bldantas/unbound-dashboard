from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone
from enum import StrEnum
from typing import Optional


class Severity(StrEnum):
    INFO = "info"
    WARNING = "warning"
    CRITICAL = "critical"


@dataclass
class Alert:
    id: int
    type: str
    message: str
    severity: Severity
    is_read: bool
    created_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))


@dataclass
class AlertCreate:
    type: str
    message: str
    severity: Severity = Severity.INFO
