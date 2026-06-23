"""Scenario result types and control-flow exceptions."""

from __future__ import annotations

from dataclasses import dataclass


class SkipScenario(Exception):
    """Raise to mark a scenario SKIP (e.g. an unrelated prerequisite is absent)."""


class BehaviorMismatch(Exception):
    """Raise when a statement ran without error but the result differs from MySQL.

    These are recorded as FAIL with error_code 'BEHAVIOR' — the most dangerous
    class of incompatibility because there is no error to alert the application.
    """


PASS = "PASS"
FAIL = "FAIL"
SKIP = "SKIP"


@dataclass
class TestResult:
    id: int
    framework: str
    category: str
    name: str
    status: str
    duration_ms: float
    detail: str | None = None
    error_code: str | None = None
    error_message: str | None = None

    def to_dict(self) -> dict:
        msg = self.error_message
        if msg is not None and len(msg) > 200:
            msg = msg[:197] + "..."
        return {
            "framework": self.framework,
            "category": self.category,
            "name": self.name,
            "status": self.status,
            "error_code": self.error_code,
            "error_message": msg,
        }
