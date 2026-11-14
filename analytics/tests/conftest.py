import json
import time

import pytest
from fastapi.testclient import TestClient

from app import security
from app.config import Settings
from app.main import create_app

SECRET = "test-secret-that-is-at-least-32-characters"


@pytest.fixture
def client() -> TestClient:
    return TestClient(create_app(Settings(shared_secret=SECRET, max_body_bytes=200_000)))


@pytest.fixture
def signed_post(client):
    def post(path: str, payload: dict, *, secret: str = SECRET, timestamp: int | None = None):
        body = json.dumps(payload).encode()
        stamp = str(timestamp if timestamp is not None else int(time.time()))
        signature = security.sign(secret, stamp, "POST", path, body)

        return client.post(path, content=body, headers={"Content-Type": "application/json", "X-Timestamp": stamp, "X-Signature": signature})

    return post
