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

1. Open the native **IOMAD Dashboard** and create the school company. Use one
   permanent company code; do not create separate trust and school companies
   for the same business record.
2. Configure the school name, address, hostname, theme, logo, colours and
   email settings in native IOMAD.
3. Create campuses and departments such as Administration, Primary,
   Secondary, Science and Languages in native **Manage departments**.
4. Create principals, HODs, teachers, students and guardians in native IOMAD
   user administration. Assign company managers and department managers there.
5. Open **Tenant Master > Managed institutions**, select the existing company
   and initialise it as **School**.
6. Open **Institution master data** and enter trust, UDISE, board affiliation,
   recognition and academic-session metadata. Do not store Aadhaar, bank,
   salary or sensitive HR documents.
7. Open **Academic masters**, create the academic year and mark it current.
8. Review shared boards, mediums, grades, streams, divisions and subjects.
9. Use **School year setup** to select a board, medium, one or more grades,
   optional stream and subjects. Tenant Master creates:

   `Academic year > Board > Medium > Grade > optional Stream > Subject course`

10. Run **Sync all**, or allow automatic synchronization and cron to complete.
11. Open native **IOMAD Courses** to copy approved content from a template or
    previous-year course into each empty year-specific course. Exclude users,
    enrolments, roles, grades, completion, logs and learner history.
12. Assign teachers explicitly to native subject courses. Multiple teachers
    can share a course. Link each guardian to learners using the approved
    native mentor-role workflow; guardians are not enrolled in learner courses.
13. Open **Classes and placements** and select the learner, year, board,
    medium, grade, stream and division.

Saving an active placement automatically creates or reuses the native class
cohort, creates a matching group in each applicable subject course, enables
Separate Groups, creates the cohort-sync enrolment and verifies tenant
ownership. Subjects without a grade ancestor are not automatically enrolled;
use native IOMAD course enrolment for electives and exceptions.

## Academic Year Change

1. Create the next academic year.
2. Generate its category and course shells through **School year setup**.
3. Use native IOMAD course administration to copy approved content into the
   new empty courses.
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
- Elective/remedial course: use explicit native IOMAD enrolment instead of
  changing the core class cohort.
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

After reset, create the company in native IOMAD, then initialise it from
**Tenant Master > Managed institutions**. Do not run the local reset against
stage or production.

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
