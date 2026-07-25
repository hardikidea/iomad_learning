# Institution Import Failure

## Trigger

Institution pack validation, plan, dry run, apply, or resume fails.

## Response

1. Preserve the immutable manifest, checksums, report, and stable error code.
2. Do not edit an applied pack in place. Correct canonical CSV/YAML and create a
   new manifest.
3. Resolve schema, foreign-key, duplicate, count, or tenant-scope failures.
4. Resume only through `local_institutionpack`; never repair tables directly.

## Verify

```bash
make pack-validate PACK=institution-packs/school/sample
make pack-plan PACK=institution-packs/school/sample
```

Apply twice in a disposable environment and verify the second run is
idempotent.

