# Enrolment Failure

1. Confirm exact active company membership, course assignment/share, enrolment
   method state, licence availability, and policy acceptance.
2. Do not grant site administrator or bypass capability checks to complete an
   enrolment.
3. Retry only through the supported Moodle/IOMAD enrollment API with the same
   idempotency reference.
4. Verify one successful same-company enrollment and one denied cross-company
   attempt, then confirm groups/cohorts remain company-scoped.

