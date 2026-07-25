<?php
// This file is part of Moodle - http://moodle.org/

namespace format_iomadvideo\local;

use context_module;
use core_media_manager;
use moodle_url;

/**
 * Builds a playlist from visible Moodle URL and Resource activities.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class playlist_service {
    /** @var \stdClass */
    private \stdClass $course;

    /**
     * Create a playlist service for one course.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    /**
     * Return media items in course section order.
     *
     * @return array
     */
    public function get_items(): array {
        $modinfo = get_fast_modinfo($this->course);
        $items = [];
        $position = 0;

        foreach ($modinfo->get_listed_section_info_all() as $section) {
            if (!$section || !$section->uservisible) {
                continue;
            }
            foreach ($section->get_sequence_cm_infos() as $cm) {
                if (!$cm->uservisible || !$cm->url) {
                    continue;
                }
                $mediaurl = $this->get_media_url($cm);
                if (!$mediaurl) {
                    continue;
                }

                $position++;
                $items[] = [
                    'cmid' => $cm->id,
                    'position' => $position,
                    'title' => $cm->get_formatted_name(),
                    'section' => get_section_name($this->course, $section),
                    'activityurl' => $cm->url->out(false),
                    'playerid' => 'iomadvideo-player-' . $cm->id,
                    'player' => core_media_manager::instance()->embed_url(
                        $mediaurl,
                        $cm->get_formatted_name(),
                        1280,
                        720,
                        [
                            core_media_manager::OPTION_BLOCK => true,
                            core_media_manager::OPTION_TRUSTED => false,
                        ],
                    ),
                ];
            }
        }

        return $items;
    }

    /**
     * Resolve the first supported media URL for an activity.
     *
     * @param \cm_info $cm Course module.
     * @return moodle_url|null
     */
    private function get_media_url(\cm_info $cm): ?moodle_url {
        if ($cm->modname === 'url') {
            return $this->get_url_activity_media($cm);
        }
        if ($cm->modname === 'resource') {
            return $this->get_resource_media($cm);
        }
        return null;
    }

    /**
     * Resolve an external URL activity through the core media players.
     *
     * @param \cm_info $cm Course module.
     * @return moodle_url|null
     */
    private function get_url_activity_media(\cm_info $cm): ?moodle_url {
        global $DB;

        $externalurl = $DB->get_field('url', 'externalurl', ['id' => $cm->instance]);
        if (!$externalurl || clean_param($externalurl, PARAM_URL) !== $externalurl) {
            return null;
        }

        $url = new moodle_url($externalurl);
        if (!core_media_manager::instance()->can_embed_url($url)) {
            return null;
        }
        return $url;
    }

    /**
     * Resolve the first video in a Resource activity using the File API.
     *
     * @param \cm_info $cm Course module.
     * @return moodle_url|null
     */
    private function get_resource_media(\cm_info $cm): ?moodle_url {
        $context = context_module::instance($cm->id);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_resource',
            'content',
            0,
            'sortorder, filepath, filename',
            false,
        );
        foreach ($files as $file) {
            if (strpos((string)$file->get_mimetype(), 'video/') !== 0) {
                continue;
            }
            $url = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_resource',
                'content',
                0,
                $file->get_filepath(),
                $file->get_filename(),
                false,
            );
            if (core_media_manager::instance()->can_embed_url($url)) {
                return $url;
            }
        }
        return null;
    }
}
