# Page Builder Catalog

`local_iomadpagebuilder` owns a versioned, tenant-scoped page definition and
`block_iomadpagebuilder` renders it through Moodle output APIs. Operators can
start from 30 templates or compose any of 140 presets. Exported JSON contains
the schema version and is validated before it can replace a page.

## Component Library

Each component is available in seven variants: **Clean**, **Bordered**, **High
contrast**, **Media led**, **Compact**, **Spacious**, and **RTL ready**. The
combination of 20 purposes and seven variants provides 140 presets.

| Component | End-user purpose |
|---|---|
| Hero and primary action | Establishes the page purpose and provides one primary and one optional secondary action |
| Key metrics | Gives learners and managers a compact view of measurable outcomes |
| Announcements and notices | Surfaces time-sensitive institutional information without hiding it in navigation |
| Formatted editorial content | Publishes accessible policy, programme or instructional copy |
| Image and supporting text | Pairs an informative asset with concise contextual content |
| Responsive video | Embeds a course, welcome or event video in a stable responsive frame |
| Course discovery | Promotes relevant tenant-visible courses |
| Category discovery | Provides a scannable entry point into the approved course hierarchy |
| Learner progress | Presents completion status and next actions |
| Academic calendar | Highlights dates, deadlines and events |
| Process or history timeline | Explains an ordered onboarding, learning or institutional process |
| Testimonials and quotations | Adds attributed social proof or learner voice |
| Faculty and staff | Introduces tenant-approved contacts and subject experts |
| Frequently asked questions | Reduces navigation and support effort for repeated questions |
| Call to action | Provides a focused enrol, apply, continue or contact action |
| Contact details | Makes institution-specific support channels visible |
| Policies and compliance | Links current policy and compliance resources |
| Files and resources | Provides governed downloads and learner resources |
| Embedded form | Places a `mod_tenantform` workflow in a page |
| Embedded report summary | Places a capability-checked tenant report summary in a page |

Variants change visual hierarchy and density, not authorization or data scope.
The RTL variant changes direction-aware presentation without duplicating page
content. High-contrast variants retain semantic headings and focus indicators.

## Starter Templates

| Template key | Intended workflow |
|---|---|
| `school_home` | School identity, notices, courses, metrics and contact actions |
| `school_student_dashboard` | Student progress, calendar, courses and resources |
| `school_parent_portal` | Learner-linked progress, notices, policies and school contacts |
| `school_teacher_portal` | Teaching courses, calendar, reports and staff actions |
| `school_admissions` | Admissions proposition, process, FAQ, form and contacts |
| `school_academic_year` | Term dates, academic notices, resources and policy links |
| `university_home` | University identity, programme discovery, research and calls to action |
| `university_student_dashboard` | Programme courses, progress, calendar and services |
| `university_faculty_portal` | Faculty courses, analytics, academic dates and resources |
| `university_programme` | Programme overview, faculty, course path, outcomes and application |
| `university_admissions` | Entry requirements, application stages, FAQ and form |
| `university_research` | Research themes, people, media, resources and contact |
| `college_home` | College identity, departments, courses and notices |
| `college_department` | Department people, programmes, courses and resources |
| `college_student_services` | Support contacts, policies, forms and downloads |
| `training_home` | Training offer, catalogue, outcomes and registration |
| `training_catalogue` | Category and course discovery with focused enrolment actions |
| `training_compliance` | Required learning, progress, policy and evidence resources |
| `training_manager_dashboard` | Company metrics, learner progress, reports and actions |
| `learner_dashboard` | Personal progress, courses, calendar and tasks |
| `educator_dashboard` | Managed courses, learners, grading, notes and analytics |
| `tenant_manager_dashboard` | Company users, licenses, reports, policies and operations |
| `executive_dashboard` | High-level engagement, completion and risk summaries |
| `course_launch` | Course proposition, outcomes, structure and enrolment action |
| `video_course_launch` | Video-led proposition, playlist context and enrolment action |
| `event_registration` | Event details, schedule, form and contacts |
| `policy_hub` | Current policies, compliance notices and governed downloads |
| `support_hub` | FAQ, support contacts, forms and service resources |
| `commerce_shopfront` | Tenant course discovery, offers, recommendations and purchase actions |
| `minimal_login_home` | Restrained institution identity and sign-in support |

## Operator Workflow

```bash
docker compose exec -T iomad \
  php public/local/iomadpagebuilder/cli/pagebuilder.php --mode=catalog

docker compose exec -T iomad \
  php public/local/iomadpagebuilder/cli/pagebuilder.php \
  --mode=create --company=GV_SCHOOL --template=school_home \
  --name='School home' --slug=school-home --target=frontpage --apply --publish
```

The web editor supports preset insertion, field editing, drag reordering,
keyboard move controls, deletion and template replacement. Do not place raw
scripts, credentials, unrestricted database queries or cross-tenant data in a
page definition.
