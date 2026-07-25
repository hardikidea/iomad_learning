# Interactive Content

## H5P

`local_iomad_h5p_bridge` observes
`\mod_h5pactivity\event\statement_received`. Moodle has already authenticated
the actor, checked activity capability, validated the statement, stored the
attempt, and updated grades. The bridge accepts only successful `answered` and
`completed` verbs and records rewards through `local_global_events`.

It does not expose a second xAPI ingestion endpoint.

## SCORM

`local_iomad_scorm_gen` has two boundaries:

1. a SCORM 1.2 package generator;
2. an observer for core `status_submitted` events.

Generated packages are self-contained ZIP files with:

- `imsmanifest.xml`;
- an accessible `index.html`;
- a small `assets/runtime.js`;
- a non-personal package manifest.

At each section checkpoint, the run-time calls the normal SCORM 1.2 API:

```text
LMSSetValue("cmi.core.lesson_location", checkpoint)
LMSCommit("")
```

Failed commits remain in a bounded local-storage queue and retry when the
browser returns online or before unload. The package never calls a custom XP
endpoint, preventing learners from choosing arbitrary point values.

Build a package from sanitized JSON:

```bash
make scorm-build INPUT=/var/www/institution-packs/demo-scorm.json \
  OUTPUT=/tmp/demo-scorm.zip
```

## Acceptance

- Validate the ZIP manifest and checksum.
- Import through Moodle's SCORM activity workflow.
- Complete checkpoints, close the window, reopen, and verify lesson location.
- Submit completed and passed statuses twice and verify one ledger record per
  stable status identity.
- Verify the same user/course event cannot be attributed to another company.
- Test keyboard, mobile, RTL, offline/reconnect, and reduced-motion behavior.
