NEOS8_COMPOSE = $(CURDIR)/Tests/sytem_under_test/neos8/docker-compose.yaml
NEOS9_COMPOSE = $(CURDIR)/Tests/sytem_under_test/neos9/docker-compose.yaml
E2E_DIR = $(CURDIR)/Tests/E2E

.PHONY: setup-test generate-bdd-files test test-neos8 test-neos9 \
	test-neos8-defaults test-neos8-enforce-all test-neos8-enforce-role test-neos8-enforce-provider test-neos8-issuer-name \
	test-neos9-defaults test-neos9-enforce-all test-neos9-enforce-role test-neos9-enforce-provider test-neos9-issuer-name \
	down

# COLORS
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
RESET  := $(shell tput -Txterm sgr0)

# initial setup
setup-test:
	@echo '${GREEN}Installing test setup.${RESET}' && \
	cd $(E2E_DIR) && \
	if [ -s "$$NVM_DIR" ]; then \
		echo '${GREEN}Found nvm on system, using it to install nodejs!${RESET}'; \
		. "$$NVM_DIR/nvm.sh" && nvm install; \
	fi && \
	npm install && npx playwright install --with-deps chromium && \
	echo '' && echo '${GREEN}generate BDD files from feature files${RESET}' && npm run generate-tests

# generate BDD files from feature files
generate-bdd-files:
	@echo '${GREEN}generate BDD files from feature files${RESET}'; \
	cd $(E2E_DIR) && npm run generate-tests

## Run all E2E tests
test: test-neos8 test-neos9

## Run all neos8 E2E tests
test-neos8: test-neos8-defaults test-neos8-enforce-all

## Run all neos9 E2E tests
test-neos9: test-neos9-defaults test-neos9-enforce-all

test-neos8-defaults:
	cd $(E2E_DIR) && npm run test:neos8:defaults

test-neos8-enforce-all:
	cd $(E2E_DIR) && npm run test:neos8:enforce-all

test-neos8-enforce-role:
	cd $(E2E_DIR) && npm run test:neos8:enforce-role

test-neos8-enforce-provider:
	cd $(E2E_DIR) && npm run test:neos8:enforce-provider

test-neos8-issuer-name:
	cd $(E2E_DIR) && npm run test:neos8:issuer-name

test-neos9-defaults:
	cd $(E2E_DIR) && npm run test:neos9:defaults

test-neos9-enforce-all:
	cd $(E2E_DIR) && npm run test:neos9:enforce-all

test-neos9-enforce-role:
	cd $(E2E_DIR) && npm run test:neos9:enforce-role

test-neos9-enforce-provider:
	cd $(E2E_DIR) && npm run test:neos9:enforce-provider

test-neos9-issuer-name:
	cd $(E2E_DIR) && npm run test:neos9:issuer-name

## Tear down all docker compose environments and remove volumes
down:
	@echo '${YELLOW}Shutting down all SUTs and removing their volumes.${RESET}'
	@docker compose -f $(NEOS8_COMPOSE) down -v
	@docker compose -f $(NEOS9_COMPOSE) down -v
