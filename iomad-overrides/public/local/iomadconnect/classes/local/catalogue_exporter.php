<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect\local;

use local_iomad\company;

/**
 * Cursor-based tenant course and category export.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue_exporter {
    /**
     * Export a bounded page of category/course changes.
     *
     * @param int $companyid Company.
     * @param string $cursor Opaque cursor.
     * @param int $limit Limit.
     * @return array Events and next cursor.
     */
    public function export(int $companyid, string $cursor = '', int $limit = 100): array {
        global $DB;

        $limit = max(1, min(500, $limit));
        $after = $this->decode_cursor($cursor);
        $courseids = array_map('intval', array_keys((new company($companyid))->get_menu_courses(
            shared: true,
            default: false,
            includehidden: true,
        )));
        $entities = [];
        if ($courseids) {
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
            foreach ($DB->get_records_select('course', "id {$insql}", $params) as $course) {
                $entities[] = [
                    'type' => 'course',
                    'id' => (int)$course->id,
                    'modified' => (int)$course->timemodified,
                    'record' => $course,
                ];
                foreach ($this->category_ancestors((int)$course->category) as $category) {
                    $entities['category:' . $category->id] = [
                        'type' => 'category',
                        'id' => (int)$category->id,
                        'modified' => (int)$category->timemodified,
                        'record' => $category,
                    ];
                }
            }
        }
        $entities = array_values($entities);
        usort($entities, static function (array $a, array $b): int {
            return [$a['modified'], $a['type'], $a['id']] <=> [$b['modified'], $b['type'], $b['id']];
        });
        $entities = array_values(array_filter(
            $entities,
            static fn(array $entity): bool => [$entity['modified'], $entity['type'], $entity['id']] > $after,
        ));
        $page = array_slice($entities, 0, $limit);
        $events = [];
        foreach ($page as $entity) {
            $record = $entity['record'];
            if ($entity['type'] === 'category') {
                $externalid = $record->idnumber ?: 'moodle-category-' . $record->id;
                $payload = [
                    'externalid' => $externalid,
                    'name' => $record->name,
                    'parent_externalid' => $record->parent
                        ? $this->category_externalid((int)$record->parent)
                        : '',
                    'visible' => (bool)$record->visible,
                ];
            } else {
                $externalid = $record->idnumber ?: $record->shortname;
                $payload = [
                    'externalid' => $externalid,
                    'shortname' => $record->shortname,
                    'fullname' => $record->fullname,
                    'category_externalid' => $this->category_externalid((int)$record->category),
                    'visible' => (bool)$record->visible,
                    'format' => $record->format,
                ];
            }
            $events[] = [
                'entitytype' => $entity['type'],
                'entityid' => $externalid,
                'action' => 'upsert',
                'modified' => $entity['modified'],
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ];
        }
        $last = $page ? end($page) : null;
        $nextcursor = $last
            ? $this->encode_cursor([(int)$last['modified'], $last['type'], (int)$last['id']])
            : $cursor;
        return [
            'events' => $events,
            'cursor' => $nextcursor,
            'hasmore' => count($entities) > count($page),
        ];
    }

    /**
     * Category stable ID.
     *
     * @param int $categoryid Category.
     * @return string
     */
    private function category_externalid(int $categoryid): string {
        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id,idnumber', MUST_EXIST);
        return $category->idnumber ?: 'moodle-category-' . $category->id;
    }

    /**
     * Return a category and all real ancestors in parent-first order.
     *
     * @param int $categoryid Category.
     * @return array
     */
    private function category_ancestors(int $categoryid): array {
        global $DB;

        $categories = [];
        while ($categoryid > 0) {
            $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
            $categories[] = $category;
            $categoryid = (int)$category->parent;
        }
        return array_reverse($categories);
    }

    /**
     * Decode cursor.
     *
     * @param string $cursor Cursor.
     * @return array
     */
    private function decode_cursor(string $cursor): array {
        if ($cursor === '') {
            return [0, '', 0];
        }
        $encoded = strtr($cursor, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new \invalid_parameter_exception('Invalid catalogue cursor.');
        }
        try {
            $value = json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \invalid_parameter_exception('Invalid catalogue cursor.');
        }
        if (!is_array($value) || count($value) !== 3) {
            throw new \invalid_parameter_exception('Invalid catalogue cursor.');
        }
        return [(int)$value[0], (string)$value[1], (int)$value[2]];
    }

    /**
     * Encode cursor.
     *
     * @param array $value Cursor tuple.
     * @return string
     */
    private function encode_cursor(array $value): string {
        return rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }
}
