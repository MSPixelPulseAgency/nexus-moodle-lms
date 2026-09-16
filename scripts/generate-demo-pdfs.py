#!/usr/bin/env python3
"""Generate original Nexus MHF4U assignment and fictional submission PDFs."""

from __future__ import annotations

from pathlib import Path
from typing import Iterable

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
from reportlab.platypus import (
    BaseDocTemplate,
    Flowable,
    Frame,
    KeepTogether,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[1]
ASSET_ROOT = ROOT / "local-nexusdemodata" / "assets"
ASSIGNMENT_DIR = ASSET_ROOT / "assignments"
SUBMISSION_DIR = ASSET_ROOT / "submissions"
LOGO = ROOT / "moodle" / "local" / "nexusbranding" / "pix" / "logo.png"

NAVY = colors.HexColor("#071B34")
BLUE = colors.HexColor("#1F5FAF")
GOLD = colors.HexColor("#C79A3B")
INK = colors.HexColor("#17243A")
SLATE = colors.HexColor("#536176")
PALE = colors.HexColor("#EEF4FA")
LINE = colors.HexColor("#CBD6E3")
WHITE = colors.white


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="NexusTitle", parent=styles["Title"], fontName="Helvetica-Bold", fontSize=23, leading=27, textColor=NAVY, spaceAfter=8))
styles.add(ParagraphStyle(name="NexusSubtitle", parent=styles["Heading2"], fontName="Helvetica", fontSize=11, leading=15, textColor=SLATE, spaceAfter=12))
styles.add(ParagraphStyle(name="Section", parent=styles["Heading2"], fontName="Helvetica-Bold", fontSize=14, leading=18, textColor=NAVY, spaceBefore=10, spaceAfter=6))
styles.add(ParagraphStyle(name="Question", parent=styles["BodyText"], fontName="Helvetica", fontSize=10.4, leading=14.5, textColor=INK, spaceAfter=5))
styles.add(ParagraphStyle(name="Small", parent=styles["BodyText"], fontName="Helvetica", fontSize=8.4, leading=11, textColor=SLATE))
styles.add(ParagraphStyle(name="BodyNexus", parent=styles["BodyText"], fontName="Helvetica", fontSize=10, leading=14, textColor=INK, spaceAfter=6))
styles.add(ParagraphStyle(name="Answer", parent=styles["BodyText"], fontName="Helvetica", fontSize=10, leading=15, textColor=INK, spaceAfter=8))
styles.add(ParagraphStyle(name="CoverCode", parent=styles["Title"], fontName="Helvetica-Bold", fontSize=28, leading=32, textColor=WHITE, alignment=TA_CENTER))
styles.add(ParagraphStyle(name="CoverName", parent=styles["BodyText"], fontName="Helvetica", fontSize=12, leading=16, textColor=WHITE, alignment=TA_CENTER))


class Rule(Flowable):
    def __init__(self, width: float, color=LINE, thickness: float = 0.7):
        super().__init__()
        self.width = width
        self.height = thickness + 2
        self.color = color
        self.thickness = thickness

    def draw(self):
        self.canv.setStrokeColor(self.color)
        self.canv.setLineWidth(self.thickness)
        self.canv.line(0, 1, self.width, 1)


def page_chrome(canvas, doc):
    canvas.saveState()
    width, height = letter
    canvas.setFillColor(NAVY)
    canvas.rect(0, height - 0.46 * inch, width, 0.46 * inch, stroke=0, fill=1)
    if LOGO.is_file():
        canvas.drawImage(str(LOGO), 0.54 * inch, height - 0.37 * inch, width=1.46 * inch, height=0.25 * inch, preserveAspectRatio=True, anchor="w", mask="auto")
    canvas.setFillColor(colors.HexColor("#DCE7F3"))
    canvas.setFont("Helvetica", 7.5)
    canvas.drawRightString(width - 0.55 * inch, height - 0.29 * inch, "NEXUS EDUCATION PRIVATE SCHOOL | MHF4U")
    canvas.setStrokeColor(GOLD)
    canvas.setLineWidth(2)
    canvas.line(0.54 * inch, 0.47 * inch, width - 0.54 * inch, 0.47 * inch)
    canvas.setFillColor(SLATE)
    canvas.setFont("Helvetica", 7.5)
    canvas.drawString(0.54 * inch, 0.28 * inch, "Original educational demo material - not a student record")
    canvas.drawRightString(width - 0.54 * inch, 0.28 * inch, f"Page {doc.page}")
    canvas.restoreState()


def document(path: Path) -> BaseDocTemplate:
    path.parent.mkdir(parents=True, exist_ok=True)
    doc = BaseDocTemplate(
        str(path),
        pagesize=letter,
        leftMargin=0.62 * inch,
        rightMargin=0.62 * inch,
        topMargin=0.72 * inch,
        bottomMargin=0.68 * inch,
        title=path.stem.replace("-", " ").title(),
        author="Nexus Education Private School",
        subject="MHF4U original educational demo material",
    )
    frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="content")
    doc.addPageTemplates([PageTemplate(id="nexus", frames=[frame], onPage=page_chrome)])
    return doc


def metadata_table(total_marks: int, due_text: str) -> Table:
    data = [
        [Paragraph("<b>Student name</b><br/>________________________________", styles["BodyNexus"]), Paragraph("<b>Date</b><br/>________________________", styles["BodyNexus"])],
        [Paragraph(f"<b>Total marks</b><br/>{total_marks}", styles["BodyNexus"]), Paragraph(f"<b>Suggested due date</b><br/>{due_text}", styles["BodyNexus"])],
    ]
    table = Table(data, colWidths=[3.7 * inch, 3.0 * inch], rowHeights=[0.58 * inch, 0.50 * inch])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), PALE),
        ("BOX", (0, 0), (-1, -1), 0.8, LINE),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
    ]))
    return table


def goals(items: Iterable[str]) -> Table:
    rows = [[Paragraph("Learning goals", styles["Section"])]]
    rows.extend([[Paragraph(f"- {item}", styles["BodyNexus"])]] for item in items)
    table = Table(rows, colWidths=[6.7 * inch])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
        ("BACKGROUND", (0, 1), (-1, -1), colors.HexColor("#F7FAFD")),
        ("BOX", (0, 0), (-1, -1), 0.8, LINE),
        ("LEFTPADDING", (0, 0), (-1, -1), 12),
        ("RIGHTPADDING", (0, 0), (-1, -1), 12),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]))
    return table


def question(number: int, text: str, marks: int, lines: int = 4) -> list:
    header = Table(
        [[Paragraph(f"<b>{number}.</b> {text}", styles["Question"]), Paragraph(f"<b>/{marks}</b>", styles["Question"])]],
        colWidths=[6.20 * inch, 0.50 * inch],
    )
    header.setStyle(TableStyle([("VALIGN", (0, 0), (-1, -1), "TOP"), ("ALIGN", (-1, 0), (-1, -1), "RIGHT")]))
    output = [header]
    for _ in range(lines):
        output.extend([Spacer(1, 0.14 * inch), Rule(6.55 * inch, colors.HexColor("#DCE4ED"), 0.5)])
    output.append(Spacer(1, 0.12 * inch))
    return output


ASSIGNMENTS = [
    {
        "slug": "assignment-1-function-transformations",
        "title": "Assignment 1 - Function Transformations",
        "total": 40,
        "due": "September 18, 2026 at 11:59 p.m. ET",
        "goals": [
            "connect equations, graphs, domains, and ranges for transformed functions",
            "communicate transformations using correct parameters and notation",
            "justify inverse-function and restriction decisions",
        ],
        "instructions": "Show all reasoning. Label graphs with scales and key features. You may use graphing technology to verify work, but your submitted solution must include original algebraic reasoning.",
        "questions": [
            ("For g(x) = -2(x - 3)^2 + 5, describe every transformation from y = x^2. State the vertex, axis of symmetry, domain, and range.", 7, 5),
            ("Sketch h(x) = 3|x + 2| - 4 using a table of at least five points. State both intercepts and the range.", 7, 6),
            ("The graph of y = 1/x is transformed to r(x) = -4/[2(x - 1)] + 3. Identify a, k, d, and c, then state both asymptotes.", 7, 5),
            ("Find the inverse of f(x) = (3x - 7)/5. Verify the result by composition and state the domain and range of each function.", 7, 5),
            ("Create an original transformed function whose graph has a maximum at (2, 6), passes through (4, -2), and opens downward. Determine an equation and justify it.", 6, 5),
            ("Reflection: explain one common transformation error and show how a point-mapping check prevents it.", 6, 5),
        ],
    },
    {
        "slug": "assignment-2-polynomial-rational-functions",
        "title": "Assignment 2 - Polynomial and Rational Functions",
        "total": 50,
        "due": "September 25, 2026 at 11:59 p.m. ET",
        "goals": [
            "use factoring and theorems to analyze polynomial functions",
            "distinguish holes, restrictions, and asymptotes in rational functions",
            "build and critique mathematical models with stated limitations",
        ],
        "instructions": "Factor completely before interpreting graph features. Keep restrictions from the original expression. Include labelled sketches where requested and check candidate solutions in the original equation.",
        "questions": [
            ("Analyze P(x) = -(x + 2)(x - 1)^2(x - 4). State degree, leading coefficient, zeros, multiplicities, end behaviour, and y-intercept.", 8, 6),
            ("Use the factor theorem to determine whether x - 3 is a factor of 2x^3 - 5x^2 - 4x + 3. Explain the conclusion.", 6, 4),
            ("Factor x^3 + 2x^2 - 9x - 18 and use the result to sketch a graph with all intercepts and end behaviour labelled.", 8, 6),
            ("For R(x) = (x^2 - x - 6)/(x^2 - 4), state restrictions, any hole, vertical asymptotes, horizontal asymptote, and intercepts.", 10, 7),
            ("Solve 2/(x - 1) = 3/(x + 2). State restrictions first and verify the final solution.", 6, 5),
            ("A rectangular open-top display tray has volume V(x) = x(30 - 2x)(22 - 2x). State a realistic domain and explain two limitations of this polynomial model.", 6, 5),
            ("Compare the graph behaviour at a polynomial zero of even multiplicity with a removable discontinuity in a rational function.", 6, 5),
        ],
    },
    {
        "slug": "assignment-3-exp-log-trig-applications",
        "title": "Assignment 3 - Exponential, Logarithmic and Trigonometric Applications",
        "total": 45,
        "due": "October 2, 2026 at 11:59 p.m. ET",
        "goals": [
            "solve exponential and logarithmic equations with valid-domain checks",
            "interpret periodic parameters in sinusoidal models",
            "evaluate assumptions and units in applied function models",
        ],
        "instructions": "Use exact values when practical and round final measured quantities to the requested precision. Define every variable and include units. Check logarithmic domains before accepting solutions.",
        "questions": [
            ("A savings balance is modelled by A(t) = 1800(1.045)^t, where t is years. Find A(6), then use logarithms to determine when the balance first exceeds $2500.", 8, 6),
            ("Solve log_2(x - 1) + log_2(x + 1) = 3. State the domain and reject any invalid roots.", 7, 5),
            ("A medication amount follows M(t) = 120(0.82)^t. Find the half-life to the nearest tenth of an hour and interpret the answer.", 7, 5),
            ("For y = 3cos(2(x - pi/4)) - 1, state amplitude, period, phase shift, midline, maximum, and minimum. Sketch one cycle.", 8, 7),
            ("Daylight is modelled by D(t) = 3.1sin[(2pi/365)(t - 80)] + 12.2. Interpret every parameter and predict daylight on day 172.", 8, 6),
            ("Compare one strength and one limitation of exponential and sinusoidal models in real Canadian contexts.", 7, 5),
        ],
    },
]


def build_assignment(spec: dict) -> Path:
    path = ASSIGNMENT_DIR / f"{spec['slug']}.pdf"
    doc = document(path)
    story = [
        Paragraph("MHF4U - Advanced Functions", styles["NexusSubtitle"]),
        Paragraph(spec["title"], styles["NexusTitle"]),
        metadata_table(spec["total"], spec["due"]),
        Spacer(1, 0.18 * inch),
        goals(spec["goals"]),
        Spacer(1, 0.14 * inch),
        Paragraph("Submission instructions", styles["Section"]),
        Paragraph(spec["instructions"], styles["BodyNexus"]),
        Paragraph("Submit one readable PDF through the matching Moodle assignment. Include your name, page numbers, and complete reasoning. Do not submit shared links or temporary draft-file URLs.", styles["BodyNexus"]),
        Spacer(1, 0.10 * inch),
    ]
    for index, (text, marks, lines) in enumerate(spec["questions"], start=1):
        story.append(KeepTogether(question(index, text, marks, lines)))
    story.extend([
        Paragraph("Completion check", styles["Section"]),
        Paragraph("Before submitting: confirm every restriction is stated, graphs are labelled, units are included, and the total PDF is readable at 100% zoom.", styles["BodyNexus"]),
    ])
    doc.build(story)
    return path


SUBMISSIONS = [
    {
        "file": "ethan-campbell-assignment-1.pdf",
        "student": "Ethan Campbell",
        "assignment": "Assignment 1 - Function Transformations",
        "submitted": "September 16, 2026",
        "answers": [
            ("Question 1", "For g(x) = -2(x - 3)^2 + 5, I move the parent function right 3 and up 5, reflect it across the x-axis, and apply a vertical stretch of 2. The vertex is (3, 5), the axis is x = 3, the domain is all real x, and the range is y <= 5."),
            ("Question 2", "My table for h(x) = 3|x + 2| - 4 uses x = -4, -3, -2, -1, 0 and gives y = 2, -1, -4, -1, 2. The vertex is (-2, -4). The y-intercept is (0, 2), and solving 3|x + 2| = 4 gives x = -2 +/- 4/3."),
            ("Question 4", "Starting with y = (3x - 7)/5, I switch x and y: x = (3y - 7)/5. Then 5x = 3y - 7, so y = (5x + 7)/3. Substitution gives f(f^-1(x)) = x."),
        ],
    },
    {
        "file": "olivia-bennett-assignment-1.pdf",
        "student": "Olivia Bennett",
        "assignment": "Assignment 1 - Function Transformations",
        "submitted": "September 16, 2026",
        "answers": [
            ("Question 1", "The negative leading factor reflects the parabola and makes it narrower. I used the point mapping (x, y) -> (x + 3, -2y + 5). The parent points (-1,1), (0,0), and (1,1) become (2,3), (3,5), and (4,3)."),
            ("Question 3", "For r(x) = -4/[2(x - 1)] + 3, I simplify the scale to -2/(x - 1) + 3. Relative to 1/x: a = -4, k = 2, d = 1, c = 3. The vertical asymptote is x = 1 and the horizontal asymptote is y = 3."),
            ("Question 5", "I used y = a(x - 2)^2 + 6 and substituted (4, -2): -2 = 4a + 6, so a = -2. The function y = -2(x - 2)^2 + 6 opens downward, has maximum (2,6), and passes through (4,-2)."),
        ],
    },
    {
        "file": "olivia-bennett-assignment-2.pdf",
        "student": "Olivia Bennett",
        "assignment": "Assignment 2 - Polynomial and Rational Functions",
        "submitted": "September 16, 2026",
        "answers": [
            ("Question 1", "P has degree 4 and leading coefficient -1. The zeros are -2 (multiplicity 1), 1 (multiplicity 2), and 4 (multiplicity 1). Both ends fall. P(0) = -8, so the y-intercept is (0,-8)."),
            ("Question 4", "R(x) factors to (x - 3)(x + 2)/[(x - 2)(x + 2)]. The original restrictions are x != -2 and x != 2. There is a hole at (-2, 5/4), a vertical asymptote at x = 2, a horizontal asymptote at y = 1, x-intercept (3,0), and y-intercept (0,3/2)."),
            ("Question 5", "Restrictions: x != 1,-2. Cross-multiplying gives 2(x + 2) = 3(x - 1), so 2x + 4 = 3x - 3 and x = 7. Substitution gives 2/6 = 3/9 = 1/3, so it is valid."),
        ],
    },
    {
        "file": "liam-foster-assignment-2.pdf",
        "student": "Liam Foster",
        "assignment": "Assignment 2 - Polynomial and Rational Functions",
        "submitted": "September 16, 2026",
        "answers": [
            ("Question 2", "For F(x) = 2x^3 - 5x^2 - 4x + 3, F(3) = 54 - 45 - 12 + 3 = 0. By the factor theorem, x - 3 is a factor."),
            ("Question 3", "Grouping gives x^2(x + 2) - 9(x + 2) = (x + 2)(x - 3)(x + 3). The intercepts are x = -3,-2,3 and y = -18. Since the leading coefficient is positive and the degree is odd, the graph falls left and rises right."),
            ("Question 6", "The dimensions require x > 0, 30 - 2x > 0, and 22 - 2x > 0, so 0 < x < 11. The model ignores material thickness and assumes perfectly square cuts and folds."),
        ],
    },
    {
        "file": "chloe-martin-assignment-1.pdf",
        "student": "Chloe Martin",
        "assignment": "Assignment 1 - Function Transformations",
        "submitted": "September 16, 2026",
        "answers": [
            ("Question 2", "The absolute-value graph has vertex (-2,-4) and opens upward with slopes +/-3. My plotted points are (-4,2), (-3,-1), (-2,-4), (-1,-1), and (0,2). The range is y >= -4."),
            ("Question 4", "I found f^-1(x) = (5x + 7)/3. Both f and its inverse are linear with non-zero slope, so their domains and ranges are all real numbers."),
            ("Question 6", "A common error is treating x - d as a shift left. Testing the parent vertex x = 0 shows that replacing x with x - 3 moves the feature to x = 3, so the shift is right."),
        ],
    },
    {
        "file": "chloe-martin-assignment-3.pdf",
        "student": "Chloe Martin",
        "assignment": "Assignment 3 - Exponential, Logarithmic and Trigonometric Applications",
        "submitted": "September 16, 2026",
        "answers": [
            ("Question 1", "A(6) = 1800(1.045)^6 = 2344.05. For the target, 2500/1800 = 1.045^t, so t = ln(2500/1800)/ln(1.045) = 7.47 years. The balance first exceeds $2500 after about 7.5 years."),
            ("Question 2", "The domain is x > 1. Combining logs gives log_2(x^2 - 1) = 3, so x^2 - 1 = 8 and x = +/-3. Only x = 3 is in the domain."),
            ("Question 4", "Amplitude 3, period pi, shift pi/4 right, midline y = -1, maximum 2, and minimum -4. One cycle runs from x = pi/4 to x = 5pi/4."),
            ("Question 6", "Exponential models are strong for proportional change but can become unrealistic when resources are limited. Sinusoidal models show repeating seasonal patterns but assume a stable period and amplitude."),
        ],
    },
]


def build_submission(spec: dict) -> Path:
    path = SUBMISSION_DIR / spec["file"]
    doc = document(path)
    story = [
        Table([[Paragraph("FICTIONAL DEMO SUBMISSION", styles["CoverCode"])], [Paragraph("For LMS grading-workflow demonstration only", styles["CoverName"])]], colWidths=[6.7 * inch], style=TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), NAVY),
            ("BOX", (0, 0), (-1, -1), 1, GOLD),
            ("TOPPADDING", (0, 0), (-1, 0), 16),
            ("BOTTOMPADDING", (0, 0), (-1, 0), 5),
            ("TOPPADDING", (0, 1), (-1, 1), 3),
            ("BOTTOMPADDING", (0, 1), (-1, 1), 16),
        ])),
        Spacer(1, 0.22 * inch),
        Paragraph(spec["assignment"], styles["NexusTitle"]),
        Table([
            [Paragraph("<b>Student</b>", styles["Small"]), Paragraph(spec["student"], styles["BodyNexus"])],
            [Paragraph("<b>Course</b>", styles["Small"]), Paragraph("MHF4U - Advanced Functions", styles["BodyNexus"])],
            [Paragraph("<b>Submitted</b>", styles["Small"]), Paragraph(spec["submitted"], styles["BodyNexus"])],
            [Paragraph("<b>Status</b>", styles["Small"]), Paragraph("Submitted for grading - demo record", styles["BodyNexus"])],
        ], colWidths=[1.1 * inch, 5.6 * inch], style=TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), PALE),
            ("BOX", (0, 0), (-1, -1), 0.7, LINE),
            ("INNERGRID", (0, 0), (-1, -1), 0.4, LINE),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("LEFTPADDING", (0, 0), (-1, -1), 9),
            ("TOPPADDING", (0, 0), (-1, -1), 7),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ])),
        Spacer(1, 0.18 * inch),
        Paragraph("Selected responses", styles["Section"]),
        Paragraph("These original responses are intentionally concise and are provided only to demonstrate Moodle submission, download, and grading workflows.", styles["BodyNexus"]),
    ]
    for heading, answer in spec["answers"]:
        story.append(KeepTogether([
            Paragraph(heading, styles["Section"]),
            Paragraph(answer, styles["Answer"]),
            Rule(6.55 * inch),
        ]))
    doc.build(story)
    return path


def main() -> None:
    if not LOGO.is_file():
        raise SystemExit(f"Brand logo not found: {LOGO}")
    outputs = [build_assignment(spec) for spec in ASSIGNMENTS]
    outputs.extend(build_submission(spec) for spec in SUBMISSIONS)
    for output in outputs:
        print(output.relative_to(ROOT))


if __name__ == "__main__":
    main()
