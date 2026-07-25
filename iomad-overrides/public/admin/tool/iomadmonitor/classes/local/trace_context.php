<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Validated W3C trace context.
 *
 * @package tool_iomadmonitor
 */
final class trace_context {
    /**
     * Constructor.
     *
     * @param string $traceid 32-character trace ID.
     * @param string $parentspanid 16-character parent span ID.
     * @param bool $sampled Sampling flag.
     */
    private function __construct(
        public readonly string $traceid,
        public readonly string $parentspanid,
        public readonly bool $sampled,
    ) {
    }

    /**
     * Parse an incoming traceparent or create a new trace.
     *
     * @param string|null $header Incoming header.
     * @return self
     */
    public static function resolve(?string $header = null): self {
        $header ??= $_SERVER['HTTP_TRACEPARENT'] ?? '';
        $header = strtolower($header);
        if (
            preg_match(
                '/^[\da-f]{2}-([\da-f]{32})-([\da-f]{16})-([\da-f]{2})$/D',
                $header,
                $matches,
            )
        ) {
            $validversion = substr($header, 0, 2) === '00';
            $validtrace = $matches[1] !== str_repeat('0', 32);
            $validspan = $matches[2] !== str_repeat('0', 16);
            if ($validversion && $validtrace && $validspan) {
                return new self($matches[1], $matches[2], (hexdec($matches[3]) & 1) === 1);
            }
        }
        return new self(bin2hex(random_bytes(16)), '', true);
    }

    /**
     * Build a traceparent value for a child request.
     *
     * @param string $spanid Current span ID.
     * @return string
     */
    public function header(string $spanid): string {
        if (!preg_match('/^[\da-f]{16}$/D', $spanid) || $spanid === str_repeat('0', 16)) {
            throw new \InvalidArgumentException('Invalid span ID.');
        }
        return sprintf('00-%s-%s-%s', $this->traceid, $spanid, $this->sampled ? '01' : '00');
    }
}
