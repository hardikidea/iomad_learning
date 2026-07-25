<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates and normalises the provider-neutral course definition.
 */
final class course_schema_validator {
    private const MAX_SECTIONS = 30;
    private const MAX_ITEMS = 40;
    private const MAX_QUESTIONS = 100;
    private const ITEM_TYPES = ['page', 'url', 'h5p_blueprint'];
    private const QUESTION_TYPES = ['multichoice', 'truefalse'];

    public function from_json(string $json): array {
        $json = trim($json);
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json) ?? $json;
        }
        try {
            $definition = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \invalid_parameter_exception('Invalid JSON: ' . $exception->getMessage());
        }
        if (!is_array($definition)) {
            throw new \invalid_parameter_exception('Course definition must be an object.');
        }
        return $this->validate($definition);
    }

    public function validate(array $input): array {
        if ((int)($input['schema_version'] ?? 0) !== 1) {
            throw new \invalid_parameter_exception('Unsupported schema_version.');
        }
        $course = (array)($input['course'] ?? []);
        $fullname = $this->plain($course['fullname'] ?? '', 254, 'course.fullname');
        $shortname = $this->plain($course['shortname'] ?? '', 100, 'course.shortname');
        $idnumber = clean_param((string)($course['idnumber'] ?? ''), PARAM_ALPHANUMEXT);
        if ($shortname === '' || $idnumber === '') {
            throw new \invalid_parameter_exception('course.shortname and course.idnumber are required.');
        }

        $sectionsinput = $input['sections'] ?? [];
        if (!is_array($sectionsinput) || $sectionsinput === [] || count($sectionsinput) > self::MAX_SECTIONS) {
            throw new \invalid_parameter_exception('sections must contain between 1 and 30 entries.');
        }
        $seenids = [];
        $sections = [];
        $questioncount = 0;
        foreach (array_values($sectionsinput) as $sectionindex => $sectioninput) {
            $section = (array)$sectioninput;
            $sectionid = $this->stable_id($section['id'] ?? "section-" . ($sectionindex + 1), $seenids);
            $itemsinput = $section['items'] ?? [];
            $quizzesinput = $section['quizzes'] ?? [];
            if (!is_array($itemsinput) || count($itemsinput) > self::MAX_ITEMS) {
                throw new \invalid_parameter_exception("sections[{$sectionindex}].items exceeds the limit.");
            }
            if (!is_array($quizzesinput)) {
                throw new \invalid_parameter_exception("sections[{$sectionindex}].quizzes must be an array.");
            }
            $items = [];
            foreach (array_values($itemsinput) as $itemindex => $iteminput) {
                $item = (array)$iteminput;
                $type = clean_param((string)($item['type'] ?? ''), PARAM_ALPHANUMEXT);
                if (!in_array($type, self::ITEM_TYPES, true)) {
                    throw new \invalid_parameter_exception("Unsupported item type at section {$sectionid}.");
                }
                $normalised = [
                    'id' => $this->stable_id($item['id'] ?? "{$sectionid}-item-" . ($itemindex + 1), $seenids),
                    'type' => $type,
                    'name' => $this->plain($item['name'] ?? '', 254, 'item.name'),
                    'content' => $this->html($item['content'] ?? ''),
                ];
                if ($type === 'url') {
                    $normalised['url'] = $this->url($item['url'] ?? '');
                }
                $items[] = $normalised;
            }
            $quizzes = [];
            foreach (array_values($quizzesinput) as $quizindex => $quizinput) {
                $quiz = (array)$quizinput;
                $questions = [];
                foreach (array_values((array)($quiz['questions'] ?? [])) as $questionindex => $questioninput) {
                    if (++$questioncount > self::MAX_QUESTIONS) {
                        throw new \invalid_parameter_exception('Course definition exceeds 100 quiz questions.');
                    }
                    $question = (array)$questioninput;
                    $type = clean_param((string)($question['type'] ?? ''), PARAM_ALPHA);
                    if (!in_array($type, self::QUESTION_TYPES, true)) {
                        throw new \invalid_parameter_exception('Unsupported quiz question type.');
                    }
                    $normalisedquestion = [
                        'id' => $this->stable_id(
                            $question['id'] ?? "{$sectionid}-q-" . ($questionindex + 1),
                            $seenids
                        ),
                        'type' => $type,
                        'name' => $this->plain($question['name'] ?? '', 254, 'question.name'),
                        'questiontext' => $this->html($question['questiontext'] ?? ''),
                        'generalfeedback' => $this->html($question['generalfeedback'] ?? ''),
                    ];
                    if ($type === 'truefalse') {
                        $normalisedquestion['correct'] = !empty($question['correct']);
                    } else {
                        $answers = array_values((array)($question['answers'] ?? []));
                        if (count($answers) < 2 || count($answers) > 10) {
                            throw new \invalid_parameter_exception('Multiple-choice questions need 2 to 10 answers.');
                        }
                        $correctanswers = 0;
                        $normalisedquestion['answers'] = [];
                        foreach ($answers as $answerinput) {
                            $answer = (array)$answerinput;
                            $correct = !empty($answer['correct']);
                            $correctanswers += $correct ? 1 : 0;
                            $normalisedquestion['answers'][] = [
                                'text' => $this->plain($answer['text'] ?? '', 1000, 'answer.text'),
                                'correct' => $correct,
                                'feedback' => $this->html($answer['feedback'] ?? ''),
                            ];
                        }
                        if ($correctanswers !== 1) {
                            throw new \invalid_parameter_exception(
                                'Each multiple-choice question must have exactly one correct answer.'
                            );
                        }
                    }
                    $questions[] = $normalisedquestion;
                }
                if ($questions === []) {
                    throw new \invalid_parameter_exception('Each quiz must contain at least one question.');
                }
                $quizzes[] = [
                    'id' => $this->stable_id($quiz['id'] ?? "{$sectionid}-quiz-" . ($quizindex + 1), $seenids),
                    'name' => $this->plain($quiz['name'] ?? '', 254, 'quiz.name'),
                    'intro' => $this->html($quiz['intro'] ?? ''),
                    'questions' => $questions,
                ];
            }
            $sections[] = [
                'id' => $sectionid,
                'title' => $this->plain($section['title'] ?? '', 254, 'section.title'),
                'summary' => $this->html($section['summary'] ?? ''),
                'items' => $items,
                'quizzes' => $quizzes,
            ];
        }

        return [
            'schema_version' => 1,
            'course' => [
                'fullname' => $fullname,
                'shortname' => $shortname,
                'idnumber' => $idnumber,
                'summary' => $this->html($course['summary'] ?? ''),
                'format' => 'topics',
                'language' => clean_param((string)($course['language'] ?? ''), PARAM_LANG),
            ],
            'sections' => $sections,
        ];
    }

    private function stable_id(mixed $value, array &$seenids): string {
        $id = clean_param((string)$value, PARAM_ALPHANUMEXT);
        if ($id === '' || \core_text::strlen($id) > 100 || isset($seenids[$id])) {
            throw new \invalid_parameter_exception('Definition IDs must be non-empty and unique.');
        }
        $seenids[$id] = true;
        return $id;
    }

    private function plain(mixed $value, int $maxlength, string $field): string {
        $text = trim(strip_tags((string)$value));
        if ($text === '' || \core_text::strlen($text) > $maxlength) {
            throw new \invalid_parameter_exception("{$field} is required and must not exceed {$maxlength} characters.");
        }
        return $text;
    }

    private function html(mixed $value): string {
        return clean_text((string)$value, FORMAT_HTML);
    }

    private function url(mixed $value): string {
        $url = trim((string)$value);
        if (!preg_match('#^https://#i', $url)) {
            throw new \invalid_parameter_exception('External content URLs must use HTTPS.');
        }
        return clean_param($url, PARAM_URL);
    }
}
