# Setup

## Local Install

```bash
cp .env.example .env
make bootstrap
make build
make up
make install
make demo-data
```

IOMAD runs at `http://localhost:18080`; MailPit runs at `http://localhost:18025`.

`scripts/bootstrap-iomad.sh` clones `IOMAD_REF`, fetches the pinned `IOMAD_COMMIT`, checks it out detached, records `.iomad-source.env`, then syncs `iomad-overrides/`.

## Paths

- Web document root: `/var/www/iomad/public`
- CLI working directory: `/var/www/iomad`
- Dataroot: `/var/www/iomaddata`
- Local packs in container: `/var/www/institution-packs`

Do not edit `iomad/` directly for project changes.
