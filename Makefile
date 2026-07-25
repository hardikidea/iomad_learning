-include .env
-include versions.env
export
IOMAD_THEME ?= iomad_learning
IOMAD_URL ?= http://localhost:18080
KEEP_BACKUPS ?= 3
DOCKER ?= $(shell command -v docker 2>/dev/null || printf /usr/local/bin/docker)

.PHONY: bootstrap sync-overrides capture-override build up start down stop restart logs shell install configure-mailpit demo-generate demo-check demo-data demo-clear demo-reseed demo-verify ecosystem-verify product-demo-data theme-install scorm-build cron test phpunit clean-install-test upgrade-test backup backup-verify backup-prune backup-restore-test restore reset-local update update-restore-on-fail status health pack-validate pack-plan pack-apply pack-workbooks reporting-validate operational-baseline observability-up observability-down observability-validate local-cloud-provision local-cloud-init local-cloud-install local-cloud-demo-data local-cloud-down local-cloud-status local-cloud-logs local-cloud-cron local-cloud-validate

bootstrap:
	./scripts/bootstrap-iomad.sh

sync-overrides:
	./scripts/sync-iomad-overrides.sh

capture-override:
	@test -n "$(RELPATH)" || (echo "Usage: make capture-override RELPATH=public/mod/pluginname" && exit 1)
	./scripts/capture-iomad-override.sh "$(RELPATH)"

build:
	docker compose build

up:
	docker compose up -d

start: up

down:
	docker compose down

stop: down

restart:
	docker compose restart iomad cron

logs:
	docker compose logs -f iomad

shell:
	docker compose exec iomad bash

install:
	./scripts/install-site.sh

configure-mailpit:
	./scripts/configure-mailpit.sh

demo-generate:
	./scripts/generate-demo-packs.py

demo-check:
	./scripts/generate-demo-packs.py --check

demo-data:
	./scripts/import-demo-packs.sh

demo-clear:
	./scripts/clear-demo-environment.sh $(RESET_ARGS)

demo-reseed:
	./scripts/reseed-demo-environment.sh $(RESEED_ARGS)

demo-verify:
	./scripts/verify-demo-environment.sh

ecosystem-verify:
	docker compose exec -T iomad php public/local/tenantmaster/cli/verify_mdm_ecosystem.php $(VERIFY_ARGS)

product-demo-data:
	./scripts/seed-product-demos.sh

theme-install:
	./scripts/sync-iomad-overrides.sh
	docker compose build iomad cron
	docker compose up -d --wait --force-recreate iomad
	docker compose exec iomad php admin/cli/upgrade.php --non-interactive
	docker compose exec iomad php admin/cli/cfg.php --name=theme --set=$(IOMAD_THEME)
	docker compose exec iomad php admin/cli/build_theme_css.php --themes=$(IOMAD_THEME) --direction=ltr --verbose
	docker compose exec iomad php admin/cli/purge_caches.php
	docker compose up -d --force-recreate cron

scorm-build:
	@test -n "$(INPUT)" && test -n "$(OUTPUT)" || (echo "Usage: make scorm-build INPUT=definition.json OUTPUT=package.zip" && exit 1)
	docker compose exec -T iomad php public/local/iomad_scorm_gen/cli/build.php --input="$(INPUT)" --output="$(OUTPUT)"

cron:
	docker compose exec iomad php admin/cli/cron.php --keep-alive=0

test:
	./scripts/test-repository.sh

phpunit:
	./scripts/test-phpunit.sh

clean-install-test:
	./scripts/test-clean-install.sh --yes

upgrade-test:
	./scripts/test-upgrade-from-previous.sh --yes

backup:
	./scripts/backup.sh

backup-verify:
	@test -n "$(BACKUP_DIR)" || (echo "Usage: make backup-verify BACKUP_DIR=backups/YYYYMMDD-HHMMSS" && exit 1)
	./scripts/verify-backup.sh "$(BACKUP_DIR)"

backup-prune:
	./scripts/prune-backups.sh --keep=$(KEEP_BACKUPS) $(APPLY)

backup-restore-test:
	./scripts/test-backup-restore.sh --yes

restore:
	@test -n "$(BACKUP_DIR)" || (echo "Usage: make restore BACKUP_DIR=backups/YYYYMMDD-HHMMSS" && exit 1)
	./scripts/restore-backup.sh "$(BACKUP_DIR)" --yes

reset-local:
	./scripts/reset-local-iomad.sh $(RESET_ARGS)

update:
	./scripts/update-iomad.sh $(IOMAD_COMMIT)

update-restore-on-fail:
	./scripts/update-iomad.sh --restore-on-fail $(IOMAD_COMMIT)

status:
	docker compose ps

health:
	curl --fail --silent --show-error "$(IOMAD_URL)/health/ready"

pack-validate:
	./scripts/pack-validate.sh $(PACK)

pack-plan:
	./scripts/pack-plan.sh $(PACK)

pack-apply:
	./scripts/pack-apply.sh $(PACK)

pack-workbooks:
	./scripts/generate-pack-workbooks.sh $(PACK)

reporting-validate:
	./scripts/validate-commercial-reporting.sh $(REPORTING_MANIFEST)

operational-baseline:
	./scripts/validate-iomad-operational-baseline.sh

observability-up:
	./scripts/init-observability.sh

observability-down:
	docker compose -f docker-compose.yml -f docker-compose.observability.yml --profile observability down

observability-validate:
	./scripts/validate-observability.sh

local-cloud-provision:
	./init-local-cloud.sh --provision-only

local-cloud-init:
	./init-local-cloud.sh

local-cloud-install:
	./init-local-cloud.sh --install

local-cloud-demo-data:
	IOMAD_COMPOSE_FILE=docker-compose.local.yml \
	IOMAD_COMPOSE_PROJECT_NAME=iomad_learning_floci \
	IOMAD_COMPOSE_SERVICE=iomad-php \
	./scripts/import-demo-packs.sh

local-cloud-down:
	$(DOCKER) compose --project-name iomad_learning_floci -f docker-compose.local.yml down

local-cloud-status:
	$(DOCKER) compose --project-name iomad_learning_floci -f docker-compose.local.yml ps

local-cloud-logs:
	$(DOCKER) compose --project-name iomad_learning_floci -f docker-compose.local.yml logs -f

local-cloud-cron:
	$(DOCKER) compose --project-name iomad_learning_floci -f docker-compose.local.yml exec -T iomad-php php admin/cli/cron.php --keep-alive=0

local-cloud-validate:
	./scripts/validate-local-cloud.sh
