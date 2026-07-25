<?php
// This file is part of Moodle - http://moodle.org/

/**
 * IOMAD video course format.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/topics/lib.php');

use core\output\inplace_editable;

/**
 * Video-first format retaining the core sections editing model.
 */
class format_iomadvideo extends format_topics {
    /**
     * The format does not use legacy activity indentation.
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Return the page title.
     *
     * @return string
     */
    public function page_title(): string {
        return get_string('sectionoutline');
    }

    /**
     * Define course format options.
     *
     * @param bool $foreditform Whether labels and form controls are required.
     * @return array
     */
    public function course_format_options($foreditform = false) {
        $options = parent::course_format_options($foreditform);
        $videooptions = [
            'videolayout' => [
                'default' => 'cinema',
                'type' => PARAM_ALPHA,
            ],
            'playlistcollapsed' => [
                'default' => 0,
                'type' => PARAM_BOOL,
            ],
            'autoadvance' => [
                'default' => 0,
                'type' => PARAM_BOOL,
            ],
            'focusmode' => [
                'default' => 0,
                'type' => PARAM_BOOL,
            ],
        ];

        if ($foreditform) {
            $videooptions['videolayout'] += [
                'label' => new lang_string('videolayout', 'format_iomadvideo'),
                'element_type' => 'select',
                'element_attributes' => [[
                    'cinema' => new lang_string('layoutcinema', 'format_iomadvideo'),
                    'classroom' => new lang_string('layoutclassroom', 'format_iomadvideo'),
                    'split' => new lang_string('layoutsplit', 'format_iomadvideo'),
                    'theatre' => new lang_string('layouttheatre', 'format_iomadvideo'),
                    'compact' => new lang_string('layoutcompact', 'format_iomadvideo'),
                    'minimal' => new lang_string('layoutminimal', 'format_iomadvideo'),
                ]],
                'help' => 'videolayout',
                'help_component' => 'format_iomadvideo',
            ];
            foreach (['playlistcollapsed', 'autoadvance', 'focusmode'] as $name) {
                $videooptions[$name] += [
                    'label' => new lang_string($name, 'format_iomadvideo'),
                    'element_type' => 'select',
                    'element_attributes' => [[
                        0 => new lang_string('no'),
                        1 => new lang_string('yes'),
                    ]],
                    'help' => $name,
                    'help_component' => 'format_iomadvideo',
                ];
            }
        }

        return array_merge($options, $videooptions);
    }

    /**
     * Return format data available to external clients.
     *
     * @return array
     */
    public function get_config_for_external() {
        return $this->get_format_options();
    }
}

/**
 * Update section names in place.
 *
 * @param string $itemtype Item type.
 * @param int $itemid Section ID.
 * @param mixed $newvalue New section name.
 * @return inplace_editable|null
 */
function format_iomadvideo_inplace_editable($itemtype, $itemid, $newvalue) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype !== 'sectionname' && $itemtype !== 'sectionnamenl') {
        return null;
    }

    $section = $DB->get_record_sql(
        'SELECT s.*
           FROM {course_sections} s
           JOIN {course} c ON s.course = c.id
          WHERE s.id = :sectionid AND c.format = :format',
        ['sectionid' => $itemid, 'format' => 'iomadvideo'],
        MUST_EXIST,
    );
    return course_get_format($section->course)->inplace_editable_update_section_name(
        $section,
        $itemtype,
        $newvalue,
    );
}
