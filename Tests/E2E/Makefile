E2E_DIR = $(CURDIR)
NEOS8_COMPOSE = $(E2E_DIR)/system_under_test/neos8/docker-compose.yaml
NEOS9_COMPOSE = $(E2E_DIR)/system_under_test/neos9/docker-compose.yaml

.DEFAULT_GOAL := help

.SILENT:
.PHONY: help setup setup-sut setup-test generate-bdd-files test test-neos8 test-neos9 \
	test-neos8-defaults test-neos8-enforce-all test-neos8-enforce-role test-neos8-enforce-provider test-neos8-passwordless test-neos8-enforce-all-passwordless \
	test-neos9-defaults test-neos9-enforce-all test-neos9-enforce-role test-neos9-enforce-provider test-neos9-passwordless test-neos9-enforce-all-passwordless \
	start-sut-neos8 start-sut-neos9 log-sut-neos8 log-sut-neos9 enter-sut-neos8 enter-sut-neos9 sut-down

# COLORS
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
RESET  := $(shell tput -Txterm sgr0)

## Show this help
help:
	echo "${YELLOW}Available targets:${RESET}"
	awk '/^## / {sub(/^## /, ""); doc = $$0; next} \
		/^[a-zA-Z0-9_-]+:/ {if (doc != "") {target = $$1; sub(/:.*/, "", target); printf "  ${GREEN}%-30s${RESET} %s\n", target, doc}} \
		{doc = ""}' $(MAKEFILE_LIST)

## initial setup
setup: setup-sut setup-test

## Build the neos8 and neos9 SUT docker images
setup-sut:
	docker compose -f $(NEOS8_COMPOSE) build --pull
	docker compose -f $(NEOS9_COMPOSE) build --pull

## Install node dependencies, playwright and generate BDD files
setup-test:
	echo "${GREEN}Installing test setup.${RESET}"
	cd $(E2E_DIR) && \
	REQUIRED_NODE="$$(cat .nvmrc)"; \
	CURRENT_NODE="$$(node --version 2>/dev/null)"; \
	REQUIRED_MINOR="$$(echo "$$REQUIRED_NODE" | cut -d. -f1,2)"; \
	CURRENT_MINOR="$$(echo "$$CURRENT_NODE" | cut -d. -f1,2)"; \
	if [ "$$CURRENT_MINOR" != "$$REQUIRED_MINOR" ]; then \
		echo "${YELLOW}Wrong Node.js version.${RESET}"; \
		echo "  required: $$REQUIRED_MINOR.x (from .nvmrc: $$REQUIRED_NODE)"; \
		echo "  current:  $${CURRENT_NODE:-not found}"; \
		echo "Please install/activate Node.js $$REQUIRED_MINOR.x (e.g. 'nvm install', 'mise install', or 'fnm use') and re-run 'make setup-test'."; \
		exit 1; \
	fi; \
	npm install && npx playwright install --with-deps chromium && \
	echo "" && echo "${GREEN}generate BDD files from feature files${RESET}" && npm run generate-tests

## generate BDD files from feature files
generate-bdd-files:
	echo "${GREEN}generate BDD files from feature files${RESET}"; \
	cd $(E2E_DIR) && npm run generate-tests

## Run all E2E tests
test: test-neos8 test-neos9

## Run all neos8 E2E tests
test-neos8: test-neos8-defaults test-neos8-enforce-all test-neos8-enforce-role test-neos8-enforce-provider test-neos8-passwordless test-neos8-enforce-all-passwordless

## Run all neos9 E2E tests
test-neos9: test-neos9-defaults test-neos9-enforce-all test-neos9-enforce-role test-neos9-enforce-provider test-neos9-passwordless test-neos9-enforce-all-passwordless

## Run neos8 E2E tests with default 2FA settings
test-neos8-defaults:
	cd $(E2E_DIR) && npm run test:neos8:defaults

## Run neos8 E2E tests with 2FA enforced for all users
test-neos8-enforce-all:
	cd $(E2E_DIR) && npm run test:neos8:enforce-all

## Run neos8 E2E tests with 2FA enforced for a role
test-neos8-enforce-role:
	cd $(E2E_DIR) && npm run test:neos8:enforce-role

## Run neos8 E2E tests with 2FA enforced for a provider
test-neos8-enforce-provider:
	cd $(E2E_DIR) && npm run test:neos8:enforce-provider

## Run neos9 E2E tests with default 2FA settings
test-neos9-defaults:
	cd $(E2E_DIR) && npm run test:neos9:defaults

## Run neos9 E2E tests with 2FA enforced for all users
test-neos9-enforce-all:
	cd $(E2E_DIR) && npm run test:neos9:enforce-all

## Run neos9 E2E tests with 2FA enforced for a role
test-neos9-enforce-role:
	cd $(E2E_DIR) && npm run test:neos9:enforce-role

## Run neos9 E2E tests with 2FA enforced for a provider
test-neos9-enforce-provider:
	cd $(E2E_DIR) && npm run test:neos9:enforce-provider

## Run neos8 E2E tests for passwordless passkey login
test-neos8-passwordless:
	cd $(E2E_DIR) && npm run test:neos8:passwordless

## Run neos8 E2E tests with 2FA enforced for all users AND passwordless enabled
test-neos8-enforce-all-passwordless:
	cd $(E2E_DIR) && npm run test:neos8:enforce-all-passwordless

## Run neos9 E2E tests for passwordless passkey login
test-neos9-passwordless:
	cd $(E2E_DIR) && npm run test:neos9:passwordless

## Run neos9 E2E tests with 2FA enforced for all users AND passwordless enabled
test-neos9-enforce-all-passwordless:
	cd $(E2E_DIR) && npm run test:neos9:enforce-all-passwordless

## Start the neos8 SUT containers
start-sut-neos8:
	docker compose -f $(NEOS8_COMPOSE) up -d --build

## Start the neos9 SUT containers
start-sut-neos9:
	docker compose -f $(NEOS9_COMPOSE) up -d --build

## Follow logs of the neos8 SUT containers
log-sut-neos8:
	docker compose -f $(NEOS8_COMPOSE) logs -f

## Follow logs of the neos9 SUT containers
log-sut-neos9:
	docker compose -f $(NEOS9_COMPOSE) logs -f

## Open a bash shell in the neos8 SUT container
enter-sut-neos8:
	docker compose -f $(NEOS8_COMPOSE) exec neos bash

## Open a bash shell in the neos9 SUT container
enter-sut-neos9:
	docker compose -f $(NEOS9_COMPOSE) exec neos bash

## Stop the neos8 SUT containers
stop-sut-neos8:
	docker compose -f $(NEOS8_COMPOSE) down

## Stop the neos9 SUT containers
stop-sut-neos9:
	docker compose -f $(NEOS9_COMPOSE) down

## Tear down all docker compose environments and remove volumes
sut-prune:
	echo "${YELLOW}Shutting down all SUTs and removing their volumes.${RESET}"
	docker compose -f $(NEOS8_COMPOSE) down -v
	docker compose -f $(NEOS9_COMPOSE) down -v
