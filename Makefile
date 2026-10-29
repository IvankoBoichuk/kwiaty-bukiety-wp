# Database and uploads sync between the local devcontainer and the server.
#
#   make pull ENV=dev            DB + uploads from the server
#   make db-pull ENV=prod        only the DB
#   make uploads-push ENV=dev    only uploads, to the server
#
# ENV is dev (default) or prod. Prod is still a plain WordPress install, not
# Bedrock -- different prefix, wp-content/ instead of app/, its own theme and
# plugins -- so it can be pulled from but never pushed to.
#
# Every overwrite keeps a dump of what it replaced: local ones in .backups/,
# server ones in ~/backups/kwiaty-bukiety/.

SHELL := /bin/bash
.SHELLFLAGS := -eo pipefail -c
.DEFAULT_GOAL := help

ENV ?= dev
SSH ?= kb

LOCAL_URL ?= http://localhost:8000
LOCAL_PREFIX ?= wp_
LOCAL_UPLOADS := web/app/uploads
# VS Code names the devcontainer's compose project <folder>_devcontainer.
COMPOSE ?= docker compose -p kwiaty-bukiety-wp_devcontainer -f .devcontainer/docker-compose.yml
WP := $(COMPOSE) exec -T -e XDEBUG_MODE=off wordpress wp --allow-root

ifeq ($(ENV),dev)
  REMOTE_PATH := domains/dev.kwiaty-bukiety.com.pl/public_html
  REMOTE_URL := https://dev.kwiaty-bukiety.com.pl
  REMOTE_UPLOADS := $(REMOTE_PATH)/web/app/uploads
  REMOTE_UPLOADS_URL := $(REMOTE_URL)/app/uploads
  REMOTE_PREFIX := wp_
  PUSHABLE := 1
else ifeq ($(ENV),prod)
  REMOTE_PATH := domains/kwiaty-bukiety.com.pl/public_html
  REMOTE_URL := https://kwiaty-bukiety.com.pl
  REMOTE_UPLOADS := $(REMOTE_PATH)/wp-content/uploads
  REMOTE_UPLOADS_URL := $(REMOTE_URL)/wp-content/uploads
  REMOTE_PREFIX := upjig_
  PUSHABLE :=
else
  $(error ENV must be dev or prod, got "$(ENV)")
endif

STAMP := $(shell date +%Y%m%d-%H%M%S)
BACKUPS := .backups
REMOTE_BACKUPS := backups/kwiaty-bukiety
RSYNC := rsync -az --info=progress2 --exclude=.gitkeep --exclude=wc-logs/ --exclude=cache/

# The server runs MariaDB 11, the devcontainer MySQL 8: drop MariaDB's sandbox
# line and its uca1400 collations. Then rename the table prefix -- table names
# sit in backticks, prefixed option names and meta keys in quotes.
TO_LOCAL := sed -e '1{/enable the sandbox mode/d;}' \
	-e 's/\(utf8mb[34]\)_uca1400_ai_ci/\1_unicode_ci/g' \
	-e 's/`$(REMOTE_PREFIX)/`$(LOCAL_PREFIX)/g' -e "s/'$(REMOTE_PREFIX)/'$(LOCAL_PREFIX)/g"

.PHONY: help pull push db-pull db-push uploads-pull uploads-push confirm-push

help:
	@sed -n '2,13p' $(MAKEFILE_LIST) | sed 's/^# \{0,1\}//'

pull: db-pull uploads-pull

push: db-push uploads-push

db-pull:
	@mkdir -p $(BACKUPS)
	@echo "→ Backing up the local DB"
	@$(WP) db export - | gzip > $(BACKUPS)/local-$(STAMP).sql.gz
	@echo "→ Dumping $(ENV)"
	@ssh $(SSH) bash -s -- export $(REMOTE_PATH) $(REMOTE_BACKUPS)/pull-$(ENV)-$(STAMP).sql.gz < bin/remote-db.sh
	@rsync -az --remove-source-files $(SSH):$(REMOTE_BACKUPS)/pull-$(ENV)-$(STAMP).sql.gz $(BACKUPS)/$(ENV)-$(STAMP).sql.gz
	@echo "→ Importing into the local DB"
	@$(WP) db reset --yes
	@gunzip -c $(BACKUPS)/$(ENV)-$(STAMP).sql.gz | $(TO_LOCAL) | $(WP) db import -
	@$(WP) search-replace '$(REMOTE_UPLOADS_URL)' '$(LOCAL_URL)/app/uploads' --all-tables-with-prefix --report-changed-only
	@$(WP) search-replace '$(REMOTE_URL)' '$(LOCAL_URL)' --all-tables-with-prefix --report-changed-only
	@$(WP) cache flush
	@echo "✓ $(ENV) DB is now local ($(LOCAL_URL))"

db-push: confirm-push
	@mkdir -p $(BACKUPS)
	@echo "→ Backing up $(ENV) to ~/$(REMOTE_BACKUPS)/"
	@ssh $(SSH) bash -s -- export $(REMOTE_PATH) $(REMOTE_BACKUPS)/$(ENV)-$(STAMP).sql.gz < bin/remote-db.sh
	@echo "→ Dumping the local DB"
	@$(WP) db export - --tables="$$($(WP) db tables --all-tables-with-prefix --format=csv)" | gzip > $(BACKUPS)/local-$(STAMP).sql.gz
	@rsync -az $(BACKUPS)/local-$(STAMP).sql.gz $(SSH):$(REMOTE_BACKUPS)/push-$(STAMP).sql.gz
	@echo "→ Importing into $(ENV)"
	@ssh $(SSH) bash -s -- import $(REMOTE_PATH) $(REMOTE_BACKUPS)/push-$(STAMP).sql.gz < bin/remote-db.sh
	@ssh $(SSH) 'rm $(REMOTE_BACKUPS)/push-$(STAMP).sql.gz && cd $(REMOTE_PATH) \
		&& wp search-replace "$(LOCAL_URL)" "$(REMOTE_URL)" --all-tables-with-prefix --report-changed-only \
		&& wp cache flush \
		&& (wp litespeed-purge all || true)'
	@echo "✓ Local DB is now on $(ENV) ($(REMOTE_URL))"

uploads-pull:
	@echo "→ Pulling uploads from $(ENV)"
	@$(RSYNC) $(SSH):$(REMOTE_UPLOADS)/ $(LOCAL_UPLOADS)/

uploads-push: confirm-push
	@echo "→ Pushing uploads to $(ENV)"
	@$(RSYNC) $(LOCAL_UPLOADS)/ $(SSH):$(REMOTE_UPLOADS)/

# A target runs once per make call, so `make push` asks only once.
confirm-push:
	@if [ -z "$(PUSHABLE)" ]; then echo "✗ Pushing to $(ENV) is disabled: it is not on Bedrock yet."; exit 1; fi
	@read -r -p "Overwrite $(ENV) ($(REMOTE_URL))? Type '$(ENV)' to confirm: " answer && [ "$$answer" = "$(ENV)" ] || { echo "✗ Cancelled"; exit 1; }
