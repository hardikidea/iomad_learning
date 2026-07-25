<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Store keyed address hashes, never plain chat addresses.
 *
 * @package local_global_events
 */
final class chat_address_repository {
    /**
     * Register an opted-in address.
     *
     * @param tenant_scope $scope Scope.
     * @param int $userid User.
     * @param string $address E.164 address.
     * @return object
     */
    public function register(tenant_scope $scope, int $userid, string $address): object {
        global $DB;

        if (!$scope->contains_user($userid)) {
            throw new \invalid_parameter_exception('The user is outside the company scope.');
        }
        $hash = $this->hash($address);
        $conditions = ['companyid' => $scope->companyid(), 'addresshash' => $hash];
        $record = $DB->get_record('local_ge_chatstate', $conditions);
        if ($record && (int)$record->userid !== $userid) {
            throw new \invalid_parameter_exception('The chat address is already registered.');
        }
        if ($record) {
            $record->timemodified = time();
            $DB->update_record('local_ge_chatstate', $record);
            return $record;
        }
        $record = (object)($conditions + [
            'userid' => $userid,
            'statejson' => '{}',
            'timemodified' => time(),
        ]);
        $record->id = $DB->insert_record('local_ge_chatstate', $record);
        return $record;
    }

    /**
     * Resolve a signed incoming address.
     *
     * @param int $companyid Company.
     * @param string $address Address.
     * @return object
     */
    public function resolve(int $companyid, string $address): object {
        global $DB;

        return $DB->get_record('local_ge_chatstate', [
            'companyid' => $companyid,
            'addresshash' => $this->hash($address),
        ], '*', MUST_EXIST);
    }

    /**
     * Keyed address hash.
     *
     * @param string $address Address.
     * @return string
     */
    private function hash(string $address): string {
        $normalised = preg_replace('/[^0-9+]/', '', $address);
        $key = getenv('IOMAD_CHAT_ADDRESS_KEY') ?: '';
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $normalised) || strlen($key) < 32) {
            throw new \invalid_parameter_exception('Invalid chat address configuration.');
        }
        return hash_hmac('sha256', $normalised, $key);
    }
}
