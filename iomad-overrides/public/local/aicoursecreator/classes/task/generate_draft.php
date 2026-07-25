<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator\task;

use local_aicoursecreator\core_ai_gateway;
use local_aicoursecreator\draft_repository;
use local_aicoursecreator\quota_service;
use local_aicoursecreator\tenant_context;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates one draft without publishing it.
 */
final class generate_draft extends \core\task\adhoc_task {
    public function execute(): void {
        $data = $this->get_custom_data();
        $draftid = (int)($data->draftid ?? 0);
        $companyid = (int)($data->companyid ?? 0);
        $userid = (int)($data->userid ?? 0);
        $repository = new draft_repository();
        $draft = $repository->get($draftid, $companyid);
        $credits = max(1, min(20, (int)ceil(\core_text::strlen($draft->brief) / 500)));
        try {
            (new quota_service())->consume($companyid, $credits);
            $repository->mark_generating($draftid, $companyid, $userid, $credits);
            $result = (new core_ai_gateway())->generate(
                $draft,
                tenant_context::context($companyid)->id,
                $userid
            );
            $repository->save_generated(
                $draftid,
                $companyid,
                $userid,
                $result['definition'],
                $result['provider'],
                $result['model']
            );
        } catch (\Throwable $exception) {
            $repository->mark_failed($draftid, $companyid, $userid, $exception::class);
            throw $exception;
        }
    }
}
