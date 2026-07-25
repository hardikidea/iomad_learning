# Pipeline Onboarding

Required GitHub configuration:

- `AWS_ACCOUNT_ID`
- `AWS_REGION`
- `GHCR_IMAGE_NAME`
- OIDC trust for dev, stage, and prod environments
- approval rules for stage and production
- backup vault and backup role variables for backup/restore workflows

The delivery workflow builds images from `IOMAD_COMMIT`, not tags or moving branches. The upgrade workflow accepts a reviewed commit SHA and requires an exact confirmation phrase.
