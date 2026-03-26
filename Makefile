NEOS8_COMPOSE = Tests/sytem_under_test/neos8/docker-compose.yaml
NEOS9_COMPOSE = Tests/sytem_under_test/neos9/docker-compose.yaml
E2E_DIR = Tests/E2E

.PHONY: setup-test test test-neos8 test-neos9 \
	test-neos8-defaults test-neos8-enforce-all test-neos8-enforce-role test-neos8-enforce-provider test-neos8-issuer-name \
	test-neos9-defaults test-neos9-enforce-all test-neos9-enforce-role test-neos9-enforce-provider test-neos9-issuer-name \
	down

# initial setup
setup-test:
	cd $(E2E_DIR) && \
	if [ -s "$$HOME/.nvm/nvm.sh" ]; then \
		. "$$HOME/.nvm/nvm.sh" && nvm install; \
	fi && \
	npm install && npx playwright install

## Run all E2E tests
test:
	cd $(E2E_DIR) && npm test

## Run all neos8 E2E tests
test-neos8:
	cd $(E2E_DIR) && npm run test:neos8:defaults && npm run test:neos8:enforce-all && npm run test:neos8:enforce-role && npm run test:neos8:enforce-provider && npm run test:neos8:issuer-name

## Run all neos9 E2E tests
test-neos9:
	cd $(E2E_DIR) && npm run test:neos9:defaults && npm run test:neos9:enforce-all && npm run test:neos9:enforce-role && npm run test:neos9:enforce-provider && npm run test:neos9:issuer-name

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
	docker compose -f $(NEOS8_COMPOSE) down -v
	docker compose -f $(NEOS9_COMPOSE) down -v
