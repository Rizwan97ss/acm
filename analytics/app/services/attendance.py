"""Attendance analytics: trends, weekday patterns and students at risk.

Attendance rate = (present + late) / (present + late + absent); excused days are not counted.
"""

import numpy as np
import pandas as pd

from app.schemas import AtRiskRequest, AttendanceTrendsRequest

WEEKDAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]


def _rate(attended: pd.Series, countable: pd.Series) -> pd.Series:
    return (attended / countable.replace(0, np.nan) * 100).round(2)


def trends(request: AttendanceTrendsRequest) -> dict:
    if not request.days:
        return {"daily": [], "weekdays": [], "summary": None}

    frame = pd.DataFrame([day.model_dump() for day in request.days])
    frame["date"] = pd.to_datetime(frame["date"])
    frame = frame.groupby("date", as_index=False).sum().sort_values("date")
    frame["attended"] = frame["present"] + frame["late"]
    frame["countable"] = frame["attended"] + frame["absent"]
    frame["rate"] = _rate(frame["attended"], frame["countable"])
    frame["moving_average"] = frame["rate"].rolling(window=7, min_periods=1).mean().round(2)

    weekly = frame.set_index("date")[["attended", "countable"]].resample("W-MON", label="left", closed="left").sum()
    weekly = weekly[weekly["countable"] > 0]
    weekly_rate = _rate(weekly["attended"], weekly["countable"])

    slope = None

    if len(weekly_rate) >= 2:
        slope = float(np.polyfit(np.arange(len(weekly_rate)), weekly_rate.to_numpy(dtype=float), 1)[0])

    frame["weekday"] = frame["date"].dt.dayofweek
    by_weekday = frame.groupby("weekday")[["attended", "countable"]].sum()
    by_weekday["rate"] = _rate(by_weekday["attended"], by_weekday["countable"])

    total_attended = int(frame["attended"].sum())
    total_countable = int(frame["countable"].sum())
    valid = frame.dropna(subset=["rate"])

    return {
        "daily": [
            {"date": row.date.strftime("%Y-%m-%d"), "rate": _clean(row.rate), "moving_average": _clean(row.moving_average)}
            for row in frame.itertuples()
        ],
        "weekdays": [
            {"weekday": WEEKDAYS[int(index)], "rate": _clean(row.rate)}
            for index, row in by_weekday.iterrows()
        ],
        "summary": {
            "overall_rate": round(total_attended / total_countable * 100, 2) if total_countable else None,
            "weekly_trend": round(slope, 3) if slope is not None else None,
            "trend_direction": None if slope is None else ("improving" if slope > 0.25 else "declining" if slope < -0.25 else "steady"),
            "best_day": _day(valid, "idxmax"),
            "worst_day": _day(valid, "idxmin"),
            "school_days": int((frame["countable"] > 0).sum()),
        },
    }


def at_risk(request: AtRiskRequest) -> dict:
    if not request.students:
        return {"students": [], "summary": {"total": 0, "at_risk": 0, "mean_rate": None}}

    frame = pd.DataFrame([student.model_dump() for student in request.students])
    frame["attended"] = frame["present"] + frame["late"]
    frame["countable"] = frame["attended"] + frame["absent"]
    frame["rate"] = _rate(frame["attended"], frame["countable"])

    mean = frame["rate"].mean()
    std = frame["rate"].std(ddof=0)
    frame["z_score"] = ((frame["rate"] - mean) / std).round(2) if std and not np.isnan(std) else 0.0

    reasons: list[list[str]] = []

    for row in frame.itertuples():
        flags = []

        if not np.isnan(row.rate) and row.rate < request.threshold:
            flags.append(f"Attendance below {request.threshold:g}%")

        if row.recent_absences >= 3:
            flags.append(f"{row.recent_absences} absences in the last two weeks")

        if row.late >= 5 and row.countable and row.late / row.countable > 0.15:
            flags.append("Frequently late")

        reasons.append(flags)

    frame["reasons"] = reasons
    frame["risk_score"] = (
        (request.threshold - frame["rate"].fillna(100)).clip(lower=0) * 2
        + frame["recent_absences"] * 5
        + (frame["late"] / frame["countable"].replace(0, np.nan)).fillna(0) * 20
    ).round(1)

    flagged = frame[frame["reasons"].map(len) > 0].sort_values("risk_score", ascending=False)

    return {
        "students": [
            {
                "student_id": int(row.student_id),
                "name": row.name,
                "section": row.section,
                "rate": _clean(row.rate),
                "z_score": _clean(row.z_score),
                "risk_score": float(row.risk_score),
                "reasons": row.reasons,
            }
            for row in flagged.itertuples()
        ],
        "summary": {"total": int(len(frame)), "at_risk": int(len(flagged)), "mean_rate": _clean(round(mean, 2))},
    }


def _day(frame: pd.DataFrame, method: str) -> dict | None:
    if frame.empty:
        return None

    row = frame.loc[getattr(frame["rate"], method)()]

    return {"date": row["date"].strftime("%Y-%m-%d"), "rate": _clean(row["rate"])}


def _clean(value) -> float | None:
    return None if value is None or (isinstance(value, float) and np.isnan(value)) else float(value)
