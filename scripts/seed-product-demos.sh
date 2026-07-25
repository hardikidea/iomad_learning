#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

run_iomad() {
    docker compose exec -T iomad php "$@"
}

run_iomad public/local/iomadpagebuilder/cli/pagebuilder.php \
    --mode=create \
    --company=GV_SCHOOL \
    --template=school_home \
    --name="Green Valley home" \
    --slug=green-valley-home \
    --target=frontpage \
    --apply \
    --publish

run_iomad public/local/iomadpagebuilder/cli/pagebuilder.php \
    --mode=create \
    --company=NBU_ENGINEERING \
    --template=university_home \
    --name="Northbridge Engineering home" \
    --slug=northbridge-engineering-home \
    --target=frontpage \
    --apply \
    --publish

run_iomad public/local/aicoursecreator/cli/aicourse.php \
    --mode=seed-demo \
    --company=GV_SCHOOL \
    --apply

run_iomad public/local/aicoursecreator/cli/aicourse.php \
    --mode=seed-demo \
    --company=NBU_ENGINEERING \
    --apply

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=product \
    --company=GV_SCHOOL \
    --course=GV-STD1-MATH-2026 \
    --product=GV-MATH-FREE \
    --product-name="Mathematics foundations" \
    --product-status=free \
    --currency=INR \
    --recommend=GV-PHYSICS-PRO

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=product \
    --company=GV_SCHOOL \
    --course=GV-STD11-PHY-2026 \
    --product=GV-PHYSICS-PRO \
    --product-name="Video physics programme" \
    --product-status=paid \
    --price-minor=49900 \
    --currency=INR \
    --access-days=365 \
    --checkout-url=https://payments.example.invalid/green-valley/physics \
    --recommend=GV-MATH-FREE

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=order \
    --company=GV_SCHOOL \
    --product=GV-MATH-FREE \
    --order=DEMO-GV-FREE-001 \
    --user-idnumber=SCH_STUDENT_001 \
    --provider=local

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=order \
    --company=GV_SCHOOL \
    --product=GV-PHYSICS-PRO \
    --order=DEMO-GV-PAID-001 \
    --user-idnumber=SCH_STUDENT_001 \
    --provider=local-acceptance

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=pay \
    --company=GV_SCHOOL \
    --order=DEMO-GV-PAID-001 \
    --event=DEMO-GV-PAY-001

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=assign \
    --company=GV_SCHOOL \
    --order=DEMO-GV-PAID-001 \
    --user-idnumber=SCH_STUDENT_001

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=product \
    --company=NBU_ENGINEERING \
    --course=NBU-PROG101-2026 \
    --product=NBU-PROGRAMMING-FREE \
    --product-name="Programming foundations" \
    --product-status=free \
    --currency=INR \
    --recommend=NBU-DATA-STRUCTURES-PRO

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=product \
    --company=NBU_ENGINEERING \
    --course=NBU-DS101-2026 \
    --product=NBU-DATA-STRUCTURES-PRO \
    --product-name="Video data structures programme" \
    --product-status=paid \
    --price-minor=79900 \
    --currency=INR \
    --access-days=180 \
    --checkout-url=https://payments.example.invalid/northbridge/data-structures \
    --recommend=NBU-PROGRAMMING-FREE

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=order \
    --company=NBU_ENGINEERING \
    --product=NBU-PROGRAMMING-FREE \
    --order=DEMO-NBU-FREE-001 \
    --user-idnumber=UNI_STU_001 \
    --provider=local

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=order \
    --company=NBU_ENGINEERING \
    --product=NBU-DATA-STRUCTURES-PRO \
    --order=DEMO-NBU-PAID-001 \
    --user-idnumber=UNI_STU_001 \
    --provider=local-acceptance

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=pay \
    --company=NBU_ENGINEERING \
    --order=DEMO-NBU-PAID-001 \
    --event=DEMO-NBU-PAY-001

run_iomad public/local/iomadcommerce/cli/manage.php \
    --mode=assign \
    --company=NBU_ENGINEERING \
    --order=DEMO-NBU-PAID-001 \
    --user-idnumber=UNI_STU_001

run_iomad public/mod/tenantform/cli/manage.php \
    --mode=create \
    --company=GV_SCHOOL \
    --course=GV-STD1-MATH-2026 \
    --cm-idnumber=DEMO-GV-FEEDBACK \
    --name="Course feedback" \
    --template=feedback

run_iomad public/mod/tenantform/cli/manage.php \
    --mode=submit \
    --company=GV_SCHOOL \
    --cm-idnumber=DEMO-GV-FEEDBACK \
    --username=gv.student \
    --data-json='{"area":"Content","helpful":"Yes","comments":"Sanitized demo feedback.","followup":"0"}'

run_iomad public/mod/tenantform/cli/manage.php \
    --mode=create \
    --company=NBU_ENGINEERING \
    --course=NBU-PROG101-2026 \
    --cm-idnumber=DEMO-NBU-SURVEY \
    --name="Learner experience survey" \
    --template=survey

run_iomad public/mod/tenantform/cli/manage.php \
    --mode=submit \
    --company=NBU_ENGINEERING \
    --cm-idnumber=DEMO-NBU-SURVEY \
    --username=nbu.student \
    --data-json='{"rating":"Good","recommend":"Yes","comments":"Sanitized demo survey."}'

run_iomad public/local/rapidgrader/cli/manage.php \
    --mode=create-item \
    --company=GV_SCHOOL \
    --course=GV-STD1-MATH-2026 \
    --item-idnumber=GV-DEMO-ASSESSMENT \
    --item-name="Demo assessment"

run_iomad public/local/rapidgrader/cli/manage.php \
    --mode=set \
    --company=GV_SCHOOL \
    --course=GV-STD1-MATH-2026 \
    --item-idnumber=GV-DEMO-ASSESSMENT \
    --user-idnumber=SCH_STUDENT_001 \
    --grade=84

run_iomad public/local/rapidgrader/cli/manage.php \
    --mode=create-item \
    --company=NBU_ENGINEERING \
    --course=NBU-PROG101-2026 \
    --item-idnumber=NBU-DEMO-ASSESSMENT \
    --item-name="Demo assessment"

run_iomad public/local/rapidgrader/cli/manage.php \
    --mode=set \
    --company=NBU_ENGINEERING \
    --course=NBU-PROG101-2026 \
    --item-idnumber=NBU-DEMO-ASSESSMENT \
    --user-idnumber=UNI_STU_001 \
    --grade=88

run_iomad public/local/global_events/cli/seed_demo.php \
    --company=GV_SCHOOL \
    --course=GV-STD1-MATH-2026

run_iomad public/local/global_events/cli/seed_demo.php \
    --company=NBU_ENGINEERING \
    --course=NBU-PROG101-2026

run_iomad public/local/institutionpack/cli/manage_blocks.php \
    --action=inject \
    --blockname=iomadpagebuilder \
    --page=site-index \
    --region=content \
    --weight=-20 \
    --apply

run_iomad public/local/institutionpack/cli/manage_blocks.php \
    --action=inject \
    --blockname=iomaddashboard \
    --page=site-index \
    --region=content \
    --weight=-10 \
    --apply

run_iomad public/local/institutionpack/cli/manage_blocks.php \
    --action=inject \
    --blockname=gamification_telemetry \
    --page=site-index \
    --region=content \
    --weight=-15 \
    --apply

echo "Sanitized product-suite demos are installed and idempotent."
