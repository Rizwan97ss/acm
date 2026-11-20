"""Request payloads. Laravel sends pre-aggregated, school-scoped data; nothing here is trusted
beyond its shape, and every list is bounded."""

from datetime import date

from pydantic import BaseModel, Field


class DailyAttendance(BaseModel):
    date: date
    present: int = Field(ge=0)
    absent: int = Field(ge=0)
    late: int = Field(ge=0)
    excused: int = Field(ge=0)


class AttendanceTrendsRequest(BaseModel):
    days: list[DailyAttendance] = Field(max_length=800)


class StudentAttendance(BaseModel):
    student_id: int
    name: str | None = Field(default=None, max_length=200)
    section: str | None = Field(default=None, max_length=100)
    present: int = Field(ge=0)
    absent: int = Field(ge=0)
    late: int = Field(ge=0)
    excused: int = Field(ge=0)
    recent_absences: int = Field(ge=0, default=0)


class AtRiskRequest(BaseModel):
    students: list[StudentAttendance] = Field(max_length=20000)
    threshold: float = Field(default=75.0, ge=0, le=100)


class StudentScore(BaseModel):
    student_id: int
    name: str | None = Field(default=None, max_length=200)
    section: str | None = Field(default=None, max_length=100)
    subject: str = Field(max_length=100)
    percentage: float = Field(ge=0, le=100)
    passed: bool


class ExamScores(BaseModel):
    exam_id: int
    exam: str = Field(max_length=150)
    scores: list[StudentScore] = Field(max_length=100000)


class PerformanceRequest(BaseModel):
    exams: list[ExamScores] = Field(max_length=20)
    attendance: dict[int, float] = Field(default_factory=dict)


class MonthlyFinance(BaseModel):
    month: str = Field(pattern=r"^\d{4}-\d{2}$")
    billed: int = Field(ge=0)
    collected: int = Field(ge=0)
    expenses: int = Field(ge=0)


class OpenInvoice(BaseModel):
    balance: int = Field(ge=0)
    days_overdue: int


class FinanceForecastRequest(BaseModel):
    months: list[MonthlyFinance] = Field(max_length=120)
    open_invoices: list[OpenInvoice] = Field(default_factory=list, max_length=200000)
    horizon: int = Field(default=3, ge=1, le=12)


class ReportRequest(BaseModel):
    title: str = Field(max_length=150)
    attendance: AttendanceTrendsRequest | None = None
    finance: FinanceForecastRequest | None = None


class StudentLearning(BaseModel):
    student_id: int
    section: str | None = Field(default=None, max_length=100)
    lessons_available: int = Field(ge=0)
    lessons_completed: int = Field(ge=0)
    days_since_active: int | None = Field(default=None, ge=0)
    quiz_average: float | None = Field(default=None, ge=0, le=100)
    work_due: int = Field(ge=0, default=0)
    work_submitted: int = Field(ge=0, default=0)


class LearningEngagementRequest(BaseModel):
    students: list[StudentLearning] = Field(max_length=20000)


class ExamStudent(BaseModel):
    student_id: int
    score: float
    section: str | None = Field(default=None, max_length=100)


class ExamQuestion(BaseModel):
    question_id: int
    number: int = Field(ge=1)
    marks: float = Field(gt=0)


class ExamResponse(BaseModel):
    student_id: int
    question_id: int
    marks: float


class ExamAnalysisRequest(BaseModel):
    max_score: float = Field(gt=0)
    passing_marks: float = Field(ge=0)
    students: list[ExamStudent] = Field(max_length=5000)
    questions: list[ExamQuestion] = Field(max_length=500)
    responses: list[ExamResponse] = Field(max_length=500_000)
