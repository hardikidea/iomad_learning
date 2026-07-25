# Updating IOMAD

Use [upgrade.md](upgrade.md) for the current commit-pinned process.

Short form:

```bash
./scripts/update-iomad.sh <reviewed-commit-sha>
```

The script never runs `git pull`, never deploys a floating branch, and never attempts database downgrade.
