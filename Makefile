.PHONY: docs test

# Для локального PHP: make docs DOCS_RUNNER=
DOCS_RUNNER ?= docker compose exec -T app
# Для локального PHP: make test TEST_RUNNER=
TEST_RUNNER ?= docker compose exec -T app

docs:
	$(DOCS_RUNNER) composer docs
	$(DOCS_RUNNER) chmod 755 storage/app/private storage/app/private/scribe
	$(DOCS_RUNNER) chmod 644 storage/app/private/scribe/openapi.yaml storage/app/private/scribe/collection.json
	$(DOCS_RUNNER) cp storage/app/private/scribe/openapi.yaml openapi.yaml
	$(DOCS_RUNNER) cp storage/app/private/scribe/collection.json collections.json
	$(DOCS_RUNNER) chmod 644 openapi.yaml collections.json
	@echo "Готово: openapi.yaml и collections.json в корне проекта."

test:
	$(TEST_RUNNER) vendor/bin/phpunit $(if $(CASE),--filter "$(CASE)")
