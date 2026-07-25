<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Repository-owned service catalogue.
 *
 * @package tool_iomadmonitor
 */
final class service_catalogue {
    /**
     * Build the current service graph.
     *
     * @return service_registry
     */
    public static function build(): service_registry {
        $registry = new service_registry();
        foreach (self::platform_services() as $service) {
            $registry->add($service);
        }
        foreach (self::project_components() as $component => $metadata) {
            if (!\core_component::get_component_directory($component)) {
                continue;
            }
            $registry->add(new service_descriptor(
                $metadata['id'],
                $metadata['name'],
                $component,
                'application',
                $metadata['criticality'],
                $metadata['dependencies'],
                metadata: $metadata['metadata'],
            ));
        }
        return $registry;
    }

    /**
     * Base runtime dependencies.
     *
     * @return service_descriptor[]
     */
    private static function platform_services(): array {
        return [
            new service_descriptor(
                'platform.database',
                'PostgreSQL',
                'platform',
                'storage',
                'critical',
                metadata: self::metadata(
                    'core',
                    'Primary transactional database.',
                    'docs/12-runbooks/database-unavailable.md',
                    'database',
                    ['data', 'postgresql'],
                ),
            ),
            new service_descriptor(
                'platform.redis',
                'Redis sessions and cache',
                'platform',
                'storage',
                'critical',
                metadata: self::metadata(
                    'core',
                    'Session and application cache backend.',
                    'docs/12-runbooks/cache-unavailable.md',
                    'redis',
                    ['cache', 'sessions'],
                ),
            ),
            new service_descriptor(
                'platform.storage',
                'IOMAD dataroot',
                'platform',
                'storage',
                'critical',
                metadata: self::metadata(
                    'core',
                    'Shared private file storage.',
                    'docs/12-runbooks/storage-capacity.md',
                    'storage',
                    ['files', 'storage'],
                ),
            ),
            new service_descriptor(
                'platform.web',
                'IOMAD web runtime',
                'platform',
                'runtime',
                'critical',
                ['platform.database', 'platform.redis', 'platform.storage'],
                metadata: self::metadata(
                    'core',
                    'Nginx and PHP-FPM application runtime.',
                    'docs/12-runbooks/application-not-ready.md',
                    'security',
                    ['http', 'php'],
                    '/',
                ),
            ),
            new service_descriptor(
                'platform.cron',
                'IOMAD scheduled task runtime',
                'platform',
                'runtime',
                'critical',
                ['platform.database', 'platform.redis'],
                metadata: array_replace(self::metadata(
                    'core_task',
                    'Moodle and IOMAD scheduled task runner.',
                    'docs/12-runbooks/cron-not-running.md',
                    'cron',
                    ['background', 'cron'],
                ), [
                    'scheduledtask' => '\core\task\manager',
                    'retrypolicy' => 'task-managed',
                ]),
            ),
            new service_descriptor(
                'platform.mail',
                'Outbound mail gateway',
                'platform',
                'integration',
                'important',
                ['platform.web'],
                metadata: array_replace(self::metadata(
                    'message',
                    'Outbound Moodle message transport.',
                    'docs/12-runbooks/external-integration-failure.md',
                    '',
                    ['email', 'integration'],
                ), [
                    'retrypolicy' => 'task-managed',
                ]),
            ),
        ];
    }

    /**
     * Project component metadata.
     *
     * @return array
     */
    private static function project_components(): array {
        return [
            'local_institutionpack' => [
                'id' => 'application.institution-pack',
                'name' => 'Institution pack importer',
                'criticality' => 'important',
                'dependencies' => ['platform.web', 'platform.cron'],
                'metadata' => array_replace(self::metadata(
                    'local_institutionpack',
                    'Versioned institution data-pack validation and import.',
                    'docs/12-runbooks/import-failure.md',
                    'integrations',
                    ['import', 'tenant'],
                    '/local/institutionpack/',
                ), [
                    'companyscope' => 'current',
                    'retrypolicy' => 'task-managed',
                ]),
            ],
            'local_iomadcommerce' => [
                'id' => 'application.commerce',
                'name' => 'Tenant commerce',
                'criticality' => 'optional',
                'dependencies' => ['platform.web', 'platform.cron', 'platform.mail'],
                'metadata' => array_replace(self::metadata(
                    'local_iomadcommerce',
                    'Company-scoped catalogue, order, seat, and payment lifecycle.',
                    'docs/12-runbooks/payment-failure.md',
                    'integrations',
                    ['commerce', 'payment'],
                    '/local/iomadcommerce/',
                ), [
                    'companyscope' => 'current',
                    'retrypolicy' => 'bounded-idempotent',
                ]),
            ],
            'local_iomadconnect' => [
                'id' => 'application.connector',
                'name' => 'External synchronization',
                'criticality' => 'optional',
                'dependencies' => ['platform.web', 'platform.cron'],
                'metadata' => array_replace(self::metadata(
                    'local_iomadconnect',
                    'Idempotent external catalogue and enrolment synchronization.',
                    'docs/12-runbooks/external-integration-failure.md',
                    'integrations',
                    ['api', 'synchronization'],
                ), [
                    'companyscope' => 'current',
                    'retrypolicy' => 'bounded-idempotent',
                ]),
            ],
            'local_global_events' => [
                'id' => 'application.global-events',
                'name' => 'Global events and gamification',
                'criticality' => 'important',
                'dependencies' => ['platform.web', 'platform.cron', 'platform.mail'],
                'metadata' => array_replace(self::metadata(
                    'local_global_events',
                    'Company-visible events, gamification, and notification queue.',
                    'docs/12-runbooks/scheduled-task-failure.md',
                    'integrations',
                    ['events', 'gamification'],
                    '/local/global_events/index.php',
                ), [
                    'companyscope' => 'current',
                    'retrypolicy' => 'task-managed',
                    'scheduledtask' => '\local_global_events\task\process_messages',
                ]),
            ],
            'local_iomad_scorm_gen' => [
                'id' => 'application.scorm-adapter',
                'name' => 'SCORM package and reward adapter',
                'criticality' => 'optional',
                'dependencies' => ['platform.web', 'application.global-events'],
                'metadata' => array_replace(self::metadata(
                    'local_iomad_scorm_gen',
                    'SCORM 1.2 package generation and trusted completion reward adapter.',
                    'docs/12-runbooks/external-integration-failure.md',
                    '',
                    ['learning', 'scorm'],
                ), [
                    'companyscope' => 'current',
                ]),
            ],
            'local_iomad_h5p_bridge' => [
                'id' => 'application.h5p-adapter',
                'name' => 'H5P reward adapter',
                'criticality' => 'optional',
                'dependencies' => ['platform.web', 'application.global-events'],
                'metadata' => array_replace(self::metadata(
                    'local_iomad_h5p_bridge',
                    'Trusted H5P statement-to-reward adapter.',
                    'docs/12-runbooks/external-integration-failure.md',
                    '',
                    ['h5p', 'learning'],
                ), [
                    'companyscope' => 'current',
                ]),
            ],
        ];
    }

    /**
     * Shared privacy-safe metadata.
     *
     * @param string $component Component.
     * @param string $description Description.
     * @param string $runbook Runbook path.
     * @param string $healthcheck Health check ID.
     * @param string[] $tags Tags.
     * @param string $publicendpoint Public application-relative endpoint.
     * @return array
     */
    private static function metadata(
        string $component,
        string $description,
        string $runbook,
        string $healthcheck,
        array $tags,
        string $publicendpoint = '',
    ): array {
        return [
            'component' => $component,
            'description' => $description,
            'technicalowner' => 'platform-engineering',
            'businessowner' => 'lms-operations',
            'visibility' => 'operator',
            'publicendpoint' => $publicendpoint,
            'internalendpoint' => '/admin/tool/iomadmonitor/status.php',
            'dashboard' => '/admin/tool/iomadmonitor/index.php',
            'runbook' => $runbook,
            'capability' => 'tool/iomadmonitor:view',
            'companyscope' => 'system',
            'dataclassification' => 'operational',
            'timeoutms' => 2000,
            'retrypolicy' => 'none',
            'scheduledtask' => '',
            'healthcheck' => $healthcheck,
            'tags' => $tags,
        ];
    }
}
