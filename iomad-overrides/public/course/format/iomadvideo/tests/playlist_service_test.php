<?php
// This file is part of Moodle - http://moodle.org/

namespace format_iomadvideo;

use format_iomadvideo\local\playlist_service;

/**
 * Playlist visibility and media detection tests.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_iomadvideo\local\playlist_service
 */
final class playlist_service_test extends \advanced_testcase {
    /**
     * URL videos are ordered and ordinary URLs are excluded.
     */
    public function test_visible_embeddable_urls_form_playlist(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'iomadvideo',
            'numsections' => 2,
        ]);
        $this->getDataGenerator()->create_module('url', [
            'course' => $course,
            'section' => 1,
            'name' => 'Intro video',
            'externalurl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $this->getDataGenerator()->create_module('url', [
            'course' => $course,
            'section' => 2,
            'name' => 'Reference site',
            'externalurl' => 'https://example.com/reference',
        ]);

        $items = (new playlist_service($course))->get_items();
        $this->assertCount(1, $items);
        $this->assertSame('Intro video', $items[0]['title']);
        $this->assertSame(1, $items[0]['position']);
        $this->assertStringContainsString('youtube', strtolower($items[0]['player']));
    }

    /**
     * Hidden activities do not leak through the playlist.
     */
    public function test_hidden_activity_is_excluded_for_learner(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'iomadvideo']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('url', [
            'course' => $course,
            'name' => 'Hidden video',
            'externalurl' => 'https://vimeo.com/76979871',
        ]);
        set_coursemodule_visible($module->cmid, 0);
        rebuild_course_cache($course->id, true);
        $this->setUser($student);

        $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $this->assertSame([], (new playlist_service($course))->get_items());
    }
}
