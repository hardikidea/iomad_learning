<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Bounded exception counters backed by the application cache.
 *
 * @package tool_iomadmonitor
 */
final class exception_counter {
    /**
     * Increment one allowlisted category.
     *
     * @param string $category Category.
     */
    public static function increment(string $category): void {
        $definition = exception_category::definition($category);
        $category = $definition['category'];
        try {
            $cache = \cache::make('tool_iomadmonitor', 'exceptioncounts');
            $current = max(0, (int)($cache->get($category) ?: 0));
            $cache->set($category, $current + 1);
        } catch (\Throwable) {
            return;
        }
    }

    /**
     * Return counters for the fixed category catalogue.
     *
     * @return array
     */
    public static function snapshot(): array {
        $result = array_fill_keys(array_keys(exception_category::all()), 0);
        try {
            $cache = \cache::make('tool_iomadmonitor', 'exceptioncounts');
            foreach ($result as $category => $unused) {
                $result[$category] = max(0, (int)($cache->get($category) ?: 0));
            }
        } catch (\Throwable) {
            return $result;
        }
        return $result;
    }
}
