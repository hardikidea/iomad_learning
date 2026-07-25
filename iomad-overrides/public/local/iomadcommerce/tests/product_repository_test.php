<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce;

use local_iomad\company;
use local_iomadcommerce\local\product_repository;
use local_iomadcommerce\local\tenant_scope;

/**
 * Tenant product repository tests.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadcommerce\local\product_repository
 */
final class product_repository_test extends \advanced_testcase {
    /**
     * Upserts are idempotent and reject another company's course.
     */
    public function test_product_upsert_is_tenant_scoped_and_idempotent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('Commerce A', 'commerce_a');
        $companyb = $this->company('Commerce B', 'commerce_b');
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $companya->add_course($coursea);
        $companyb->add_course($courseb);
        $repository = new product_repository();
        $scope = new tenant_scope($companya->id);
        $input = [
            'externalid' => 'COURSE-A',
            'name' => 'Course A product',
            'status' => 'paid',
            'priceminor' => 12500,
            'currency' => 'INR',
            'accessdays' => 365,
            'checkouturl' => 'https://payments.example.test/course-a',
            'recommendations' => ['COURSE-B'],
        ];

        [$created, $createdaction] = $repository->upsert($scope, $coursea, $input);
        [$unchanged, $unchangedaction] = $repository->upsert($scope, $coursea, $input);

        $this->assertSame('created', $createdaction);
        $this->assertSame('unchanged', $unchangedaction);
        $this->assertSame((int)$created->id, (int)$unchanged->id);
        $this->assertCount(1, $repository->list($companya->id));

        $this->expectException(\invalid_parameter_exception::class);
        $repository->upsert($scope, $courseb, $input + ['externalid' => 'COURSE-B']);
    }

    /**
     * Create a company through IOMAD.
     *
     * @param string $name Name.
     * @param string $shortname Shortname.
     * @return company
     */
    private function company(string $name, string $shortname): company {
        return company::create_company((object)[
            'name' => $name,
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
    }
}
