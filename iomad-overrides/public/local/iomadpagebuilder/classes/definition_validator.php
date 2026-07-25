<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates and normalises untrusted page definitions.
 */
final class definition_validator {
    private const MAX_SECTIONS = 100;
    private const MAX_ITEMS = 24;

    /**
     * Decode and validate JSON.
     */
    public function from_json(string $json): array {
        try {
            $definition = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \invalid_parameter_exception('Invalid page definition JSON: ' . $exception->getMessage());
        }
        if (!is_array($definition)) {
            throw new \invalid_parameter_exception('Page definition must be a JSON object.');
        }
        return $this->validate($definition);
    }

    /**
     * Validate a decoded page definition.
     */
    public function validate(array $definition): array {
        if (($definition['schema_version'] ?? null) !== catalog::VERSION) {
            throw new \invalid_parameter_exception('Unsupported page schema version.');
        }
        $sections = $definition['sections'] ?? null;
        if (!is_array($sections) || count($sections) > self::MAX_SECTIONS) {
            throw new \invalid_parameter_exception('A page must contain at most 100 sections.');
        }

        $presets = catalog::presets();
        $seenids = [];
        $normalised = [];
        foreach ($sections as $position => $section) {
            if (!is_array($section)) {
                throw new \invalid_parameter_exception('Every page section must be an object.');
            }
            $presetkey = clean_param((string)($section['preset'] ?? ''), PARAM_ALPHANUMEXT);
            if (!isset($presets[$presetkey])) {
                throw new \invalid_parameter_exception('Unknown component preset at position ' . ($position + 1) . '.');
            }
            $preset = $presets[$presetkey];
            $id = clean_param((string)($section['id'] ?? ''), PARAM_ALPHANUMEXT);
            if ($id === '' || isset($seenids[$id])) {
                throw new \invalid_parameter_exception('Section identifiers must be present and unique.');
            }
            $seenids[$id] = true;

            $items = $section['items'] ?? [];
            if (!is_array($items) || count($items) > self::MAX_ITEMS) {
                throw new \invalid_parameter_exception('A component can contain at most 24 items.');
            }
            $cleanitems = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new \invalid_parameter_exception('Component items must be objects.');
                }
                $cleanitems[] = [
                    'title' => clean_param((string)($item['title'] ?? ''), PARAM_TEXT),
                    'text' => clean_param((string)($item['text'] ?? ''), PARAM_TEXT),
                    'url' => $this->url((string)($item['url'] ?? '')),
                    'value' => clean_param((string)($item['value'] ?? ''), PARAM_TEXT),
                ];
            }

            $normalised[] = [
                'id' => $id,
                'preset' => $presetkey,
                'type' => $preset['type'],
                'variant' => $preset['variant'],
                'title' => clean_param((string)($section['title'] ?? ''), PARAM_TEXT),
                'body' => clean_text((string)($section['body'] ?? ''), FORMAT_HTML),
                'mediaurl' => $this->url((string)($section['mediaurl'] ?? '')),
                'primarylabel' => clean_param((string)($section['primarylabel'] ?? ''), PARAM_TEXT),
                'primaryurl' => $this->url((string)($section['primaryurl'] ?? '')),
                'secondarylabel' => clean_param((string)($section['secondarylabel'] ?? ''), PARAM_TEXT),
                'secondaryurl' => $this->url((string)($section['secondaryurl'] ?? '')),
                'items' => $cleanitems,
            ];
        }

        $settings = is_array($definition['settings'] ?? null) ? $definition['settings'] : [];
        $width = in_array(($settings['width'] ?? ''), ['narrow', 'standard', 'wide', 'full'], true)
            ? $settings['width'] : 'standard';
        $density = in_array(($settings['density'] ?? ''), ['compact', 'comfortable', 'spacious'], true)
            ? $settings['density'] : 'comfortable';

        return [
            'schema_version' => catalog::VERSION,
            'settings' => [
                'width' => $width,
                'density' => $density,
            ],
            'sections' => $normalised,
        ];
    }

    /**
     * Allow relative Moodle paths and absolute HTTPS URLs.
     */
    private function url(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return clean_param($url, PARAM_LOCALURL);
        }
        $clean = clean_param($url, PARAM_URL);
        if (!preg_match('#^https://#i', $clean)) {
            throw new \invalid_parameter_exception('Only relative paths and HTTPS URLs are accepted.');
        }
        return $clean;
    }
}
