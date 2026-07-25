<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

use core_question\local\bank\question_version_status;
use local_iomad\company;

defined('MOODLE_INTERNAL') || die();

/**
 * Publishes an approved definition through supported Moodle and IOMAD APIs.
 */
final class course_publisher {
    public function publish(int $draftid, int $companyid, int $userid): \stdClass {
        global $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $repository = new draft_repository();
        $draft = $repository->get($draftid, $companyid);
        if ($draft->status === 'published' && $draft->courseid) {
            return get_course((int)$draft->courseid);
        }
        if ($draft->status !== 'approved') {
            throw new \moodle_exception('invalidtransition', 'local_aicoursecreator', '', (object)[
                'from' => $draft->status,
                'to' => 'published',
            ]);
        }
        $definition = $repository->definition($draft);
        $company = new company($companyid);
        $categoryid = (int)$company->get('coursecategoryid');
        if ($categoryid <= 0) {
            throw new \moodle_exception('invalidcategory');
        }
        if (
            $DB->record_exists('course', ['shortname' => $definition['course']['shortname']])
                || $DB->record_exists('course', ['idnumber' => $definition['course']['idnumber']])
        ) {
            throw new \moodle_exception('shortnametaken', '', '', $definition['course']['shortname']);
        }

        $transaction = $DB->start_delegated_transaction();
        $course = create_course((object)[
            'fullname' => $definition['course']['fullname'],
            'shortname' => $definition['course']['shortname'],
            'idnumber' => $definition['course']['idnumber'],
            'category' => $categoryid,
            'summary' => $definition['course']['summary'],
            'summaryformat' => FORMAT_HTML,
            'format' => 'topics',
            'lang' => $definition['course']['language'] ?: '',
            'visible' => 0,
            'enablecompletion' => 1,
            'numsections' => count($definition['sections']),
        ]);
        $company->add_course($course, 0, true, false);
        $PAGE->set_context(\context_course::instance($course->id));

        foreach ($definition['sections'] as $index => $section) {
            $sectionnumber = $index + 1;
            course_create_sections_if_missing($course, [$sectionnumber]);
            $sectioninfo = get_fast_modinfo($course)->get_section_info($sectionnumber, MUST_EXIST);
            course_update_section($course, $sectioninfo, [
                'name' => $section['title'],
                'summary' => $section['summary'],
                'summaryformat' => FORMAT_HTML,
                'visible' => 1,
            ]);
            foreach ($section['items'] as $item) {
                $this->add_item($course, $sectionnumber, $item);
            }
            foreach ($section['quizzes'] as $quiz) {
                $this->add_quiz($course, $sectionnumber, $quiz);
            }
        }
        rebuild_course_cache($course->id, true);
        $repository->mark_published($draftid, $companyid, $userid, $course->id);
        $transaction->allow_commit();
        return get_course($course->id);
    }

    private function add_item(\stdClass $course, int $sectionnumber, array $item): void {
        if ($item['type'] === 'url') {
            $this->add_module($course, $sectionnumber, 'url', [
                'name' => $item['name'],
                'intro' => $item['content'],
                'introformat' => FORMAT_HTML,
                'externalurl' => $item['url'],
                'display' => RESOURCELIB_DISPLAY_AUTO,
                'printintro' => 1,
            ]);
            return;
        }

        $content = $item['content'];
        if ($item['type'] === 'h5p_blueprint') {
            $content = '<div class="alert alert-info">'
                . s('H5P blueprint: attach a reviewed .h5p package before enabling this activity.')
                . '</div>' . $content;
        }
        $this->add_module($course, $sectionnumber, 'page', [
            'name' => $item['name'],
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'display' => RESOURCELIB_DISPLAY_OPEN,
            'printintro' => 0,
            'printlastmodified' => 0,
        ]);
    }

    private function add_quiz(\stdClass $course, int $sectionnumber, array $quizdefinition): void {
        global $DB;

        $reviewdefaults = [
            'attemptduring' => 1,
            'correctnessduring' => 1,
            'maxmarksduring' => 1,
            'marksduring' => 1,
            'specificfeedbackduring' => 1,
            'generalfeedbackduring' => 1,
            'rightanswerduring' => 1,
            'overallfeedbackduring' => 0,
            'attemptimmediately' => 1,
            'correctnessimmediately' => 1,
            'maxmarksimmediately' => 1,
            'marksimmediately' => 1,
            'specificfeedbackimmediately' => 1,
            'generalfeedbackimmediately' => 1,
            'rightanswerimmediately' => 1,
            'overallfeedbackimmediately' => 1,
            'attemptopen' => 1,
            'correctnessopen' => 1,
            'maxmarksopen' => 1,
            'marksopen' => 1,
            'specificfeedbackopen' => 1,
            'generalfeedbackopen' => 1,
            'rightansweropen' => 1,
            'overallfeedbackopen' => 1,
            'attemptclosed' => 1,
            'correctnessclosed' => 1,
            'maxmarksclosed' => 1,
            'marksclosed' => 1,
            'specificfeedbackclosed' => 1,
            'generalfeedbackclosed' => 1,
            'rightanswerclosed' => 1,
            'overallfeedbackclosed' => 1,
        ];
        $quizmodule = $this->add_module($course, $sectionnumber, 'quiz', $reviewdefaults + [
            'name' => $quizdefinition['name'],
            'intro' => $quizdefinition['intro'],
            'introformat' => FORMAT_HTML,
            'timeopen' => 0,
            'timeclose' => 0,
            'timelimit' => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts' => 0,
            'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2,
            'questiondecimalpoints' => -1,
            'questionsperpage' => 1,
            'shuffleanswers' => 1,
            'sumgrades' => 0,
            'grade' => 100,
            'overduehandling' => 'autosubmit',
            'graceperiod' => DAYSECS,
            'quizpassword' => '',
            'subnet' => '',
            'browsersecurity' => '',
            'delay1' => 0,
            'delay2' => 0,
            'showuserpicture' => 0,
            'showblocks' => 0,
            'navmethod' => QUIZ_NAVMETHOD_FREE,
        ]);
        $quiz = $DB->get_record('quiz', ['id' => $quizmodule->instance], '*', MUST_EXIST);
        $context = \context_module::instance($quizmodule->coursemodule);
        $category = question_get_default_category($context->id, true);
        foreach ($quizdefinition['questions'] as $questiondefinition) {
            $question = $this->save_question($category, $questiondefinition);
            quiz_add_quiz_question($question->id, $quiz, 0, 1.0);
        }
    }

    private function save_question(\stdClass $category, array $definition): \stdClass {
        $question = (object)['qtype' => $definition['type']];
        $base = [
            'category' => "{$category->id},{$category->contextid}",
            'name' => $definition['name'],
            'questiontext' => ['text' => $definition['questiontext'], 'format' => FORMAT_HTML, 'itemid' => 0],
            'generalfeedback' => [
                'text' => $definition['generalfeedback'],
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'status' => question_version_status::QUESTION_STATUS_READY,
            'showstandardinstruction' => 1,
            'hint' => [],
            'hintclearwrong' => [],
            'hintshownumcorrect' => [],
        ];
        if ($definition['type'] === 'truefalse') {
            $form = (object)($base + [
                'correctanswer' => $definition['correct'] ? 1 : 0,
                'feedbacktrue' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                'feedbackfalse' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                'penalty' => 1,
            ]);
        } else {
            $answers = [];
            $fractions = [];
            $feedback = [];
            foreach ($definition['answers'] as $answer) {
                $answers[] = ['text' => $answer['text'], 'format' => FORMAT_PLAIN, 'itemid' => 0];
                $fractions[] = $answer['correct'] ? 1.0 : 0.0;
                $feedback[] = ['text' => $answer['feedback'], 'format' => FORMAT_HTML, 'itemid' => 0];
            }
            $form = (object)($base + [
                'single' => 1,
                'shuffleanswers' => 1,
                'answernumbering' => 'abc',
                'answer' => $answers,
                'fraction' => $fractions,
                'feedback' => $feedback,
                'correctfeedback' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                'partiallycorrectfeedback' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                'incorrectfeedback' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                'shownumcorrect' => 1,
            ]);
        }
        return \question_bank::get_qtype($definition['type'])->save_question($question, $form);
    }

    private function add_module(
        \stdClass $course,
        int $sectionnumber,
        string $modulename,
        array $specific
    ): \stdClass {
        global $DB;

        $module = $DB->get_record('modules', ['name' => $modulename], '*', MUST_EXIST);
        $common = [
            'modulename' => $modulename,
            'module' => $module->id,
            'section' => $sectionnumber,
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'cmidnumber' => '',
            'groupmode' => 0,
            'groupingid' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => COMPLETION_VIEW_REQUIRED,
            'completionexpected' => 0,
        ];
        return add_moduleinfo((object)($specific + $common), $course);
    }
}
