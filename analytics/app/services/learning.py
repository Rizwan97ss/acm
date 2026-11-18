"""Learning engagement: how actively each student uses the learning portal.

Each student gets a 0–100 engagement score from four signals, each scaled to 0–100:

* completion  – lessons completed / lessons available            (weight 0.40)
* assessment  – average quiz score                               (weight 0.25)
* submission  – assignments handed in / assignments due          (weight 0.20)
* recency     – 100 if active today, falling to 0 after 30 days  (weight 0.15)

Signals that do not apply to a student (no lessons, no quizzes, no work due) are left
out and the remaining weights are rescaled, so nobody is penalised for content that was
never set. Students are banded low (< 40), medium (< 70) and high.
"""

import numpy as np
import pandas as pd

from app.schemas import LearningEngagementRequest

WEIGHTS = {"completion": 0.40, "assessment": 0.25, "submission": 0.20, "recency": 0.15}
RECENCY_WINDOW_DAYS = 30


def engagement(request: LearningEngagementRequest) -> dict:
    if not request.students:
        return {"summary": None, "bands": [], "students": [], "sections": []}

    frame = pd.DataFrame([student.model_dump() for student in request.students])
    frame["lessons_completed"] = np.minimum(frame["lessons_completed"], frame["lessons_available"])
    frame["work_submitted"] = np.minimum(frame["work_submitted"], frame["work_due"])

    signals = pd.DataFrame(index=frame.index)
    signals["completion"] = np.where(frame["lessons_available"] > 0, frame["lessons_completed"] / frame["lessons_available"].replace(0, np.nan) * 100, np.nan)
    signals["assessment"] = frame["quiz_average"].astype(float)
    signals["submission"] = np.where(frame["work_due"] > 0, frame["work_submitted"] / frame["work_due"].replace(0, np.nan) * 100, np.nan)
    days = frame["days_since_active"].astype(float)
    signals["recency"] = np.where(days.isna(), np.where(frame["lessons_available"] > 0, 0.0, np.nan), np.clip(100 - days / RECENCY_WINDOW_DAYS * 100, 0, 100))

    weights = pd.Series(WEIGHTS)
    present = signals.notna()
    weighted = signals.fillna(0).mul(weights, axis=1).sum(axis=1)
    weight_sum = present.mul(weights, axis=1).sum(axis=1)
    frame["score"] = (weighted / weight_sum.replace(0, np.nan)).round(1)

    for name in WEIGHTS:
        frame[name] = signals[name].round(1)

    scored = frame.dropna(subset=["score"]).copy()
    scored["band"] = pd.cut(scored["score"], bins=[-0.1, 40, 70, 100.1], labels=["low", "medium", "high"], right=False).astype(str)
    scored["reasons"] = scored.apply(_reasons, axis=1)

    bands = scored["band"].value_counts()
    sections = (
        scored.dropna(subset=["section"]).groupby("section")["score"].agg(["mean", "count"]).reset_index().sort_values("mean")
        if scored["section"].notna().any()
        else pd.DataFrame(columns=["section", "mean", "count"])
    )

    ordered = scored.sort_values(["score", "student_id"])

    return {
        "summary": {
            "students": int(len(scored)),
            "average_score": _clean(scored["score"].mean()),
            "median_score": _clean(scored["score"].median()),
            "average_completion": _clean(scored["completion"].mean()),
            "average_quiz": _clean(scored["assessment"].mean()),
            "inactive_30_days": int(((frame["days_since_active"].isna()) | (frame["days_since_active"] > RECENCY_WINDOW_DAYS)).sum()),
        },
        "bands": [{"band": band, "students": int(bands.get(band, 0))} for band in ("low", "medium", "high")],
        "sections": [{"section": row.section, "average_score": _clean(row.mean), "students": int(row.count)} for row in sections.itertuples()],
        "students": [
            {
                "student_id": int(row.student_id),
                "section": row.section,
                "score": _clean(row.score),
                "band": row.band,
                "completion": _clean(row.completion),
                "assessment": _clean(row.assessment),
                "submission": _clean(row.submission),
                "days_since_active": None if pd.isna(row.days_since_active) else int(row.days_since_active),
                "reasons": row.reasons,
            }
            for row in ordered.itertuples()
        ],
    }


def _reasons(row: pd.Series) -> list[str]:
    reasons = []

    if not pd.isna(row["completion"]) and row["completion"] < 40:
        reasons.append("few lessons completed")
    if not pd.isna(row["assessment"]) and row["assessment"] < 50:
        reasons.append("low quiz scores")
    if not pd.isna(row["submission"]) and row["submission"] < 60:
        reasons.append("missing homework")
    if pd.isna(row["days_since_active"]) or row["days_since_active"] > 14:
        reasons.append("not active recently")

    return reasons


def _clean(value) -> float | None:
    return None if value is None or pd.isna(value) else round(float(value), 1)
