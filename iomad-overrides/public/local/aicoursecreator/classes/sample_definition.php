<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

/**
 * Sanitised sample used by local demos and executable acceptance tests.
 */
final class sample_definition {
    public static function create(string $suffix = 'demo'): array {
        $suffix = clean_param($suffix, PARAM_ALPHANUMEXT) ?: 'demo';
        return [
            'schema_version' => 1,
            'course' => [
                'fullname' => 'Digital Safety Foundations',
                'shortname' => "DSF-{$suffix}",
                'idnumber' => "dsf-{$suffix}",
                'summary' => '<p>A short, sanitised course about practical digital safety.</p>',
                'format' => 'topics',
                'language' => 'en',
            ],
            'sections' => [
                [
                    'id' => "welcome-{$suffix}",
                    'title' => 'Welcome',
                    'summary' => '<p>Set learning goals and establish safe working practices.</p>',
                    'items' => [
                        [
                            'id' => "welcome-page-{$suffix}",
                            'type' => 'page',
                            'name' => 'Learning goals',
                            'content' => '<p>Identify strong authentication and safe information-sharing practices.</p>',
                        ],
                        [
                            'id' => "reference-url-{$suffix}",
                            'type' => 'url',
                            'name' => 'Reference resource',
                            'content' => '<p>Review this public reference after the lesson.</p>',
                            'url' => 'https://www.cisa.gov/secure-our-world',
                        ],
                    ],
                    'quizzes' => [],
                ],
                [
                    'id' => "practice-{$suffix}",
                    'title' => 'Practice',
                    'summary' => '<p>Apply the guidance to short scenarios.</p>',
                    'items' => [
                        [
                            'id' => "h5p-blueprint-{$suffix}",
                            'type' => 'h5p_blueprint',
                            'name' => 'Authentication scenario',
                            'content' => '<p>Branching scenario: choose the safer sign-in response and explain why.</p>',
                        ],
                    ],
                    'quizzes' => [
                        [
                            'id' => "knowledge-check-{$suffix}",
                            'name' => 'Knowledge check',
                            'intro' => '<p>Check your understanding.</p>',
                            'questions' => [
                                [
                                    'id' => "mcq-{$suffix}",
                                    'type' => 'multichoice',
                                    'name' => 'Strong authentication',
                                    'questiontext' => '<p>Which option provides the strongest authentication?</p>',
                                    'generalfeedback' => '<p>Use a unique password and a second factor.</p>',
                                    'answers' => [
                                        [
                                            'text' => 'A unique password plus a security key',
                                            'correct' => true,
                                            'feedback' => '<p>Correct.</p>',
                                        ],
                                        [
                                            'text' => 'A reused password',
                                            'correct' => false,
                                            'feedback' => '<p>Reused passwords increase risk.</p>',
                                        ],
                                    ],
                                ],
                                [
                                    'id' => "truefalse-{$suffix}",
                                    'type' => 'truefalse',
                                    'name' => 'Password reuse',
                                    'questiontext' => '<p>Reusing one password across services is recommended.</p>',
                                    'generalfeedback' => '<p>Use a unique password for each service.</p>',
                                    'correct' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
