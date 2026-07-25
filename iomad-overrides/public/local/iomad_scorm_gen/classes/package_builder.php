<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_scorm_gen;

/**
 * Build a self-contained SCORM 1.2 package.
 *
 * @package local_iomad_scorm_gen
 */
final class package_builder {
    /**
     * Build a package atomically.
     *
     * @param array $input Definition.
     * @param string $target Target ZIP.
     * @return array Manifest metadata.
     */
    public function build(array $input, string $target): array {
        $definition = (new package_definition())->validate($input);
        $directory = dirname($target);
        if (
            strtolower(pathinfo($target, PATHINFO_EXTENSION)) !== 'zip'
            || !is_dir($directory)
            || !is_writable($directory)
        ) {
            throw new \invalid_parameter_exception('The SCORM target must be a writable ZIP path.');
        }
        $temporary = $target . '.tmp-' . bin2hex(random_bytes(4));
        $zip = new \ZipArchive();
        if ($zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new \moodle_exception('cannotcreatezip', 'error');
        }
        try {
            $zip->addFromString('imsmanifest.xml', $this->manifest($definition));
            $zip->addFromString('index.html', $this->html($definition));
            $zip->addFromString('assets/runtime.js', $this->runtime());
            $zip->addFromString('package.json', json_encode([
                'schema_version' => 1,
                'idnumber' => $definition['idnumber'],
                'section_ids' => array_column($definition['sections'], 'id'),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            if (!$zip->close()) {
                throw new \moodle_exception('cannotcreatezip', 'error');
            }
            if (!rename($temporary, $target)) {
                throw new \moodle_exception('cannotcreatezip', 'error');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($temporary);
            throw $exception;
        }
        return [
            'idnumber' => $definition['idnumber'],
            'sections' => count($definition['sections']),
            'sha256' => hash_file('sha256', $target),
        ];
    }

    /**
     * SCORM 1.2 manifest.
     *
     * @param array $definition Definition.
     * @return string
     */
    private function manifest(array $definition): string {
        $id = htmlspecialchars($definition['idnumber'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($definition['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="{$id}" version="1.0"
 xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
 xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
 http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd
 http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">
  <organizations default="ORG-1">
    <organization identifier="ORG-1">
      <title>{$title}</title>
      <item identifier="ITEM-1" identifierref="RESOURCE-1">
        <title>{$title}</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RESOURCE-1" type="webcontent" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
      <file href="assets/runtime.js"/>
      <file href="package.json"/>
    </resource>
  </resources>
</manifest>
XML;
    }

    /**
     * Accessible course content.
     *
     * @param array $definition Definition.
     * @return string
     */
    private function html(array $definition): string {
        $title = htmlspecialchars($definition['title'], ENT_QUOTES, 'UTF-8');
        $sections = '';
        foreach ($definition['sections'] as $section) {
            $id = htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8');
            $sectiontitle = htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8');
            $body = nl2br(htmlspecialchars($section['body'], ENT_QUOTES, 'UTF-8'));
            $sections .= <<<HTML
<section id="{$id}" tabindex="-1">
  <h2>{$sectiontitle}</h2>
  <p>{$body}</p>
  <button type="button" data-checkpoint="{$id}">Mark section complete</button>
</section>
HTML;
        }
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <style>
    body{font-family:system-ui,sans-serif;max-width:56rem;margin:0 auto;padding:1.5rem;line-height:1.6}
    section{border-bottom:1px solid #d0d5dd;padding:1rem 0 2rem}
    button{min-height:44px;padding:.65rem 1rem}
    button:focus-visible{outline:3px solid #005fcc;outline-offset:3px}
  </style>
</head>
<body>
  <main><h1>{$title}</h1>{$sections}</main>
  <script src="assets/runtime.js"></script>
</body>
</html>
HTML;
    }

    /**
     * Runtime with core SCORM commits and an offline checkpoint queue.
     *
     * @return string
     */
    private function runtime(): string {
        return <<<'JS'
(function () {
  'use strict';
  var storageKey = 'iomad-scorm-checkpoints:' + location.pathname;
  var api = null;

  function findApi(win) {
    var attempts = 0;
    while (win && attempts < 10) {
      if (win.API) {
        return win.API;
      }
      if (win.parent === win) {
        break;
      }
      win = win.parent;
      attempts += 1;
    }
    return null;
  }

  function readQueue() {
    try {
      return JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch (error) {
      return [];
    }
  }

  function writeQueue(queue) {
    try {
      localStorage.setItem(storageKey, JSON.stringify(queue.slice(-100)));
    } catch (error) {
      // Core SCORM tracking still works when local storage is unavailable.
    }
  }

  function commit(checkpoint) {
    api = api || findApi(window);
    if (!api) {
      return false;
    }
    api.LMSInitialize('');
    var set = api.LMSSetValue('cmi.core.lesson_location', checkpoint);
    var saved = api.LMSCommit('');
    return String(set).toLowerCase() === 'true' && String(saved).toLowerCase() === 'true';
  }

  function flush() {
    var queue = readQueue();
    var remaining = [];
    queue.forEach(function (checkpoint) {
      if (!commit(checkpoint)) {
        remaining.push(checkpoint);
      }
    });
    writeQueue(remaining);
  }

  function checkpoint(id) {
    if (!/^[A-Za-z][A-Za-z0-9_-]{1,39}$/.test(id)) {
      return;
    }
    var queue = readQueue();
    if (queue.indexOf(id) === -1) {
      queue.push(id);
      writeQueue(queue);
    }
    flush();
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-checkpoint]');
    if (button) {
      checkpoint(button.getAttribute('data-checkpoint'));
      button.disabled = true;
      button.textContent = 'Completed';
    }
  });
  window.addEventListener('online', flush);
  window.addEventListener('beforeunload', function () {
    flush();
    if (api) {
      api.LMSFinish('');
    }
  });
  window.IomadScorm = {checkpoint: checkpoint, flush: flush};
  flush();
}());
JS;
    }
}
