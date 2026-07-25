# Role-Aware UX

Role-aware pages are projections of capabilities and company scope, not a
client-side role name switch.

| Profile | Primary experience | Data boundary |
|---|---|---|
| Learner | Own courses, attempts, feedback, tasks, XP, level, badges, events | Own records only |
| Teacher/faculty | Course participants, grading, notes, course analytics | Assigned course and company users |
| Principal/registrar | Company administration and aggregate reports | Active company |
| HOD/dean | Department and assigned-course reports | Managed department tree |
| Trustee/management | Parent-company aggregate dashboard | Explicit child companies, no learner rows by default |
| IT coordinator | Limited configuration and support tools | Granted capabilities only |
| Parent/guardian | Mentor-linked learner view | Explicit learner relationship |

`block_iomaddashboard` uses Moodle capabilities for its ten workflow widgets.
`block_gamification_telemetry` renders only the current learner's company
progress. It uses stable dimensions, semantic progress markup, and a small
celebration only after a points increase. The effect is removed when
`prefers-reduced-motion` is enabled.

## Acceptance Matrix

For each profile, test:

1. expected navigation and commands;
2. denied commands and direct URLs;
3. same-company sibling department boundaries;
4. unrelated company denial;
5. parent-company aggregate behavior;
6. mobile, keyboard, 200% zoom, RTL, and reduced motion;
7. session switch between authorized companies;
8. no site-administrator requirement.
