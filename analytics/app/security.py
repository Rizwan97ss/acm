"""Request signing shared with Laravel (App\\Services\\Analytics\\AnalyticsClient).

signature = hex(HMAC-SHA256(secret, f"{timestamp}.{METHOD}.{path}.{sha256(body)}"))

Requests must carry X-Timestamp (unix seconds) and X-Signature. Old or future-dated
timestamps are rejected to prevent replay, and comparison is constant-time.
"""

import hashlib
import hmac
import time


def sign(secret: str, timestamp: str, method: str, path: str, body: bytes) -> str:
    body_hash = hashlib.sha256(body).hexdigest()
    message = f"{timestamp}.{method.upper()}.{path}.{body_hash}".encode()

    return hmac.new(secret.encode(), message, hashlib.sha256).hexdigest()


def verify(secret: str, timestamp: str | None, signature: str | None, method: str, path: str, body: bytes, max_skew: int, now: float | None = None) -> bool:
    if not timestamp or not signature or not timestamp.isdigit():
        return False

    current = time.time() if now is None else now

    if abs(current - int(timestamp)) > max_skew:
        return False

    expected = sign(secret, timestamp, method, path, body)

    return hmac.compare_digest(expected, signature)
