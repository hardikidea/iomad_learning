<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle 5.1 core AI provider adapter.
 */
final class core_ai_gateway implements ai_gateway {
    public function generate(\stdClass $draft, int $contextid, int $userid): array {
        $options = json_decode($draft->optionsjson, true, 16, JSON_THROW_ON_ERROR);
        $prompt = $this->prompt($draft, $options);
        $action = new \core_ai\aiactions\generate_text(
            contextid: $contextid,
            userid: $userid,
            prompttext: $prompt,
        );
        $manager = \core\di::get(\core_ai\manager::class);
        $response = $manager->process_action($action);
        if (!$response->get_success()) {
            throw new \moodle_exception('providerfailed', 'local_aicoursecreator');
        }
        $data = $response->get_response_data();
        $content = (string)($data['generatedcontent'] ?? '');
        try {
            $definition = (new course_schema_validator())->from_json($content);
        } catch (\Throwable $exception) {
            throw new \moodle_exception('providerinvalid', 'local_aicoursecreator', '', null, $exception->getMessage());
        }
        return [
            'definition' => $definition,
            'provider' => null,
            'model' => isset($data['model']) ? clean_param((string)$data['model'], PARAM_TEXT) : null,
        ];
    }

    private function prompt(\stdClass $draft, array $options): string {
        $sectioncount = min(30, max(1, (int)($options['sectioncount'] ?? 5)));
        $audience = trim((string)($options['audience'] ?? 'general learners'));
        $tone = trim((string)($options['tone'] ?? 'professional'));
        $schemaexample = json_encode([
            'schema_version' => 1,
            'course' => [
                'fullname' => '',
                'shortname' => '',
                'idnumber' => '',
                'summary' => '',
                'format' => 'topics',
                'language' => 'en',
            ],
            'sections' => [[
                'id' => 'section-1',
                'title' => '',
                'summary' => '',
                'items' => [[
                    'id' => 'item-1',
                    'type' => 'page',
                    'name' => '',
                    'content' => '',
                ]],
                'quizzes' => [[
                    'id' => 'quiz-1',
                    'name' => '',
                    'intro' => '',
                    'questions' => [[
                        'id' => 'question-1',
                        'type' => 'truefalse',
                        'name' => '',
                        'questiontext' => '',
                        'generalfeedback' => '',
                        'correct' => true,
                    ]],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return <<<PROMPT
Create a reviewed course draft as strict JSON only. Do not include markdown fences.
Use schema_version 1 and this exact structure:
{$schemaexample}
Allowed item types are page, url, and h5p_blueprint. URL items require an HTTPS url.
Allowed question types are multichoice and truefalse. Multiple-choice questions must have exactly one correct answer.
All ids must be unique ASCII slugs. Use safe semantic HTML only. Never include scripts, credentials, personal data, or external tracking.
Create {$sectioncount} sections. Course title: {$draft->title}
Audience: {$audience}
Tone: {$tone}
Course brief: {$draft->brief}
PROMPT;
    }
}
