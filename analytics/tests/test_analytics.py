from datetime import date, timedelta


def _days(rates: list[int], start: date = date(2026, 1, 5)) -> list[dict]:
    return [
        {"date": (start + timedelta(days=index)).isoformat(), "present": rate, "absent": 100 - rate, "late": 0, "excused": 0}
        for index, rate in enumerate(rates)
    ]


def test_attendance_trends_detects_decline(signed_post):
    rates = [95] * 7 + [90] * 7 + [85] * 7 + [80] * 7
    result = signed_post("/v1/attendance/trends", {"days": _days(rates)}).json()

    assert result["summary"]["overall_rate"] == 87.5
    assert result["summary"]["trend_direction"] == "declining"
    assert result["summary"]["school_days"] == 28
    assert result["daily"][0] == {"date": "2026-01-05", "rate": 95.0, "moving_average": 95.0}
    assert len(result["weekdays"]) == 7


def test_attendance_trends_handles_empty_and_zero_days(signed_post):
    assert signed_post("/v1/attendance/trends", {"days": []}).json()["summary"] is None

    result = signed_post("/v1/attendance/trends", {"days": [{"date": "2026-01-05", "present": 0, "absent": 0, "late": 0, "excused": 4}]}).json()

    assert result["daily"][0]["rate"] is None
    assert result["summary"]["overall_rate"] is None


def test_at_risk_flags_low_attendance_and_recent_absences(signed_post):
    students = [
        {"student_id": 1, "name": "Good", "present": 95, "absent": 5, "late": 0, "excused": 0, "recent_absences": 0},
        {"student_id": 2, "name": "Low", "present": 60, "absent": 40, "late": 0, "excused": 0, "recent_absences": 1},
        {"student_id": 3, "name": "Recent", "present": 90, "absent": 10, "late": 0, "excused": 0, "recent_absences": 4},
    ]
    result = signed_post("/v1/attendance/at-risk", {"students": students, "threshold": 75}).json()

    assert [s["student_id"] for s in result["students"]] == [2, 3]
    assert result["students"][0]["reasons"] == ["Attendance below 75%"]
    assert result["summary"]["at_risk"] == 2


def test_performance_summary(signed_post):
    def score(student_id, subject, percentage):
        return {"student_id": student_id, "name": f"S{student_id}", "subject": subject, "percentage": percentage, "passed": percentage >= 40}

    payload = {
        "exams": [
            {"exam_id": 1, "exam": "Mid", "scores": [score(1, "Math", 50), score(2, "Math", 80), score(3, "Math", 30)]},
            {"exam_id": 2, "exam": "Final", "scores": [score(1, "Math", 70), score(2, "Math", 75), score(3, "Math", 35), score(1, "English", 90), score(2, "English", 85), score(3, "English", 45)]},
        ],
        "attendance": {"1": 90, "2": 95, "3": 60},
    }
    result = signed_post("/v1/performance/summary", payload).json()

    assert [s["subject"] for s in result["subjects"]] == ["English", "Math"]
    assert result["subjects"][1]["pass_rate"] == 66.7
    assert sum(band["students"] for band in result["distribution"]) == 3
    assert result["correlation"] > 0.5
    assert result["movers"]["improved"][0]["student_id"] == 1
    assert [e["exam"] for e in result["exams"]] == ["Mid", "Final"]


def test_finance_forecast_and_aging(signed_post):
    months = [{"month": f"2026-{m:02d}", "billed": 100_000, "collected": 50_000 + m * 5_000, "expenses": 40_000} for m in range(1, 9)]
    invoices = [{"balance": 1_000, "days_overdue": -5}, {"balance": 2_000, "days_overdue": 15}, {"balance": 3_000, "days_overdue": 120}]
    result = signed_post("/v1/finance/forecast", {"months": months, "open_invoices": invoices, "horizon": 3}).json()

    projected = result["forecast"]["collected"]

    assert [p["month"] for p in projected] == ["2026-09", "2026-10", "2026-11"]
    assert projected[0]["value"] > 85_000
    assert all(p["low"] <= p["value"] <= p["high"] for p in projected)
    assert result["history"][0]["collection_rate"] == 55.0
    assert {a["bucket"]: a["amount"] for a in result["aging"]} == {"Not yet due": 1_000, "1–30 days": 2_000, "31–60 days": 0, "61–90 days": 0, "90+ days": 3_000}


def test_finance_forecast_needs_three_months(signed_post):
    result = signed_post("/v1/finance/forecast", {"months": [{"month": "2026-01", "billed": 1, "collected": 1, "expenses": 1}]}).json()

    assert result["forecast"]["collected"] == []


def test_html_report_escapes_title(signed_post):
    response = signed_post("/v1/reports/html", {"title": "<script>alert(1)</script>", "attendance": {"days": _days([90, 80, 85])}})

    assert response.status_code == 200
    assert "&lt;script&gt;alert(1)&lt;/script&gt;" in response.text
    assert "<script>alert(1)</script>" not in response.text
    assert "Plotly" in response.text


def test_invalid_payload_is_rejected(signed_post):
    assert signed_post("/v1/finance/forecast", {"months": [{"month": "January", "billed": 1, "collected": 1, "expenses": 1}]}).status_code == 422


def test_learning_engagement_scores_bands_and_reasons(signed_post):
    students = [
        {"student_id": 1, "section": "5-A", "lessons_available": 10, "lessons_completed": 10, "days_since_active": 0, "quiz_average": 92, "work_due": 4, "work_submitted": 4},
        {"student_id": 2, "section": "5-A", "lessons_available": 10, "lessons_completed": 1, "days_since_active": None, "quiz_average": 30, "work_due": 4, "work_submitted": 1},
        {"student_id": 3, "section": "5-B", "lessons_available": 10, "lessons_completed": 6, "days_since_active": 5, "quiz_average": None, "work_due": 0, "work_submitted": 0},
    ]
    result = signed_post("/v1/learning/engagement", {"students": students}).json()

    ordered = [s["student_id"] for s in result["students"]]
    assert ordered[0] == 2 and ordered[-1] == 1
    assert result["students"][0]["band"] == "low"
    assert "few lessons completed" in result["students"][0]["reasons"]
    assert "not active recently" in result["students"][0]["reasons"]
    assert result["students"][-1]["score"] == 98.0
    assert {b["band"]: b["students"] for b in result["bands"]}["high"] >= 1
    assert result["summary"]["students"] == 3
    assert result["summary"]["inactive_30_days"] == 1


def test_learning_engagement_ignores_signals_that_do_not_apply(signed_post):
    students = [{"student_id": 9, "lessons_available": 4, "lessons_completed": 4, "days_since_active": 0, "work_due": 0, "work_submitted": 0}]
    result = signed_post("/v1/learning/engagement", {"students": students}).json()

    assert result["students"][0]["score"] == 100.0
    assert result["students"][0]["submission"] is None


def test_learning_engagement_empty(signed_post):
    assert signed_post("/v1/learning/engagement", {"students": []}).json()["summary"] is None


def _exam_payload() -> dict:
    # Question 1 separates strong from weak students; question 2 is answered right only by the weakest.
    students = [{"student_id": i, "score": s, "section": "A" if i <= 3 else "B"} for i, s in enumerate([9, 8, 7, 4, 3, 2], start=1)]
    responses = []

    for student in students:
        strong = student["score"] >= 7
        responses.append({"student_id": student["student_id"], "question_id": 10, "marks": 5 if strong else 0})
        responses.append({"student_id": student["student_id"], "question_id": 11, "marks": 5 if student["student_id"] == 6 else 0})

    return {
        "max_score": 10,
        "passing_marks": 5,
        "students": students,
        "questions": [{"question_id": 10, "number": 1, "marks": 5}, {"question_id": 11, "number": 2, "marks": 5}],
        "responses": responses,
    }


def test_exam_analysis_summarises_scores_and_flags_bad_questions(signed_post):
    result = signed_post("/v1/exams/analysis", _exam_payload()).json()

    assert result["summary"]["students"] == 6
    assert result["summary"]["mean"] == 55.0
    assert result["summary"]["pass_rate"] == 50.0

    good, bad = result["items"]
    assert good["number"] == 1 and good["discrimination"] == 1.0 and good["flags"] == []
    assert bad["discrimination"] < 0 and "negative_discrimination" in bad["flags"]
    assert [section["section"] for section in result["sections"]] == ["A", "B"]
    assert result["sections"][0]["pass_rate"] == 100.0


def test_exam_analysis_needs_two_students_and_rejects_bad_input(signed_post):
    single = _exam_payload() | {"students": [{"student_id": 1, "score": 5}]}
    assert signed_post("/v1/exams/analysis", single).json() == {"summary": None, "items": [], "sections": []}

    assert signed_post("/v1/exams/analysis", _exam_payload() | {"max_score": 0}).status_code == 422
