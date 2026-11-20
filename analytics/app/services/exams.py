"""Online exam analysis: how the marks are spread, and how well each question worked.

Score distribution: mean, median, standard deviation, quartiles, skewness and the pass rate.

Item analysis, per question (classical test theory):

* difficulty     – average share of the question's marks students earned (0–1). Above 0.9
                   is "too easy", below 0.2 "too hard".
* discrimination – difficulty in the top 27% of students (by total score) minus difficulty in
                   the bottom 27%. Good questions separate strong from weak students: 0.3+ is
                   good, under 0.2 is poor, negative means weaker students did better (usually a
                   wrong answer key or an ambiguous question).
* point_biserial – correlation between the question's marks and the rest-of-paper score
                   (total minus this question), so a question does not correlate with itself.
"""

import numpy as np
import pandas as pd

from app.schemas import ExamAnalysisRequest

GROUP_SHARE = 0.27


def analysis(request: ExamAnalysisRequest) -> dict:
    if len(request.students) < 2:
        return {"summary": None, "items": [], "sections": []}

    students = pd.DataFrame([student.model_dump() for student in request.students]).drop_duplicates("student_id").set_index("student_id")
    scores = students["score"].clip(lower=0).astype(float)
    percentages = scores / request.max_score * 100

    summary = {
        "students": int(len(scores)),
        "mean": _round(percentages.mean()),
        "median": _round(percentages.median()),
        "std": _round(percentages.std(ddof=0)),
        "q1": _round(percentages.quantile(0.25)),
        "q3": _round(percentages.quantile(0.75)),
        "skewness": _round(percentages.skew()) if len(scores) >= 3 else None,
        "pass_rate": _round((scores >= request.passing_marks).mean() * 100),
    }

    return {"summary": summary, "items": _items(request, scores), "sections": _sections(students, percentages, scores, request.passing_marks)}


def _items(request: ExamAnalysisRequest, scores: pd.Series) -> list[dict]:
    if not request.questions:
        return []

    responses = pd.DataFrame([response.model_dump() for response in request.responses]) if request.responses else pd.DataFrame(columns=["student_id", "question_id", "marks"])
    responses = responses[responses["student_id"].isin(scores.index)]
    matrix = responses.pivot_table(index="student_id", columns="question_id", values="marks", aggfunc="sum").reindex(index=scores.index).fillna(0.0)

    group = max(1, int(round(len(scores) * GROUP_SHARE)))
    ranked = scores.sort_values(kind="mergesort")
    lower, upper = ranked.index[:group], ranked.index[-group:]

    items = []

    for question in sorted(request.questions, key=lambda item: item.number):
        marks = matrix[question.question_id] if question.question_id in matrix.columns else pd.Series(0.0, index=scores.index)
        share = (marks / question.marks).clip(lower=-1, upper=1)
        difficulty = share.mean()
        discrimination = share.loc[upper].mean() - share.loc[lower].mean()
        rest = scores - marks
        point_biserial = marks.corr(rest) if marks.std(ddof=0) > 0 and rest.std(ddof=0) > 0 else np.nan

        items.append({
            "question_id": question.question_id,
            "number": question.number,
            "difficulty": _round(difficulty, 2),
            "discrimination": _round(discrimination, 2),
            "point_biserial": _round(point_biserial, 2),
            "flags": _flags(difficulty, discrimination),
        })

    return items


def _flags(difficulty: float, discrimination: float) -> list[str]:
    flags = []

    if difficulty > 0.9:
        flags.append("too_easy")
    elif difficulty < 0.2:
        flags.append("too_hard")

    if discrimination < 0:
        flags.append("negative_discrimination")
    elif discrimination < 0.2:
        flags.append("poor_discrimination")

    return flags


def _sections(students: pd.DataFrame, percentages: pd.Series, scores: pd.Series, passing: float) -> list[dict]:
    if not students["section"].notna().any():
        return []

    frame = pd.DataFrame({"section": students["section"], "percentage": percentages, "passed": scores >= passing}).dropna(subset=["section"])
    grouped = frame.groupby("section").agg(count=("percentage", "size"), average=("percentage", "mean"), spread=("percentage", "std"), passing=("passed", "mean"))

    return [
        {"section": section, "students": int(row["count"]), "mean": _round(row["average"]), "std": _round(row["spread"]), "pass_rate": _round(row["passing"] * 100)}
        for section, row in grouped.sort_index().iterrows()
    ]


def _round(value, digits: int = 1) -> float | None:
    return None if value is None or pd.isna(value) else round(float(value), digits)
