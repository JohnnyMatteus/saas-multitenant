# Atalhos do ambiente local. `make help` lista os alvos.
COMPOSE := docker compose
CORE    := $(COMPOSE) --profile core
ALL     := $(COMPOSE) --profile core --profile observability --profile tools

.DEFAULT_GOAL := help
.PHONY: help smoke web-dev up up-all up-portfolio down logs ps seed test test-unit test-integration lint stan check clean network token evidencia

help: ## Lista os alvos
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  %-18s %s\n", $$1, $$2}'

up: ## Sobe o núcleo (API, worker, MySQL, Redis, Keycloak, Mailpit, LocalStack)
	$(CORE) up -d --build --wait

up-all: ## Núcleo + Prometheus + Jaeger (spans) + Adminer
	OTEL_EXPORTER_OTLP_ENDPOINT=$${OTEL_EXPORTER_OTLP_ENDPOINT:-http://jaeger:4318} $(ALL) up -d --build --wait

up-portfolio: network ## Núcleo ligado também à rede compartilhada portfolio-net
	$(CORE) -f docker-compose.yml -f docker-compose.portfolio.yml up -d --build --wait

down: ## Para tudo (mantém volumes)
	$(ALL) down

logs: ## Acompanha os logs (JSON) da API e do worker
	$(CORE) logs -f app worker

ps: ## Estado dos serviços
	$(ALL) ps

web-dev: ## Front-end em modo dev (Vite em :5173, proxy /api para :8000)
	cd web && npm ci && npm run dev

seed: ## Recria os dados fictícios de desenvolvimento
	$(CORE) run --rm migrate php artisan db:seed --force

test: ## Suíte completa (sobe só o MySQL, se preciso)
	$(CORE) up -d --wait mysql
	vendor/bin/phpunit

test-unit: ## Só testes unitários (sem banco)
	vendor/bin/phpunit --testsuite Unit

test-integration: ## Testes de integração com MySQL (isolamento, agenda, concorrência)
	$(CORE) up -d --wait mysql
	vendor/bin/phpunit --testsuite Feature

lint: ## Pint (estilo)
	vendor/bin/pint --test

stan: ## PHPStan/Larastan nível 8
	vendor/bin/phpstan analyse --memory-limit=1G

check: lint stan test ## O mesmo que o CI roda

network: ## Cria a rede compartilhada do ecossistema (idempotente)
	@docker network inspect portfolio-net >/dev/null 2>&1 || docker network create portfolio-net

token: ## Token de DEV de um usuário do realm de exemplo (KC_USER=...)
	@curl -s -X POST http://localhost:$${KEYCLOAK_PORT:-8080}/realms/estudios/protocol/openid-connect/token \
		-d grant_type=password -d client_id=studio-dev-cli \
		-d username=$${KC_USER:-aluna.lotus@example.com} -d password=dev-only-change-me \
		| php -r '$$t = json_decode(stream_get_contents(STDIN)); echo $$t->access_token ?? "erro: o Keycloak está no ar?", PHP_EOL;'

smoke: ## Smoke da stack já no ar (make up): login real, reserva, isolamento, pacotes, export, front, e-mail
	bash scripts/smoke.sh

evidencia: ## Sobe a stack, zera os dados e grava o vídeo do fluxo principal (Playwright)
	$(COMPOSE) --profile core --profile observability up -d --build --wait
	$(CORE) run --rm migrate php artisan migrate:fresh --seed --force
	cd e2e/evidencia && npm ci && npx playwright install chromium && npx playwright test

clean: ## Remove contêineres, volumes e caches locais
	$(ALL) down -v --remove-orphans
	rm -rf .phpunit.cache .phpstan.cache
