# School Management in Tenant Master

All normal school setup and annual operations are available at:

`Site administration > Tenant Master`

Tenant Master stores school-specific intent and projects operational records
through supported Moodle and IOMAD APIs. Users, companies, departments,
categories, courses, cohorts, groups, roles and enrolments remain native
records.

## Data Mapping

| School concept | Managed record |
|---|---|
| Trust with one school | One IOMAD company and one Tenant Master profile |
| Independently administered trust schools | Parent company with child school companies |
| Campus or administrative unit | IOMAD department |
| Academic year | Native course-category root |
| Board, medium, grade and stream | Year-scoped course categories |
| Subject | Native Moodle course assigned to the IOMAD company |
| Division | Class cohort plus a Separate Groups group in each subject course |
| Student | Native Moodle user and IOMAD company member |
| Teacher | Native company educator and explicit course teacher |
| Principal | Native company manager |
| Parent | Native user with an explicit learner-context guardian role |

Departments and academic course categories are intentionally separate.

## Initial Setup Sequence

1. Open **Tenants** and create the school company. Use one stable
   `trust_code`; do not create separate trust and school companies for the same
   business record.
2. Open **Institution profile** and enter the school, trust, UDISE, board
   affiliation, recognition, academic-session, location and branding fields.
   Do not store Aadhaar, bank, salary or sensitive HR documents.
3. Open **Organisation** and create campuses and departments such as
   Administration, Primary, Secondary, Science and Languages.
4. Open **Academic structure**, create the academic year and mark it current.
5. Review shared boards, mediums, grades, streams, divisions and subjects.
6. Use **School year setup** to select a board, medium, one or more grades,
   optional stream and subjects. Tenant Master creates:

   `Academic year > Board > Medium > Grade > optional Stream > Subject course`

7. Run **Sync all**, or allow automatic synchronization and cron to complete.
8. Open **Courses** and copy approved content from a template or previous-year
   course into each empty year-specific course. The copy excludes users,
   enrolments, roles, grades, completion, logs and other learner history.
9. Open **Users and roles** to create native principals, HODs, teachers,
   students and guardians. A student or teacher can be created before a course
   is assigned.
10. Assign principals at company scope, HODs at department scope and teachers
    explicitly to their courses. Multiple teachers can share a subject course.
11. Link each guardian explicitly to one or more learners. Guardians are not
    enrolled in learner courses.
12. Open **Classes and placements** and select the learner, year, board,
    medium, grade, stream and division.

Saving an active placement automatically creates or reuses the native class
cohort, creates a matching group in each applicable subject course, enables
Separate Groups, creates the cohort-sync enrolment and verifies tenant
ownership. Subjects without a grade ancestor are not automatically enrolled;
use **Cohorts and enrolments** for electives and exceptions.

## Academic Year Change

1. Create the next academic year.
2. Generate its category and course shells through **School year setup**.
3. Copy approved content into the new empty courses.
4. Use **Progression > Academic rollover** to preview and create year-scoped
   definitions. Rollover requires verified recovery-set evidence before apply.
5. Create one student progression decision: promote, repeat, conditional,
   transferred, withdrawn or graduated.
6. Review the decision and use **Apply approved decision**.
7. Tenant Master creates the new placement and native access before marking the
   source placement completed.
8. Keep the previous cohort membership and course outcomes as history. Hide or
   archive the prior-year category according to school policy.
9. Change the current academic year only after **Validation** reports no
   blocking placement, cohort, course or tenant-isolation issues.

Never reset or reuse a live academic course for another year.

## Same-Year Changes

- Division change: edit the placement. Tenant Master provisions the new cohort
  and course groups, then removes the learner from the previous managed cohort.
- Grade or stream transfer: edit the placement only after academic approval;
  old cohort-sync access is reconciled without deleting submissions or grades.
- Elective/remedial course: use explicit native enrolment under **Cohorts and
  enrolments** instead of changing the core class cohort.
- Teacher change: add the replacement teacher to the subject course and group,
  then suspend the former assignment according to HR policy.

## Safe Deactivation

Tenant Master does not delete companies, active courses, users, enrolments,
grades, submissions, completion or certificates. Archive academic years,
deactivate masters, suspend native users or end-date placements. Destructive
annual reconciliation requires impact review and recovery evidence.

## Clean Local Reset

To remove all local companies, users, courses, files, grades and demo data and
return to an empty IOMAD installation:

```bash
make demo-clear RESET_ARGS="--yes --build"
```

After reset, create the school from **Tenant Master > Tenants** and follow this
runbook. Do not run the local reset against stage or production.

## Acceptance Checks

1. Every user, course, cohort and group resolves to exactly one company.
2. Each active placement has a native class cohort membership.
3. Each applicable course has one cohort-sync instance and matching division
   group.
4. Subject courses use Separate Groups.
5. Teachers see only authorized courses and groups.
6. Parents have learner-context guardian roles and no course enrolments.
7. Previous-year grades, submissions, completion and certificates remain
   unchanged after progression.
8. Repeating setup, placement or course-copy operations is idempotent.
9. **Validation** reports zero blocking cross-company issues.
