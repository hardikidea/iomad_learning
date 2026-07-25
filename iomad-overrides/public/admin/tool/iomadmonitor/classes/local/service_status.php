<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Privacy-safe runtime status record.
 *
 * @package tool_iomadmonitor
 */
final class service_status {
    /** @var string[] Supported public states. */
    private const STATES = [
        'healthy', 'degraded', 'unavailable', 'maintenance', 'disabled', 'unknown',
    ];

    /**
     * Constructor.
     *
     * @param string $serviceid Service ID.
     * @param string $state Public state.
     * @param int $durationms Check duration.
     * @param int $checkedat Check time.
     * @param string $failurecategory Stable failure category.
     */
    public function __construct(
        public readonly string $serviceid,
        public readonly string $state,
        public readonly int $durationms,
        public readonly int $checkedat,
        public readonly string $failurecategory = '',
    ) {
        if (!preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $serviceid)) {
            throw new \InvalidArgumentException('Invalid service status ID.');
        }
        if (!in_array($state, self::STATES, true) || $durationms < 0 || $checkedat <= 0) {
            throw new \InvalidArgumentException('Invalid service status.');
        }
        if ($failurecategory !== '' && !preg_match('/^[a-z][a-z0-9_]{1,63}$/', $failurecategory)) {
            throw new \InvalidArgumentException('Invalid service failure category.');
        }
    }

    /**
     * Array representation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'serviceid' => $this->serviceid,
            'state' => $this->state,
            'durationms' => $this->durationms,
            'checkedat' => $this->checkedat,
            'failurecategory' => $this->failurecategory,
        ];
    }
}
