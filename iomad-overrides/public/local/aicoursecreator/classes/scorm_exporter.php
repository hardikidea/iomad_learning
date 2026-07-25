<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates a portable SCORM 1.2 package from reviewed static content.
 */
final class scorm_exporter {
    public function export_to_path(array $definition, string $pathname): string {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        $definition = (new course_schema_validator())->validate($definition);
        $resources = [];
        $links = [];
        foreach ($definition['sections'] as $sectionindex => $section) {
            foreach ($section['items'] as $itemindex => $item) {
                $filename = sprintf('content/section-%02d-item-%02d.html', $sectionindex + 1, $itemindex + 1);
                $body = $item['content'];
                if ($item['type'] === 'url') {
                    $body .= '<p><a rel="noopener noreferrer" href="' . s($item['url']) . '">'
                        . s($item['url']) . '</a></p>';
                } else if ($item['type'] === 'h5p_blueprint') {
                    $body = '<p>This H5P blueprint requires a reviewed H5P package in the LMS.</p>' . $body;
                }
                $resources[$filename] = [$this->html_document($item['name'], $body)];
                $links[] = [
                    'id' => $item['id'],
                    'title' => $item['name'],
                    'href' => $filename,
                ];
            }
            foreach ($section['quizzes'] as $quizindex => $quiz) {
                $filename = sprintf('content/section-%02d-quiz-%02d.html', $sectionindex + 1, $quizindex + 1);
                $body = '<p>' . s('This reviewed question blueprint is published as a Moodle quiz in the LMS.') . '</p>';
                $body .= $quiz['intro'];
                $body .= '<ol>';
                foreach ($quiz['questions'] as $question) {
                    $body .= '<li>' . $question['questiontext'] . '</li>';
                }
                $body .= '</ol>';
                $resources[$filename] = [$this->html_document($quiz['name'], $body)];
                $links[] = [
                    'id' => $quiz['id'],
                    'title' => $quiz['name'],
                    'href' => $filename,
                ];
            }
        }
        if ($links === []) {
            throw new \invalid_parameter_exception('SCORM export requires at least one content item.');
        }
        $resources['index.html'] = [$this->index_document($definition['course']['fullname'], $links)];
        $resources['scorm-api.js'] = [$this->runtime()];
        $resources['imsmanifest.xml'] = [$this->manifest($definition['course']['fullname'], $links)];
        $packer = get_file_packer('application/zip');
        if (!$packer->archive_to_pathname($resources, $pathname, false)) {
            throw new \moodle_exception('errorcreatingfile', 'error', '', $pathname);
        }
        return $pathname;
    }

    private function html_document(string $title, string $body): string {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . s($title) . '</title><script src="../scorm-api.js"></script>'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:70rem;margin:auto;padding:2rem}'
            . 'img,video{max-width:100%;height:auto}a{color:#075985}</style></head>'
            . '<body><main><h1>' . s($title) . '</h1>' . $body
            . '</main><script>window.IOMADSCORM.complete();</script></body></html>';
    }

    private function index_document(string $title, array $links): string {
        $items = '';
        foreach ($links as $link) {
            $items .= '<li><a href="' . s($link['href']) . '">' . s($link['title']) . '</a></li>';
        }
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . s($title) . '</title></head><body><main><h1>' . s($title)
            . '</h1><ol>' . $items . '</ol></main></body></html>';
    }

    private function runtime(): string {
        return <<<'JS'
(function () {
    'use strict';
    function api() {
        var current = window;
        for (var depth = 0; depth < 8 && current; depth++) {
            if (current.API) {
                return current.API;
            }
            if (!current.parent || current.parent === current) {
                break;
            }
            current = current.parent;
        }
        return null;
    }
    window.IOMADSCORM = {
        complete: function () {
            var runtime = api();
            if (!runtime) {
                return;
            }
            runtime.LMSInitialize('');
            runtime.LMSSetValue('cmi.core.lesson_status', 'completed');
            runtime.LMSCommit('');
        }
    };
}());
JS;
    }

    private function manifest(string $title, array $links): string {
        $items = '';
        $resources = '';
        foreach ($links as $index => $link) {
            $number = $index + 1;
            $items .= '<item identifier="ITEM-' . $number . '" identifierref="RES-' . $number . '">'
                . '<title>' . htmlspecialchars($link['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</title></item>';
            $resources .= '<resource identifier="RES-' . $number . '" type="webcontent" '
                . 'adlcp:scormtype="sco" href="' . htmlspecialchars($link['href'], ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '"><file href="' . htmlspecialchars($link['href'], ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '"/><file href="scorm-api.js"/></resource>';
        }
        $escapedtitle = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<manifest identifier="IOMAD-AI-COURSE" version="1.0" '
            . 'xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" '
            . 'xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>'
            . '<organizations default="ORG-1"><organization identifier="ORG-1"><title>'
            . $escapedtitle . '</title>' . $items . '</organization></organizations>'
            . '<resources>' . $resources . '</resources></manifest>';
    }
}
