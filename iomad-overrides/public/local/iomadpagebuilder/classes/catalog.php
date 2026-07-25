<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Versioned catalogue of page components and starter pages.
 */
final class catalog {
    public const VERSION = 1;

    /** @var array<string, string> */
    private const COMPONENTS = [
        'hero' => 'Hero and primary action',
        'stats' => 'Key metrics',
        'announcement' => 'Announcements and notices',
        'richtext' => 'Formatted editorial content',
        'imagetext' => 'Image and supporting text',
        'video' => 'Responsive video',
        'courses' => 'Course discovery',
        'categories' => 'Category discovery',
        'progress' => 'Learner progress',
        'calendar' => 'Academic calendar',
        'timeline' => 'Process or history timeline',
        'quotes' => 'Testimonials and quotations',
        'people' => 'Faculty and staff',
        'faq' => 'Frequently asked questions',
        'cta' => 'Call to action',
        'contacts' => 'Contact details',
        'policies' => 'Policies and compliance',
        'downloads' => 'Files and resources',
        'form' => 'Embedded form',
        'report' => 'Embedded report summary',
    ];

    /** @var array<string, string> */
    private const VARIANTS = [
        'clean' => 'Clean',
        'bordered' => 'Bordered',
        'contrast' => 'High contrast',
        'media' => 'Media led',
        'compact' => 'Compact',
        'spacious' => 'Spacious',
        'rtl' => 'RTL ready',
    ];

    /**
     * Return all 140 component presets.
     *
     * @return array<string, array>
     */
    public static function presets(): array {
        $presets = [];
        foreach (self::COMPONENTS as $type => $purpose) {
            foreach (self::VARIANTS as $variant => $variantlabel) {
                $key = $type . '_' . $variant;
                $presets[$key] = [
                    'key' => $key,
                    'type' => $type,
                    'variant' => $variant,
                    'name' => $variantlabel . ' ' . $purpose,
                    'purpose' => $purpose,
                    'defaults' => self::defaults($type),
                ];
            }
        }
        return $presets;
    }

    /**
     * Return 30 starter page templates.
     *
     * @return array<string, array>
     */
    public static function templates(): array {
        $names = [
            'school_home' => 'School home',
            'school_student_dashboard' => 'School student dashboard',
            'school_parent_portal' => 'School parent portal',
            'school_teacher_portal' => 'School teacher portal',
            'school_admissions' => 'School admissions',
            'school_academic_year' => 'School academic year',
            'university_home' => 'University home',
            'university_student_dashboard' => 'University student dashboard',
            'university_faculty_portal' => 'University faculty portal',
            'university_programme' => 'University programme',
            'university_admissions' => 'University admissions',
            'university_research' => 'University research',
            'college_home' => 'College home',
            'college_department' => 'College department',
            'college_student_services' => 'College student services',
            'training_home' => 'Training organisation home',
            'training_catalogue' => 'Training catalogue',
            'training_compliance' => 'Training compliance',
            'training_manager_dashboard' => 'Training manager dashboard',
            'learner_dashboard' => 'Learner dashboard',
            'educator_dashboard' => 'Educator dashboard',
            'tenant_manager_dashboard' => 'Tenant manager dashboard',
            'executive_dashboard' => 'Executive dashboard',
            'course_launch' => 'Course launch',
            'video_course_launch' => 'Video course launch',
            'event_registration' => 'Event registration',
            'policy_hub' => 'Policy hub',
            'support_hub' => 'Support hub',
            'commerce_shopfront' => 'Course shopfront',
            'minimal_login_home' => 'Minimal login home',
        ];

        $templates = [];
        $presetsets = [
            ['hero_clean', 'announcement_compact', 'courses_media', 'stats_bordered', 'cta_contrast', 'contacts_clean'],
            ['hero_compact', 'progress_clean', 'calendar_bordered', 'courses_clean', 'downloads_compact', 'contacts_clean'],
            ['hero_media', 'categories_clean', 'imagetext_spacious', 'people_bordered', 'faq_clean', 'cta_contrast'],
        ];
        $index = 0;
        foreach ($names as $key => $name) {
            $templates[$key] = [
                'key' => $key,
                'name' => $name,
                'description' => 'Accessible starter layout for ' . strtolower($name) . '.',
                'definition' => self::definition_from_presets($presetsets[$index % count($presetsets)], $name),
            ];
            $index++;
        }
        return $templates;
    }

    /**
     * Get one starter definition.
     */
    public static function template(string $key): array {
        $templates = self::templates();
        if (!isset($templates[$key])) {
            throw new \invalid_parameter_exception('Unknown page template.');
        }
        return $templates[$key]['definition'];
    }

    /**
     * Default fields for a component type.
     */
    private static function defaults(string $type): array {
        return [
            'title' => self::COMPONENTS[$type],
            'body' => '',
            'mediaurl' => '',
            'primarylabel' => '',
            'primaryurl' => '',
            'secondarylabel' => '',
            'secondaryurl' => '',
            'items' => [],
        ];
    }

    /**
     * Build a complete page definition from preset keys.
     */
    private static function definition_from_presets(array $presetkeys, string $title): array {
        $presets = self::presets();
        $sections = [];
        foreach ($presetkeys as $position => $presetkey) {
            $preset = $presets[$presetkey];
            $section = $preset['defaults'];
            $section['id'] = 'section-' . ($position + 1);
            $section['preset'] = $presetkey;
            $section['type'] = $preset['type'];
            $section['variant'] = $preset['variant'];
            if ($position === 0) {
                $section['title'] = $title;
            }
            $sections[] = $section;
        }
        return [
            'schema_version' => self::VERSION,
            'settings' => [
                'width' => 'wide',
                'density' => 'comfortable',
            ],
            'sections' => $sections,
        ];
    }
}
