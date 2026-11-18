"""School ERP analytics service.

Stateless computation over pre-aggregated, school-scoped datasets sent by Laravel. Every
request (except /health) must be HMAC-signed; see app/security.py.

Run locally:  uvicorn app.main:create_app --factory --host 127.0.0.1 --port 8100
"""

from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, JSONResponse

from app import security
from app.config import Settings, load_settings
from app.schemas import AtRiskRequest, AttendanceTrendsRequest, FinanceForecastRequest, LearningEngagementRequest, PerformanceRequest, ReportRequest
from app.services import attendance, finance, learning, performance, reports


def create_app(settings: Settings | None = None) -> FastAPI:
    config = settings or load_settings()
    app = FastAPI(title="School ERP Analytics", version="1.0.0", docs_url=None, redoc_url=None, openapi_url=None)

    @app.middleware("http")
    async def verify_signature(request: Request, call_next):
        if request.url.path == "/health":
            return await call_next(request)

        declared = request.headers.get("content-length")

        if declared is not None and declared.isdigit() and int(declared) > config.max_body_bytes:
            return JSONResponse({"detail": "Payload too large."}, status_code=413)

        body = await request.body()

        if len(body) > config.max_body_bytes:
            return JSONResponse({"detail": "Payload too large."}, status_code=413)

        if not security.verify(
            config.shared_secret,
            request.headers.get("x-timestamp"),
            request.headers.get("x-signature"),
            request.method,
            request.url.path,
            body,
            config.max_clock_skew_seconds,
        ):
            return JSONResponse({"detail": "Invalid signature."}, status_code=401)

        return await call_next(request)

    @app.get("/health")
    def health() -> dict:
        return {"status": "ok"}

    @app.post("/v1/attendance/trends")
    def attendance_trends(payload: AttendanceTrendsRequest) -> dict:
        return attendance.trends(payload)

    @app.post("/v1/attendance/at-risk")
    def attendance_at_risk(payload: AtRiskRequest) -> dict:
        return attendance.at_risk(payload)

    @app.post("/v1/performance/summary")
    def performance_summary(payload: PerformanceRequest) -> dict:
        return performance.summary(payload)

    @app.post("/v1/learning/engagement")
    def learning_engagement(payload: LearningEngagementRequest) -> dict:
        return learning.engagement(payload)

    @app.post("/v1/finance/forecast")
    def finance_forecast(payload: FinanceForecastRequest) -> dict:
        return finance.forecast(payload)

    @app.post("/v1/reports/html", response_class=HTMLResponse)
    def html_report(payload: ReportRequest) -> HTMLResponse:
        return HTMLResponse(reports.build(payload), headers={"Content-Security-Policy": "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:"})

    return app
