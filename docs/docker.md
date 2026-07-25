# Docker

Local services:

| Service | Purpose |
|---|---|
| `iomad` | PHP-FPM + Nginx web runtime serving `/var/www/iomad/public` |
| `cron` | IOMAD cron loop |
| `db` | PostgreSQL |
| `redis` | cache/session backend |
| `mailpit` | local SMTP catcher |

Volumes:

- `./iomaddata:/var/www/iomaddata`
- `./institution-packs:/var/www/institution-packs:ro`

Local and production builds set `INCLUDE_IOMAD_SOURCE=true`; the Dockerfile
clones the official repository, checks out `IOMAD_COMMIT` detached, applies
`iomad-overrides/` and the reviewed exclusion manifest, installs Composer
dependencies, and labels the image with the source commit. The ignored host
`iomad/` checkout remains a clean inspection and upgrade input instead of a
runtime bind mount, so local execution matches the immutable release image.
