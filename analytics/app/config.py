"""Service configuration, read once from the environment."""

import os
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    shared_secret: str
    max_clock_skew_seconds: int = 60
    max_body_bytes: int = 5 * 1024 * 1024


def load_settings() -> Settings:
    secret = os.environ.get("ANALYTICS_SHARED_SECRET", "")

    if len(secret) < 32:
        raise RuntimeError("ANALYTICS_SHARED_SECRET must be set to at least 32 characters.")

    return Settings(
        shared_secret=secret,
        max_clock_skew_seconds=int(os.environ.get("ANALYTICS_MAX_CLOCK_SKEW", "60")),
        max_body_bytes=int(os.environ.get("ANALYTICS_MAX_BODY_BYTES", str(5 * 1024 * 1024))),
    )
