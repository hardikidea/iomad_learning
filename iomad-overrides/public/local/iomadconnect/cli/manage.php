<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');

use local_iomadconnect\local\catalogue_exporter;
use local_iomadconnect\local\peer_repository;
use local_iomadconnect\local\sync_service;
use local_iomadcommerce\local\tenant_scope;

[$options, $unrecognised] = cli_get_params([
    'action' => 'doctor',
    'company' => '',
    'peer' => '',
    'baseurl' => '',
    'keyid' => '',
    'status' => 'disabled',
    'cursor' => '',
    'limit' => 100,
    'system' => 'wordpress',
    'events' => '',
    'help' => false,
], [
    'a' => 'action',
    'c' => 'company',
    'p' => 'peer',
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    echo <<<'HELP'
IOMAD Connect CLI

  --action=doctor
  --action=register-peer --company=SHORTNAME --peer=KEY --baseurl=HTTPS_URL --keyid=ENV_KEY [--status=enabled]
  --action=test-peer --company=SHORTNAME --peer=KEY
  --action=export --company=SHORTNAME [--cursor=CURSOR] [--limit=100]
  --action=apply --company=SHORTNAME --system=KEY --events=/absolute/events.json
  --action=status --company=SHORTNAME [--peer=KEY]

Credentials are read only from IOMAD_CONNECT_TOKENS_JSON. Password synchronization is rejected.
HELP;
    exit(0);
}

/**
 * Resolve a company ID without exposing tenant data.
 *
 * @param string $value ID or shortname.
 * @return int
 */
function local_iomadconnect_cli_companyid(string $value): int {
    global $DB;

    if ($value === '') {
        cli_error('--company is required.');
    }
    $company = ctype_digit($value)
        ? $DB->get_record('local_iomad_companies', ['id' => (int)$value])
        : $DB->get_record('local_iomad_companies', ['shortname' => $value]);
    if (!$company) {
        cli_error('Company not found.');
    }
    return (int)$company->id;
}

try {
    $action = (string)$options['action'];
    $peers = new peer_repository();
    if ($action === 'doctor') {
        $checks = [
            'tables' => $DB->get_manager()->table_exists('local_iomadconnect_event'),
            'service' => $DB->record_exists('external_services', ['shortname' => 'iomad_connect']),
            'catalogue_function' => $DB->record_exists(
                'external_functions',
                ['name' => 'local_iomadconnect_get_catalogue'],
            ),
            'apply_function' => $DB->record_exists(
                'external_functions',
                ['name' => 'local_iomadconnect_apply_events'],
            ),
            'federated_auth' => in_array(
                get_config('local_iomadconnect', 'authmethod') ?: 'iomadoidc',
                get_enabled_auth_plugins(true),
                true,
            ),
        ];
        echo json_encode(['ok' => !in_array(false, $checks, true), 'checks' => $checks], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(in_array(false, $checks, true) ? 1 : 0);
    }
    $companyid = local_iomadconnect_cli_companyid((string)$options['company']);
    if ($action === 'register-peer') {
        $peer = $peers->upsert(
            $companyid,
            (string)$options['peer'],
            (string)$options['baseurl'],
            (string)$options['keyid'],
            (string)$options['status'],
        );
        echo json_encode([
            'ok' => true,
            'peer' => $peer->externalid,
            'status' => $peer->status,
        ], JSON_THROW_ON_ERROR) . PHP_EOL;
    } else if ($action === 'test-peer') {
        $peer = $peers->get($companyid, (string)$options['peer']);
        if ($peer->status !== 'enabled') {
            cli_error('The peer is disabled.');
        }
        $url = new moodle_url($peer->baseurl . '/webservice/rest/server.php');
        $curl = new curl();
        $response = $curl->post($url->out(false), [
            'wstoken' => $peers->token_for($peer->keyid),
            'wsfunction' => 'local_iomadconnect_get_catalogue',
            'moodlewsrestformat' => 'json',
            'companyid' => $companyid,
            'limit' => 1,
        ], ['CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 5]);
        $decoded = json_decode($response, true);
        $ok = is_array($decoded) && !isset($decoded['exception']) && isset($decoded['events']);
        $peers->mark_result((int)$peer->id, $ok);
        echo json_encode(['ok' => $ok, 'peer' => $peer->externalid], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit($ok ? 0 : 1);
    } else if ($action === 'export') {
        $result = (new catalogue_exporter())->export(
            $companyid,
            (string)$options['cursor'],
            (int)$options['limit'],
        );
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else if ($action === 'apply') {
        $path = (string)$options['events'];
        if ($path === '' || !is_file($path) || filesize($path) > 1048576) {
            cli_error('--events must identify a readable JSON file no larger than 1 MiB.');
        }
        $events = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($events) || !array_is_list($events)) {
            cli_error('The events file must contain a JSON array.');
        }
        $results = (new sync_service())->apply(
            new tenant_scope($companyid),
            (string)$options['system'],
            $events,
        );
        echo json_encode(['ok' => true, 'results' => $results], JSON_THROW_ON_ERROR) . PHP_EOL;
    } else if ($action === 'status') {
        $conditions = ['companyid' => $companyid];
        if ((string)$options['peer'] !== '') {
            $conditions['externalid'] = (string)$options['peer'];
        }
        $records = $DB->get_records('local_iomadconnect_peer', $conditions, 'externalid ASC');
        $result = array_map(static fn(object $peer): array => [
            'peer' => $peer->externalid,
            'status' => $peer->status,
            'lastsuccess' => (int)$peer->lastsuccess,
            'lastfailure' => (int)$peer->lastfailure,
        ], array_values($records));
        echo json_encode(['ok' => true, 'peers' => $result], JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        cli_error('Unsupported action.');
    }
} catch (Throwable $exception) {
    cli_error($exception instanceof moodle_exception ? $exception->getMessage() : 'IOMAD Connect failed.');
}
