"""Finance analytics: collection-rate history, a short-term forecast and receivables ageing.

The forecast uses Holt's linear exponential smoothing (level + trend) fitted with a small
grid search on one-step-ahead error, with an 80% band from the residual spread. It is a
planning aid for a few months ahead, not an accounting figure. Amounts are in cents.
"""

import numpy as np
import pandas as pd

from app.schemas import FinanceForecastRequest

AGING_BUCKETS = [("Not yet due", None, 0), ("1–30 days", 1, 30), ("31–60 days", 31, 60), ("61–90 days", 61, 90), ("90+ days", 91, None)]
Z_80 = 1.2816


def forecast(request: FinanceForecastRequest) -> dict:
    frame = pd.DataFrame([month.model_dump() for month in request.months]).sort_values("month") if request.months else pd.DataFrame(columns=["month", "billed", "collected", "expenses"])

    history = [
        {
            "month": row.month,
            "billed": int(row.billed),
            "collected": int(row.collected),
            "expenses": int(row.expenses),
            "collection_rate": round(row.collected / row.billed * 100, 1) if row.billed else None,
        }
        for row in frame.itertuples()
    ]

    future_months = _next_months(frame["month"].iloc[-1], request.horizon) if len(frame) else []

    return {
        "history": history,
        "forecast": {
            "collected": _holt(frame["collected"].to_numpy(dtype=float), future_months),
            "expenses": _holt(frame["expenses"].to_numpy(dtype=float), future_months),
        },
        "aging": _aging(request),
    }


def _holt(series: np.ndarray, future_months: list[str]) -> list[dict]:
    if len(series) < 3 or not future_months:
        return []

    best = None

    for alpha in np.linspace(0.1, 0.9, 9):
        for beta in np.linspace(0.05, 0.5, 10):
            level, trend = series[0], series[1] - series[0]
            errors = []

            for value in series[1:]:
                prediction = level + trend
                errors.append(value - prediction)
                new_level = alpha * value + (1 - alpha) * (level + trend)
                trend = beta * (new_level - level) + (1 - beta) * trend
                level = new_level

            sse = float(np.sum(np.square(errors)))

            if best is None or sse < best[0]:
                best = (sse, level, trend, np.std(errors, ddof=1) if len(errors) > 1 else 0.0)

    _, level, trend, sigma = best
    results = []

    for step, month in enumerate(future_months, start=1):
        value = max(0.0, level + step * trend)
        spread = Z_80 * sigma * np.sqrt(step)
        results.append({"month": month, "value": int(round(value)), "low": int(round(max(0.0, value - spread))), "high": int(round(value + spread))})

    return results


def _aging(request: FinanceForecastRequest) -> list[dict]:
    if not request.open_invoices:
        return [{"bucket": label, "amount": 0, "invoices": 0} for label, _, _ in AGING_BUCKETS]

    frame = pd.DataFrame([invoice.model_dump() for invoice in request.open_invoices])
    result = []

    for label, low, high in AGING_BUCKETS:
        mask = pd.Series(True, index=frame.index)

        if low is None:
            mask &= frame["days_overdue"] <= high
        else:
            mask &= frame["days_overdue"] >= low

            if high is not None:
                mask &= frame["days_overdue"] <= high

        result.append({"bucket": label, "amount": int(frame.loc[mask, "balance"].sum()), "invoices": int(mask.sum())})

    return result


def _next_months(last: str, horizon: int) -> list[str]:
    start = pd.Period(last, freq="M")

    return [str(start + offset) for offset in range(1, horizon + 1)]
