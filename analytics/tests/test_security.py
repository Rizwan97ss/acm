import time

PATH = "/v1/attendance/trends"


def test_health_is_public(client):
    assert client.get("/health").json() == {"status": "ok"}


def test_signed_request_is_accepted(signed_post):
    assert signed_post(PATH, {"days": []}).status_code == 200


def test_unsigned_request_is_rejected(client):
    assert client.post(PATH, json={"days": []}).status_code == 401


def test_wrong_secret_is_rejected(signed_post):
    assert signed_post(PATH, {"days": []}, secret="another-secret-that-is-32-characters-long").status_code == 401


def test_stale_timestamp_is_rejected(signed_post):
    assert signed_post(PATH, {"days": []}, timestamp=int(time.time()) - 300).status_code == 401


def test_tampered_body_is_rejected(client):
    from app import security
    from tests.conftest import SECRET

    stamp = str(int(time.time()))
    signature = security.sign(SECRET, stamp, "POST", PATH, b'{"days": []}')
    response = client.post(PATH, content=b'{"days": [{"date": "2026-01-05", "present": 1, "absent": 0, "late": 0, "excused": 0}]}', headers={"X-Timestamp": stamp, "X-Signature": signature, "Content-Type": "application/json"})

    assert response.status_code == 401


def test_oversized_payload_is_rejected(signed_post):
    days = [{"date": "2026-01-05", "present": 1, "absent": 0, "late": 0, "excused": 0}] * 5000

    assert signed_post(PATH, {"days": days}).status_code == 413
