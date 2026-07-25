# Storage Capacity

## Trigger

`storage` health fails or EFS/dataroot free space approaches the configured
threshold.

## Response

1. Stop imports, course restores, and large file uploads.
2. Inspect EFS capacity, throughput, inode pressure, mount state, and failed
   antivirus or file-processing tasks.
3. Never delete dataroot content manually. Use Moodle APIs and approved
   retention processes.
4. Create and verify a matching recovery set before corrective migration.

## Verify

Run `make health`, upload and retrieve a sanitized test file, and confirm a
tenant from each configured hostname can access existing course content.

