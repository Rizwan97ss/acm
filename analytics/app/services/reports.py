"""Self-contained, interactive HTML reports built with Plotly for download.

Titles and labels are HTML-escaped; Plotly renders data values as text, never as markup.
"""

import html

import plotly.graph_objects as go
from plotly.io import to_html

from app.schemas import ReportRequest
from app.services import attendance, finance

COLORS = {"primary": "#4f46e5", "secondary": "#10b981", "accent": "#f43f5e", "muted": "#94a3b8"}


def build(request: ReportRequest) -> str:
    sections: list[str] = []
    include_js: bool | str = True

    if request.attendance is not None:
        result = attendance.trends(request.attendance)
        figure = go.Figure()
        figure.add_scatter(x=[d["date"] for d in result["daily"]], y=[d["rate"] for d in result["daily"]], mode="markers", name="Daily rate", marker={"color": COLORS["muted"], "size": 6})
        figure.add_scatter(x=[d["date"] for d in result["daily"]], y=[d["moving_average"] for d in result["daily"]], mode="lines", name="7-day average", line={"color": COLORS["primary"], "width": 2})
        figure.update_layout(title="Attendance rate (%)", yaxis={"range": [0, 100]}, template="plotly_white", height=380)
        sections.append(to_html(figure, include_plotlyjs=include_js, full_html=False))
        include_js = False

        summary = result["summary"] or {}
        sections.append(f"<p>Overall attendance: <strong>{summary.get('overall_rate', '—')}%</strong> over {summary.get('school_days', 0)} school days. Trend: {html.escape(str(summary.get('trend_direction') or 'not enough data'))}.</p>")

    if request.finance is not None:
        result = finance.forecast(request.finance)
        figure = go.Figure()
        months = [m["month"] for m in result["history"]]
        figure.add_bar(x=months, y=[m["collected"] / 100 for m in result["history"]], name="Collected", marker_color=COLORS["secondary"])
        figure.add_bar(x=months, y=[m["expenses"] / 100 for m in result["history"]], name="Expenses", marker_color=COLORS["accent"])
        projected = result["forecast"]["collected"]

        if projected:
            figure.add_scatter(x=[p["month"] for p in projected], y=[p["value"] / 100 for p in projected], mode="lines+markers", name="Forecast (collected)", line={"color": COLORS["primary"], "dash": "dash"})

        figure.update_layout(title="Collections, expenses and forecast", barmode="group", template="plotly_white", height=400)
        sections.append(to_html(figure, include_plotlyjs=include_js, full_html=False))

    title = html.escape(request.title)
    body = "\n".join(sections) or "<p>No data.</p>"

    return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<style>body{{font-family:system-ui,sans-serif;max-width:1000px;margin:2rem auto;padding:0 1rem;color:#1e1b4b}}h1{{background:linear-gradient(90deg,#4f46e5,#c026d3);-webkit-background-clip:text;background-clip:text;color:transparent}}</style>
</head><body><h1>{title}</h1>{body}</body></html>"""
