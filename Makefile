.PHONY: install dev test lint typecheck migrate-up setup-env help

UV = uv
PYTHON = $(UV) run python
PYTEST = $(UV) run pytest
RUFF = $(UV) run ruff
MYPY = $(UV) run mypy

help:  ## Mostra este help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

install:  ## Instala dependências Python (uv sync)
	$(UV) sync --dev

setup-env:  ## Copia .env.example para .env (apenas se não existir)
	@[ -f .env ] || (cp .env.example .env && echo ".env criado — edite JWT_SECRET antes de usar em produção")

migrate-up:  ## Aplica migrations DuckDB pendentes
	$(PYTHON) -m app.db.migrate

dev:  ## Sobe API com hot reload (porta 8000)
	$(UV) run uvicorn app.main:app --reload --host 127.0.0.1 --port 8000

test:  ## Executa todos os testes (unit + integration)
	$(PYTEST) --cov=app --cov-report=term-missing -v

test-unit:  ## Executa apenas testes unitários
	$(PYTEST) tests/unit -v

test-integration:  ## Executa apenas testes de integração
	$(PYTEST) tests/integration -v

lint:  ## Linting Python (ruff)
	$(RUFF) check app tests

format:  ## Formata código Python (ruff format)
	$(RUFF) format app tests

typecheck:  ## Checagem de tipos (mypy)
	$(MYPY) app

check: lint typecheck  ## lint + typecheck juntos

build-frontend:  ## Compila frontend para produção
	cd frontend && npm ci && npm run build

dev-ui:  ## Inicia apenas o frontend Vite (dev server)
	cd frontend && npm run dev

deploy:  ## Build frontend + instala e ativa serviço systemd
	$(MAKE) build-frontend
	sudo install -m 644 deployments/systemd/unbound-api.service /etc/systemd/system/
	sudo systemctl daemon-reload
	sudo systemctl enable --now unbound-api
	sudo systemctl reload-or-restart unbound-api
	@echo "Deploy concluído"

deploy-caddy:  ## Instala Caddyfile no Caddy
	sudo install -m 644 deployments/caddy/Caddyfile /etc/caddy/Caddyfile
	sudo systemctl reload caddy

logs:  ## Acompanha logs do serviço systemd
	journalctl -u unbound-api -f

migrate-mariadb:  ## Migra dados do MariaDB → DuckDB (requer MYSQL_URL)
	$(PYTHON) tools/migrate_from_mariadb.py

clean:  ## Remove arquivos temporários e caches
	find . -type d -name __pycache__ -exec rm -rf {} + 2>/dev/null || true
	find . -name "*.pyc" -delete 2>/dev/null || true
	rm -rf .pytest_cache htmlcov .coverage frontend/dist
