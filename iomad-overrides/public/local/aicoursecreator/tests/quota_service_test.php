<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

final class quota_service_test extends \advanced_testcase {
    public function test_usage_is_accumulated_within_the_company_period(): void {
        $this->resetAfterTest(true);
        $service = new quota_service();
        $service->set_limit(101, 10, '2026-07');
        $service->consume(101, 3, '2026-07');
        $quota = $service->consume(101, 4, '2026-07');

        $this->assertSame(7, (int)$quota->creditsused);
        $this->assertSame(10, (int)$quota->creditlimit);
    }

    public function test_usage_cannot_exceed_company_limit(): void {
        $this->resetAfterTest(true);
        $service = new quota_service();
        $service->set_limit(202, 2, '2026-07');

        $this->expectException(\moodle_exception::class);
        $service->consume(202, 3, '2026-07');
    }
}
