<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Immutable service catalogue entry.
 *
 * @package    tool_iomadmonitor
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class service_descriptor {
    /** @var array<string, mixed> Safe optional service metadata. */
    private array $metadata;

    /** @var string[] Dependencies. */
    private array $dependencies;

    /**
     * Constructor.
     *
     * @param string $id Stable machine ID.
     * @param string $name Operator-facing name.
     * @param string $owner Owning Moodle component or platform team.
     * @param string $type Service type.
     * @param string $criticality Critical, important, or optional.
     * @param array $dependencies Service IDs required by this service.
     * @param bool $enabled Whether the service is enabled.
     * @param bool $maintenance Whether the service is intentionally unavailable.
     * @param array $metadata Privacy-safe operational metadata.
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $owner,
        private readonly string $type,
        private readonly string $criticality,
        array $dependencies = [],
        private readonly bool $enabled = true,
        private readonly bool $maintenance = false,
        array $metadata = [],
    ) {
        if (!preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $id)) {
            throw new \InvalidArgumentException('Service IDs must be stable lowercase identifiers.');
        }
        if ($name === '' || $owner === '') {
            throw new \InvalidArgumentException('Service name and owner are required.');
        }
        if (!in_array($type, ['runtime', 'storage', 'queue', 'integration', 'application'], true)) {
            throw new \InvalidArgumentException('Unsupported service type.');
        }
        if (!in_array($criticality, ['critical', 'important', 'optional'], true)) {
            throw new \InvalidArgumentException('Unsupported service criticality.');
        }
        $dependencies = array_values(array_unique(array_map('strval', $dependencies)));
        if (in_array($id, $dependencies, true)) {
            throw new \InvalidArgumentException('A service cannot depend on itself.');
        }
        $defaults = [
            'component' => $owner,
            'description' => '',
            'technicalowner' => $owner,
            'businessowner' => 'unassigned',
            'visibility' => 'operator',
            'publicendpoint' => '',
            'internalendpoint' => '',
            'dashboard' => '',
            'runbook' => '',
            'capability' => 'tool/iomadmonitor:view',
            'companyscope' => 'system',
            'dataclassification' => 'operational',
            'timeoutms' => 2000,
            'retrypolicy' => 'none',
            'scheduledtask' => '',
            'healthcheck' => '',
            'tags' => [],
        ];
        $unknown = array_diff(array_keys($metadata), array_keys($defaults));
        if ($unknown) {
            throw new \InvalidArgumentException('Unsupported service metadata: ' . implode(', ', $unknown));
        }
        $metadata = array_replace($defaults, $metadata);
        if (!in_array($metadata['visibility'], ['public', 'authenticated', 'operator', 'internal'], true)) {
            throw new \InvalidArgumentException('Unsupported service visibility.');
        }
        if (!in_array($metadata['companyscope'], ['none', 'current', 'parent', 'system'], true)) {
            throw new \InvalidArgumentException('Unsupported company scope.');
        }
        if (!in_array($metadata['dataclassification'], ['public', 'internal', 'operational', 'confidential'], true)) {
            throw new \InvalidArgumentException('Unsupported data classification.');
        }
        if (!in_array($metadata['retrypolicy'], ['none', 'bounded-idempotent', 'task-managed'], true)) {
            throw new \InvalidArgumentException('Unsupported retry policy.');
        }
        if ((int)$metadata['timeoutms'] < 50 || (int)$metadata['timeoutms'] > 30000) {
            throw new \InvalidArgumentException('Service timeout must be between 50 and 30000 milliseconds.');
        }
        foreach (['publicendpoint', 'internalendpoint', 'dashboard', 'runbook'] as $pathfield) {
            $path = (string)$metadata[$pathfield];
            if ($path !== '' && !str_starts_with($path, '/') && !str_starts_with($path, 'docs/')) {
                throw new \InvalidArgumentException('Service locations must be repository or application relative.');
            }
        }
        if (
            (string)$metadata['capability'] !== ''
            && !preg_match('/^[a-z][a-z0-9_]+\/[a-z][a-z0-9_:]+$/', (string)$metadata['capability'])
        ) {
            throw new \InvalidArgumentException('Invalid service capability.');
        }
        if (!is_array($metadata['tags'])) {
            throw new \InvalidArgumentException('Service tags must be an array.');
        }
        $metadata['tags'] = array_values(array_unique(array_map(
            static function (mixed $tag): string {
                $tag = (string)$tag;
                if (!preg_match('/^[a-z][a-z0-9_.-]{1,31}$/', $tag)) {
                    throw new \InvalidArgumentException('Invalid service tag.');
                }
                return $tag;
            },
            $metadata['tags'],
        )));
        $metadata['timeoutms'] = (int)$metadata['timeoutms'];
        $this->dependencies = $dependencies;
        $this->metadata = $metadata;
    }

    /**
     * Service ID.
     *
     * @return string
     */
    public function id(): string {
        return $this->id;
    }

    /**
     * Dependencies.
     *
     * @return string[]
     */
    public function dependencies(): array {
        return $this->dependencies;
    }

    /**
     * Enabled state.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Maintenance state.
     *
     * @return bool
     */
    public function is_in_maintenance(): bool {
        return $this->maintenance;
    }

    /**
     * One safe metadata value.
     *
     * @param string $key Metadata key.
     * @return mixed
     */
    public function metadata(string $key): mixed {
        if (!array_key_exists($key, $this->metadata)) {
            throw new \InvalidArgumentException('Unknown service metadata key.');
        }
        return $this->metadata[$key];
    }

    /**
     * Privacy-safe catalogue record.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner' => $this->owner,
            'type' => $this->type,
            'criticality' => $this->criticality,
            'dependencies' => $this->dependencies,
            'enabled' => $this->enabled,
            'maintenance' => $this->maintenance,
        ] + $this->metadata;
    }
}
