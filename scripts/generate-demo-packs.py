#!/usr/bin/env python3
"""Generate deterministic, sanitized School and University institution packs."""

from __future__ import annotations

import csv
import io
import sys
from pathlib import Path
from typing import Iterable


ROOT = Path(__file__).resolve().parents[1]
PACK_ROOT = ROOT / "institution-packs"
PACK_VARIANTS = ("master", "sample")

ROLE_ROWS = [
    ["principal_registrar", "companymanager", "company", "block/iomad_company_admin:company_view_all"],
    ["trustee_management", "companyreporter", "company", "block/iomad_company_admin:view_company_reports"],
    [
        "it_coordinator",
        "institutionitcoordinator",
        "company",
        "block/iomad_company_admin:company_user_update|block/iomad_company_admin:company_user_upload",
    ],
    ["teacher_faculty", "editingteacher", "course", "moodle/course:update"],
    ["student_learner", "student", "course", "moodle/course:view"],
    ["parent_guardian", "parentguardian", "user", "moodle/user:viewdetails"],
    ["hod_dean", "companydepartmentmanager", "company", "block/iomad_company_admin:view_department"],
]

HEADERS = {
    "institutions": ["institution_id", "name", "type", "country", "timezone"],
    "companies": [
        "institution_id",
        "company_shortname",
        "name",
        "parent_company_shortname",
        "city",
        "country",
        "theme",
        "hostname",
    ],
    "domains": ["company_shortname", "domain"],
    "departments": ["company_shortname", "department_shortname", "name", "parent_department_shortname"],
    "academic_years": ["institution_id", "academic_year_shortname", "name", "start_date", "end_date"],
    "boards": ["institution_id", "board_shortname", "name"],
    "mediums": ["institution_id", "medium_shortname", "name"],
    "programmes": ["institution_id", "programme_shortname", "name", "faculty_shortname"],
    "grades": ["institution_id", "grade_shortname", "name"],
    "semesters": ["institution_id", "semester_shortname", "name"],
    "streams": ["institution_id", "stream_shortname", "name"],
    "subjects": ["institution_id", "subject_shortname", "name"],
    "categories": ["category_idnumber", "name", "parent_idnumber", "company_shortname", "category_type"],
    "course_templates": ["template_shortname", "fullname", "category_idnumber", "format", "summary"],
    "courses": [
        "course_shortname",
        "fullname",
        "category_idnumber",
        "company_shortname",
        "department_shortname",
        "template_shortname",
        "format",
        "visible",
        "summary",
    ],
    "users": [
        "user_external_id",
        "username",
        "firstname",
        "lastname",
        "email",
        "company_shortname",
        "department_shortname",
        "role_key",
        "password",
    ],
    "roles": ["role_key", "role_shortname", "context", "capabilities"],
    "cohorts": ["cohort_idnumber", "name", "company_shortname", "description"],
    "groups": ["course_shortname", "group_idnumber", "name", "description"],
    "enrolments": [
        "user_external_id",
        "course_shortname",
        "role_shortname",
        "company_shortname",
        "group_idnumber",
    ],
    "parent_links": ["parent_user_external_id", "learner_user_external_id"],
    "policies": ["policy_key", "company_shortname", "name", "audience", "revision", "summary", "content"],
    "licenses": [
        "license_key",
        "company_shortname",
        "name",
        "allocation",
        "validlength",
        "start_date",
        "expiry_date",
        "type",
        "program",
        "instant",
        "clearonexpire",
        "course_shortnames",
    ],
    "branding": [
        "company_shortname",
        "maincolor",
        "headingcolor",
        "linkcolor",
        "font_family",
        "support_email",
        "customcss",
    ],
}


def write_pack(pack_type: str, data: dict[str, list[list[object]]], check: bool = False) -> list[str]:
    errors = []
    for variant in PACK_VARIANTS:
        target = PACK_ROOT / pack_type / variant
        if not check:
            target.mkdir(parents=True, exist_ok=True)
        for entity, headers in HEADERS.items():
            rows = data.get(entity, [])
            buffer = io.StringIO(newline="")
            writer = csv.writer(buffer, lineterminator="\n")
            writer.writerow(headers)
            writer.writerows(rows)
            path = target / f"{entity}.csv"
            expected = buffer.getvalue()
            if check:
                if not path.is_file() or path.read_text(encoding="utf-8") != expected:
                    errors.append(str(path.relative_to(ROOT)))
            else:
                path.write_text(expected, encoding="utf-8")
    return errors


def school_pack() -> dict[str, list[list[object]]]:
    company = "GV_SCHOOL"
    institution = "SCH_DEMO"
    data: dict[str, list[list[object]]] = {
        "institutions": [[institution, "Demo School", "school", "IN", "Asia/Kolkata"]],
        "companies": [
            [institution, company, "Demo School", "", "Ahmedabad", "IN", "iomad_learning", "school.localhost"]
        ],
        "domains": [[company, "school.localhost"]],
        "departments": [
            [company, "GV_ADMIN", "Administration", ""],
            [company, "GV_PRIMARY", "Primary School", ""],
            [company, "GV_MIDDLE", "Middle School", ""],
            [company, "GV_SECONDARY", "Secondary School", ""],
            [company, "GV_SENIOR", "Senior Secondary School", ""],
            [company, "GV_MATH", "Mathematics Department", "GV_SECONDARY"],
            [company, "GV_SCIENCE", "Science Department", "GV_SECONDARY"],
            [company, "GV_LANG", "Languages Department", "GV_SECONDARY"],
            [company, "GV_SOCIAL", "Social Sciences Department", "GV_SECONDARY"],
            [company, "GV_SPORTS", "Sports and Activities", ""],
            [company, "GV_SUPPORT", "Learner and Parent Support", ""],
        ],
        "academic_years": [
            [institution, "AY_2025_26", "Academic Year 2025-26", "2025-06-01", "2026-04-30"],
            [institution, "AY_2026_27", "Academic Year 2026-27", "2026-06-01", "2027-04-30"],
        ],
        "boards": [
            [institution, "CBSE", "Central Board Demo"],
            [institution, "STATE", "State Board Demo"],
        ],
        "mediums": [
            [institution, "EN", "English"],
            [institution, "HI", "Hindi"],
            [institution, "GU", "Gujarati"],
        ],
        "programmes": [
            [institution, "PRIMARY", "Primary Programme", ""],
            [institution, "SECONDARY", "Secondary Programme", ""],
            [institution, "SENIOR", "Senior Secondary Programme", ""],
        ],
        "grades": [[institution, f"STD_{grade:02}", f"Standard {grade}"] for grade in range(1, 13)],
        "semesters": [
            [institution, "TERM_1", "Term 1"],
            [institution, "TERM_2", "Term 2"],
            [institution, "TERM_3", "Term 3"],
        ],
        "streams": [
            [institution, "SCIENCE", "Science"],
            [institution, "COMMERCE", "Commerce"],
            [institution, "HUMANITIES", "Humanities"],
        ],
        "subjects": [
            [institution, "MATH", "Mathematics"],
            [institution, "ENG", "English"],
            [institution, "EVS", "Environmental Studies"],
            [institution, "SCI", "Science"],
            [institution, "PHY", "Physics"],
            [institution, "CHEM", "Chemistry"],
            [institution, "BIO", "Biology"],
            [institution, "CS", "Computer Science"],
            [institution, "HIST", "History"],
            [institution, "GEO", "Geography"],
            [institution, "PE", "Physical Education"],
            [institution, "ART", "Art and Design"],
        ],
        "roles": ROLE_ROWS,
        "policies": [
            [
                "GV_ACCEPTABLE_USE",
                company,
                "School Acceptable Use",
                "authenticated",
                "2026.1",
                "Acceptable technology use.",
                "<p>Sanitized demonstration policy for acceptable use.</p>",
            ],
            [
                "GV_SAFEGUARDING",
                company,
                "Learner Safeguarding",
                "authenticated",
                "2026.1",
                "Learner safeguarding expectations.",
                "<p>Sanitized demonstration safeguarding policy.</p>",
            ],
            [
                "GV_PRIVACY",
                company,
                "School Privacy Notice",
                "all",
                "2026.1",
                "Privacy and data handling.",
                "<p>Sanitized demonstration privacy notice.</p>",
            ],
            [
                "GV_ACADEMIC_INTEGRITY",
                company,
                "Academic Integrity",
                "authenticated",
                "2026.1",
                "Academic integrity expectations.",
                "<p>Sanitized demonstration academic integrity policy.</p>",
            ],
        ],
        "branding": [[company, "#2454a6", "#1d2433", "#0f7b6c", "Inter", "school-support@example.local", ""]],
    }

    categories = [
        ["GV_AY_2026", "Academic Year 2026-27", "", company, "academic_year"],
        ["GV_CBSE", "CBSE", "GV_AY_2026", company, "board"],
        ["GV_EN", "English Medium", "GV_CBSE", company, "medium"],
    ]
    templates: list[list[object]] = []
    courses: list[list[object]] = []
    groups: list[list[object]] = []
    cohorts: list[list[object]] = []
    enrolments: list[list[object]] = []
    grade_courses: dict[int, list[str]] = {}

    for grade in range(1, 13):
        grade_category = f"GV_STD_{grade:02}"
        categories.append([grade_category, f"Standard {grade}", "GV_EN", company, "standard"])
        cohorts.append(
            [
                f"GV-STD{grade:02}-2026",
                f"Standard {grade} Cohort 2026",
                company,
                f"Sanitized Standard {grade} demonstration cohort.",
            ]
        )
        template = f"GV_TEMPLATE_STD{grade:02}"
        templates.append(
            [
                template,
                f"Standard {grade} course template",
                grade_category,
                "topics",
                f"Sanitized template metadata for Standard {grade}.",
            ]
        )
        third_subject = "EVS" if grade <= 5 else ("SCI" if grade <= 10 else "PHY")
        grade_courses[grade] = []
        for subject_code, subject_name, department in [
            ("MATH", "Mathematics", "GV_MATH"),
            ("ENG", "English", "GV_LANG"),
            (third_subject, {"EVS": "Environmental Studies", "SCI": "Science", "PHY": "Physics"}[third_subject], "GV_SCIENCE"),
        ]:
            category = (
                "GV_STD1_MATH"
                if grade == 1 and subject_code == "MATH"
                else "GV_STD11_PHY"
                if grade == 11 and subject_code == "PHY"
                else f"GV_STD{grade}_{subject_code}"
            )
            categories.append([category, subject_name, grade_category, company, "subject"])
            shortname = f"GV-STD{grade}-{subject_code}-2026"
            course_format = "iomadvideo" if subject_code == third_subject and grade >= 6 else "topics"
            courses.append(
                [
                    shortname,
                    f"Standard {grade} {subject_name} 2026",
                    category,
                    company,
                    department,
                    template,
                    course_format,
                    1,
                    f"Sanitized demonstration course for Standard {grade} {subject_name}.",
                ]
            )
            group_id = f"GV-STD{grade:02}-{subject_code}-A"
            groups.append([shortname, group_id, f"Standard {grade} {subject_code} Section A", "Sanitized class group."])
            grade_courses[grade].append(shortname)
            teacher = ((len(courses) - 1) % 12) + 1
            enrolments.append([f"SCH_TEACHER_{teacher:03}", shortname, "editingteacher", company, group_id])

    categories.append(["GV_ORIENTATION", "School Orientation", "GV_AY_2026", company, "course"])
    templates.append(
        [
            "GV_TEMPLATE_ORIENTATION",
            "School orientation template",
            "GV_ORIENTATION",
            "topics",
            "Sanitized all-learner orientation template metadata.",
        ]
    )
    courses.append(
        [
            "GV-ORIENTATION-2026",
            "School Orientation 2026",
            "GV_ORIENTATION",
            company,
            "GV_SUPPORT",
            "GV_TEMPLATE_ORIENTATION",
            "topics",
            1,
            "Sanitized all-learner course used for feature demonstrations.",
        ]
    )
    groups.append(
        [
            "GV-ORIENTATION-2026",
            "GV-ORIENTATION-A",
            "School Orientation Group",
            "Sanitized all-learner orientation group.",
        ]
    )
    enrolments.append(
        ["SCH_TEACHER_001", "GV-ORIENTATION-2026", "editingteacher", company, "GV-ORIENTATION-A"]
    )

    users = [
        ["SCH_PRINCIPAL_001", "gv.principal", "Principal", "Demo", "gv.principal@example.local", company, "GV_ADMIN", "principal_registrar", ""],
        ["SCH_TRUSTEE_001", "gv.trustee", "Trustee", "Demo", "gv.trustee@example.local", company, "GV_ADMIN", "trustee_management", ""],
        ["SCH_IT_001", "gv.it", "IT", "Coordinator", "gv.it@example.local", company, "GV_ADMIN", "it_coordinator", ""],
    ]
    for index, department in enumerate(["GV_SCIENCE", "GV_MATH", "GV_LANG", "GV_SOCIAL"], start=1):
        username = "gv.hod" if index == 1 else f"gv.hod{index:02}"
        users.append(
            [
                f"SCH_HOD_{index:03}",
                username,
                "HOD",
                f"Department {index}",
                f"{username}@example.local",
                company,
                department,
                "hod_dean",
                "",
            ]
        )
    teacher_departments = ["GV_MATH", "GV_LANG", "GV_SCIENCE"] * 4
    for index, department in enumerate(teacher_departments, start=1):
        username = "gv.teacher" if index == 1 else f"gv.teacher{index:03}"
        users.append(
            [
                f"SCH_TEACHER_{index:03}",
                username,
                "Teacher",
                f"{index:03}",
                f"{username}@example.local",
                company,
                department,
                "teacher_faculty",
                "",
            ]
        )

    parent_links: list[list[object]] = []
    for index in range(1, 101):
        grade = ((index - 1) % 12) + 1
        department = (
            "GV_PRIMARY"
            if grade <= 5
            else "GV_MIDDLE"
            if grade <= 8
            else "GV_SECONDARY"
            if grade <= 10
            else "GV_SENIOR"
        )
        student_username = "gv.student" if index == 1 else f"gv.student{index:03}"
        parent_username = "gv.parent" if index == 1 else f"gv.parent{index:03}"
        users.append(
            [
                f"SCH_STUDENT_{index:03}",
                student_username,
                "Learner",
                f"{index:03}",
                f"{student_username}@example.local",
                company,
                department,
                "student_learner",
                "",
            ]
        )
        users.append(
            [
                f"SCH_PARENT_{index:03}",
                parent_username,
                "Guardian",
                f"{index:03}",
                f"{parent_username}@example.local",
                company,
                "GV_SUPPORT",
                "parent_guardian",
                "",
            ]
        )
        parent_links.append([f"SCH_PARENT_{index:03}", f"SCH_STUDENT_{index:03}"])
        enrolments.append(
            [f"SCH_STUDENT_{index:03}", "GV-ORIENTATION-2026", "student", company, "GV-ORIENTATION-A"]
        )
        for course_shortname in grade_courses[grade]:
            subject_code = course_shortname.split("-")[2]
            group_id = f"GV-STD{grade:02}-{subject_code}-A"
            enrolments.append([f"SCH_STUDENT_{index:03}", course_shortname, "student", company, group_id])

    licenses = []
    for block in range(6):
        selected = []
        for grade in range((block * 2) + 1, (block * 2) + 3):
            selected.extend(grade_courses[grade])
        licenses.append(
            [
                f"GV_GRADE_BLOCK_{block + 1:02}",
                company,
                f"School Grade Block {block + 1}",
                100,
                365,
                "2026-06-01",
                "2027-05-31",
                0,
                0,
                0,
                0,
                "|".join(selected),
            ]
        )

    data.update(
        {
            "categories": categories,
            "course_templates": templates,
            "courses": courses,
            "users": users,
            "cohorts": cohorts,
            "groups": groups,
            "enrolments": enrolments,
            "parent_links": parent_links,
            "licenses": licenses,
        }
    )
    return data


def university_pack() -> dict[str, list[list[object]]]:
    company = "NBU_ENGINEERING"
    institution = "UNI_DEMO"
    data: dict[str, list[list[object]]] = {
        "institutions": [[institution, "Demo University", "university", "IN", "Asia/Kolkata"]],
        "companies": [
            [institution, company, "Demo University", "", "Pune", "IN", "iomad_learning", "university.localhost"]
        ],
        "domains": [[company, "university.localhost"]],
        "departments": [
            [company, "NBU_REGISTRY", "Registry and Admissions", ""],
            [company, "NBU_CSE", "Computer Science and Engineering", ""],
            [company, "NBU_ECE", "Electronics and Communication", ""],
            [company, "NBU_BUSINESS", "Business and Management", ""],
            [company, "NBU_SCIENCE", "Science and Data", ""],
            [company, "NBU_ARTS", "Arts and Humanities", ""],
            [company, "NBU_RESEARCH", "Research and Innovation", ""],
            [company, "NBU_LIBRARY", "Library Services", ""],
            [company, "NBU_STUDENT", "Student Services", ""],
            [company, "NBU_IT", "Information Technology", ""],
        ],
        "academic_years": [
            [institution, "AY_2025_26", "Academic Year 2025-26", "2025-07-01", "2026-06-30"],
            [institution, "AY_2026_27", "Academic Year 2026-27", "2026-07-01", "2027-06-30"],
        ],
        "boards": [
            [institution, "UGC", "University Grants Demo Framework"],
            [institution, "AICTE", "Technical Education Demo Framework"],
        ],
        "mediums": [
            [institution, "EN", "English"],
            [institution, "HI", "Hindi"],
        ],
        "programmes": [
            [institution, "BTECH_CSE", "B.Tech Computer Science", "ENG"],
            [institution, "BTECH_ECE", "B.Tech Electronics", "ENG"],
            [institution, "BBA", "Bachelor of Business Administration", "BUS"],
            [institution, "MBA", "Master of Business Administration", "BUS"],
            [institution, "BSC_DS", "B.Sc Data Science", "SCI"],
            [institution, "MSC_DS", "M.Sc Data Science", "SCI"],
            [institution, "BA_ENG", "B.A. English", "ARTS"],
            [institution, "MA_ENG", "M.A. English", "ARTS"],
        ],
        "grades": [
            [institution, "UG", "Undergraduate"],
            [institution, "PG", "Postgraduate"],
            [institution, "PHD", "Doctoral"],
            [institution, "CERT", "Certificate"],
        ],
        "semesters": [[institution, f"SEM_{semester:02}", f"Semester {semester}"] for semester in range(1, 9)],
        "streams": [
            [institution, "CSE", "Computer Science"],
            [institution, "ECE", "Electronics"],
            [institution, "BUS", "Business"],
            [institution, "DS", "Data Science"],
            [institution, "ARTS", "Arts and Humanities"],
        ],
        "subjects": [
            [institution, "PROG101", "Programming Fundamentals"],
            [institution, "DS101", "Data Structures"],
            [institution, "DB101", "Database Systems"],
            [institution, "WEB101", "Web Engineering"],
            [institution, "EC101", "Circuit Fundamentals"],
            [institution, "BUS101", "Management Foundations"],
            [institution, "ACC101", "Accounting"],
            [institution, "STAT101", "Statistics"],
            [institution, "ML101", "Machine Learning"],
            [institution, "LIT101", "Literature"],
            [institution, "COM101", "Communication"],
            [institution, "RES101", "Research Methods"],
        ],
        "roles": ROLE_ROWS,
        "policies": [
            [
                "NBU_ACADEMIC_INTEGRITY",
                company,
                "University Academic Integrity",
                "authenticated",
                "2026.1",
                "Academic integrity expectations.",
                "<p>Sanitized demonstration academic integrity policy.</p>",
            ],
            [
                "NBU_RESEARCH_ETHICS",
                company,
                "Research Ethics",
                "authenticated",
                "2026.1",
                "Research ethics expectations.",
                "<p>Sanitized demonstration research ethics policy.</p>",
            ],
            [
                "NBU_PRIVACY",
                company,
                "University Privacy Notice",
                "all",
                "2026.1",
                "Privacy and data handling.",
                "<p>Sanitized demonstration privacy notice.</p>",
            ],
            [
                "NBU_ACCEPTABLE_USE",
                company,
                "University Acceptable Use",
                "authenticated",
                "2026.1",
                "Acceptable technology use.",
                "<p>Sanitized demonstration acceptable-use policy.</p>",
            ],
        ],
        "branding": [
            [company, "#3746a0", "#172033", "#0a7d86", "Inter", "university-support@example.local", ""]
        ],
    }

    faculty_definitions = [
        ("ENG", "Faculty of Engineering", "NBU_CSE"),
        ("BUS", "Faculty of Business", "NBU_BUSINESS"),
        ("SCI", "Faculty of Science", "NBU_SCIENCE"),
        ("ARTS", "Faculty of Arts", "NBU_ARTS"),
    ]
    programme_definitions = [
        ("BTECH_CSE", "B.Tech Computer Science", "ENG", "NBU_CSE"),
        ("BTECH_ECE", "B.Tech Electronics", "ENG", "NBU_ECE"),
        ("BBA", "Bachelor of Business Administration", "BUS", "NBU_BUSINESS"),
        ("MBA", "Master of Business Administration", "BUS", "NBU_BUSINESS"),
        ("BSC_DS", "B.Sc Data Science", "SCI", "NBU_SCIENCE"),
        ("MSC_DS", "M.Sc Data Science", "SCI", "NBU_SCIENCE"),
        ("BA_ENG", "B.A. English", "ARTS", "NBU_ARTS"),
        ("MA_ENG", "M.A. English", "ARTS", "NBU_ARTS"),
    ]
    course_definitions = {
        "BTECH_CSE": [
            ("NBU-PROG101-2026", "Programming Fundamentals 2026", "PROG101"),
            ("NBU-DS101-2026", "Data Structures 2026", "DS101"),
            ("NBU-DB101-2026", "Database Systems 2026", "DB101"),
            ("NBU-WEB101-2026", "Web Engineering 2026", "WEB101"),
        ],
        "BTECH_ECE": [
            ("NBU-EC101-2026", "Circuit Fundamentals 2026", "EC101"),
            ("NBU-EC102-2026", "Digital Systems 2026", "EC102"),
            ("NBU-EC103-2026", "Signals and Systems 2026", "EC103"),
            ("NBU-EC104-2026", "Embedded Systems 2026", "EC104"),
        ],
        "BBA": [
            ("NBU-BUS101-2026", "Management Foundations 2026", "BUS101"),
            ("NBU-ACC101-2026", "Accounting 2026", "ACC101"),
            ("NBU-MKT101-2026", "Marketing 2026", "MKT101"),
            ("NBU-ECO101-2026", "Economics 2026", "ECO101"),
        ],
        "MBA": [
            ("NBU-MBA501-2026", "Strategic Management 2026", "MBA501"),
            ("NBU-MBA502-2026", "Corporate Finance 2026", "MBA502"),
            ("NBU-MBA503-2026", "Operations Management 2026", "MBA503"),
            ("NBU-MBA504-2026", "Leadership 2026", "MBA504"),
        ],
        "BSC_DS": [
            ("NBU-STAT101-2026", "Statistics 2026", "STAT101"),
            ("NBU-ML101-2026", "Machine Learning 2026", "ML101"),
            ("NBU-DV101-2026", "Data Visualization 2026", "DV101"),
            ("NBU-PY101-2026", "Python for Data Science 2026", "PY101"),
        ],
        "MSC_DS": [
            ("NBU-ADS501-2026", "Advanced Data Science 2026", "ADS501"),
            ("NBU-AI501-2026", "Applied Artificial Intelligence 2026", "AI501"),
            ("NBU-BD501-2026", "Big Data Systems 2026", "BD501"),
            ("NBU-RES501-2026", "Research Methods 2026", "RES501"),
        ],
        "BA_ENG": [
            ("NBU-LIT101-2026", "World Literature 2026", "LIT101"),
            ("NBU-COM101-2026", "Academic Communication 2026", "COM101"),
            ("NBU-LIN101-2026", "Linguistics 2026", "LIN101"),
            ("NBU-CW101-2026", "Creative Writing 2026", "CW101"),
        ],
        "MA_ENG": [
            ("NBU-LIT501-2026", "Literary Theory 2026", "LIT501"),
            ("NBU-CUL501-2026", "Cultural Studies 2026", "CUL501"),
            ("NBU-RES502-2026", "Humanities Research 2026", "RES502"),
            ("NBU-DIG501-2026", "Digital Humanities 2026", "DIG501"),
        ],
    }

    categories = [["NBU_AY_2026", "Academic Year 2026-27", "", company, "academic_year"]]
    for faculty_code, faculty_name, _ in faculty_definitions:
        faculty_category = "NBU_ENG" if faculty_code == "ENG" else f"NBU_{faculty_code}"
        categories.append([faculty_category, faculty_name, "NBU_AY_2026", company, "faculty"])

    templates: list[list[object]] = []
    courses: list[list[object]] = []
    groups: list[list[object]] = []
    cohorts: list[list[object]] = []
    enrolments: list[list[object]] = []
    programme_courses: dict[str, list[str]] = {}
    course_index = 0

    for programme_code, programme_name, faculty_code, department in programme_definitions:
        faculty_category = "NBU_ENG" if faculty_code == "ENG" else f"NBU_{faculty_code}"
        programme_category = "NBU_BTECH_CSE" if programme_code == "BTECH_CSE" else f"NBU_{programme_code}"
        semester_category = (
            "NBU_BTECH_CSE_SEM1" if programme_code == "BTECH_CSE" else f"NBU_{programme_code}_SEM1"
        )
        categories.append([programme_category, programme_name, faculty_category, company, "programme"])
        categories.append([semester_category, "Semester 1", programme_category, company, "semester"])
        template = f"NBU_TEMPLATE_{programme_code}"
        templates.append(
            [
                template,
                f"{programme_name} semester template",
                semester_category,
                "topics",
                f"Sanitized template metadata for {programme_name}.",
            ]
        )
        cohorts.append(
            [
                f"NBU-{programme_code}-2026",
                f"{programme_name} Cohort 2026",
                company,
                f"Sanitized {programme_name} demonstration cohort.",
            ]
        )
        programme_courses[programme_code] = []
        for shortname, fullname, subject_code in course_definitions[programme_code]:
            course_category = (
                "NBU_PROG101"
                if shortname == "NBU-PROG101-2026"
                else "NBU_DS101"
                if shortname == "NBU-DS101-2026"
                else f"NBU_{subject_code}"
            )
            categories.append([course_category, fullname.removesuffix(" 2026"), semester_category, company, "course"])
            course_format = "iomadvideo" if course_index % 4 == 1 else "topics"
            courses.append(
                [
                    shortname,
                    fullname,
                    course_category,
                    company,
                    department,
                    template,
                    course_format,
                    1,
                    f"Sanitized demonstration course for {fullname}.",
                ]
            )
            group_id = f"{shortname.removesuffix('-2026')}-A"
            groups.append([shortname, group_id, f"{fullname.removesuffix(' 2026')} Section A", "Sanitized class group."])
            programme_courses[programme_code].append(shortname)
            faculty_index = (course_index % 16) + 1
            enrolments.append([f"UNI_FAC_{faculty_index:03}", shortname, "editingteacher", company, group_id])
            course_index += 1

    categories.append(["NBU_ORIENTATION", "University Orientation", "NBU_AY_2026", company, "course"])
    templates.append(
        [
            "NBU_TEMPLATE_ORIENTATION",
            "University orientation template",
            "NBU_ORIENTATION",
            "topics",
            "Sanitized all-learner orientation template metadata.",
        ]
    )
    courses.append(
        [
            "NBU-ORIENTATION-2026",
            "University Orientation 2026",
            "NBU_ORIENTATION",
            company,
            "NBU_STUDENT",
            "NBU_TEMPLATE_ORIENTATION",
            "topics",
            1,
            "Sanitized all-learner course used for feature demonstrations.",
        ]
    )
    groups.append(
        [
            "NBU-ORIENTATION-2026",
            "NBU-ORIENTATION-A",
            "University Orientation Group",
            "Sanitized all-learner orientation group.",
        ]
    )
    enrolments.append(
        ["UNI_FAC_001", "NBU-ORIENTATION-2026", "editingteacher", company, "NBU-ORIENTATION-A"]
    )

    users = [
        ["UNI_REG_001", "nbu.registrar", "Registrar", "Demo", "nbu.registrar@example.local", company, "NBU_REGISTRY", "principal_registrar", ""],
        ["UNI_TRUSTEE_001", "nbu.trustee", "Trustee", "Demo", "nbu.trustee@example.local", company, "NBU_REGISTRY", "trustee_management", ""],
        ["UNI_IT_001", "nbu.it", "IT", "Coordinator", "nbu.it@example.local", company, "NBU_IT", "it_coordinator", ""],
    ]
    dean_departments = ["NBU_CSE", "NBU_ECE", "NBU_BUSINESS", "NBU_SCIENCE", "NBU_ARTS"]
    for index, department in enumerate(dean_departments, start=1):
        username = "nbu.dean" if index == 1 else f"nbu.dean{index:02}"
        users.append(
            [
                f"UNI_DEAN_{index:03}",
                username,
                "Dean",
                f"Faculty {index}",
                f"{username}@example.local",
                company,
                department,
                "hod_dean",
                "",
            ]
        )
    faculty_departments = [definition[3] for definition in programme_definitions] * 2
    for index, department in enumerate(faculty_departments, start=1):
        username = "nbu.faculty" if index == 1 else f"nbu.faculty{index:03}"
        users.append(
            [
                f"UNI_FAC_{index:03}",
                username,
                "Faculty",
                f"{index:03}",
                f"{username}@example.local",
                company,
                department,
                "teacher_faculty",
                "",
            ]
        )

    student_programmes = ["BTECH_CSE", "BTECH_ECE", "BBA", "BSC_DS"]
    parent_links: list[list[object]] = []
    for guardian in range(1, 51):
        username = f"nbu.mentor{guardian:03}"
        users.append(
            [
                f"UNI_GUARDIAN_{guardian:03}",
                username,
                "Mentor",
                f"{guardian:03}",
                f"{username}@example.local",
                company,
                "NBU_STUDENT",
                "parent_guardian",
                "",
            ]
        )
    for index in range(1, 101):
        programme = student_programmes[(index - 1) % len(student_programmes)]
        department = next(item[3] for item in programme_definitions if item[0] == programme)
        username = "nbu.student" if index == 1 else f"nbu.student{index:03}"
        users.append(
            [
                f"UNI_STU_{index:03}",
                username,
                "Student",
                f"{index:03}",
                f"{username}@example.local",
                company,
                department,
                "student_learner",
                "",
            ]
        )
        guardian = ((index - 1) % 50) + 1
        parent_links.append([f"UNI_GUARDIAN_{guardian:03}", f"UNI_STU_{index:03}"])
        enrolments.append(
            [f"UNI_STU_{index:03}", "NBU-ORIENTATION-2026", "student", company, "NBU-ORIENTATION-A"]
        )
        for course_shortname in programme_courses[programme]:
            group_id = f"{course_shortname.removesuffix('-2026')}-A"
            enrolments.append([f"UNI_STU_{index:03}", course_shortname, "student", company, group_id])

    licenses = []
    for index, (programme_code, programme_name, _, _) in enumerate(programme_definitions, start=1):
        licenses.append(
            [
                f"NBU_{programme_code}_LICENSE",
                company,
                f"{programme_name} Demonstration License",
                100,
                365,
                "2026-07-01",
                "2027-06-30",
                0,
                1,
                0,
                0,
                "|".join(programme_courses[programme_code]),
            ]
        )

    data.update(
        {
            "categories": categories,
            "course_templates": templates,
            "courses": courses,
            "users": users,
            "cohorts": cohorts,
            "groups": groups,
            "enrolments": enrolments,
            "parent_links": parent_links,
            "licenses": licenses,
        }
    )
    return data


def row_count(data: dict[str, Iterable[object]]) -> int:
    return sum(len(list(rows)) for rows in data.values())


def main() -> None:
    school = school_pack()
    university = university_pack()
    check = "--check" in sys.argv[1:]
    unknown = [argument for argument in sys.argv[1:] if argument != "--check"]
    if unknown:
        raise SystemExit(f"Unknown arguments: {' '.join(unknown)}")
    errors = write_pack("school", school, check) + write_pack("university", university, check)
    if errors:
        print("Generated demo packs are stale:", file=sys.stderr)
        for path in errors:
            print(f"  {path}", file=sys.stderr)
        raise SystemExit(1)
    verb = "Validated" if check else "Generated"
    print(
        f"{verb} sanitized institution packs: "
        f"school={row_count(school)} rows, university={row_count(university)} rows."
    )


if __name__ == "__main__":
    main()
