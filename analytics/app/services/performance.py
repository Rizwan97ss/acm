"""Exam performance analytics: subject statistics, score distribution, attendance
correlation, and students who improved or declined between the two latest exams."""

import numpy as np
import pandas as pd

from app.schemas import PerformanceRequest

BINS = [0, 40, 50, 60, 70, 80, 90, 100.0001]
BIN_LABELS = ["0–39", "40–49", "50–59", "60–69", "70–79", "80–89", "90–100"]


def summary(request: PerformanceRequest) -> dict:
    rows = [
        {**score.model_dump(), "exam_id": exam.exam_id, "exam": exam.exam, "order": index}
        for index, exam in enumerate(request.exams)
        for score in exam.scores
    ]

    if not rows:
        return {"subjects": [], "distribution": [], "correlation": None, "movers": {"improved": [], "declined": []}, "exams": []}

    frame = pd.DataFrame(rows)
    latest_order = frame["order"].max()
    latest = frame[frame["order"] == latest_order]

    subjects = (
        latest.groupby("subject")
        .agg(mean=("percentage", "mean"), median=("percentage", "median"), std=("percentage", "std"), pass_rate=("passed", "mean"), students=("student_id", "nunique"))
        .reset_index()
        .sort_values("mean", ascending=False)
    )

    student_latest = latest.groupby("student_id", as_index=False)["percentage"].mean()
    counts = pd.cut(student_latest["percentage"], bins=BINS, labels=BIN_LABELS, right=False).value_counts().reindex(BIN_LABELS, fill_value=0)

    correlation = None

    if request.attendance:
        joined = student_latest.assign(attendance=student_latest["student_id"].map(request.attendance)).dropna(subset=["attendance"])

        if len(joined) >= 3 and joined["attendance"].std() > 0 and joined["percentage"].std() > 0:
            correlation = round(float(np.corrcoef(joined["attendance"], joined["percentage"])[0, 1]), 3)

    movers = {"improved": [], "declined": []}

    if latest_order > 0:
        previous = frame[frame["order"] == latest_order - 1].groupby("student_id")["percentage"].mean()
        change = student_latest.set_index("student_id")["percentage"].sub(previous).dropna()
        def describe(series: pd.Series) -> list[dict]:
            return [{"student_id": int(student_id), "change": round(float(delta), 2)} for student_id, delta in series.items()]

        movers = {
            "improved": describe(change[change > 0].sort_values(ascending=False).head(5)),
            "declined": describe(change[change < 0].sort_values().head(5)),
        }

    exams = frame.groupby(["order", "exam"], as_index=False)["percentage"].mean().sort_values("order")

    return {
        "subjects": [
            {
                "subject": row.subject,
                "mean": round(float(row.mean), 2),
                "median": round(float(row.median), 2),
                "std": round(float(row.std), 2) if not np.isnan(row.std) else 0.0,
                "pass_rate": round(float(row.pass_rate) * 100, 1),
                "students": int(row.students),
            }
            for row in subjects.itertuples()
        ],
        "distribution": [{"band": band, "students": int(count)} for band, count in counts.items()],
        "correlation": correlation,
        "movers": movers,
        "exams": [{"exam": row.exam, "mean": round(float(row.percentage), 2)} for row in exams.itertuples()],
    }
