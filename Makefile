DC      = docker compose
PHP     = $(DC) exec php
CONSOLE = $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: help start stop sh sass-watch db-reset fixtures test phpstan cs cs-fix qa

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-12s\033[0m %s\n", $$1, $$2}'

start: ## Construit et démarre l'application
	$(DC) up -d --build --wait
	$(PHP) composer install --no-interaction
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(CONSOLE) sass:build
	@echo "Application : http://localhost:8080  |  E-mails (Mailpit) : http://localhost:8025"

stop: ## Arrête les conteneurs
	$(DC) down

sh: ## Ouvre un shell dans le conteneur PHP
	$(PHP) bash

sass-watch: ## Recompile le SCSS à chaque modification
	$(CONSOLE) sass:build --watch

db-reset: ## Recrée la base de données de développement
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

fixtures: ## Recharge les données de démonstration (vide la base de développement)
	$(CONSOLE) foundry:load-fixtures main --no-interaction

test: ## Lance les tests (la base de test est reconstruite automatiquement)
	$(PHP) vendor/bin/phpunit

phpstan: ## Analyse statique (PHPStan niveau 8)
	$(CONSOLE) cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Vérifie le style du code
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige le style du code
	$(PHP) vendor/bin/php-cs-fixer fix

qa: cs phpstan test ## Lance toutes les vérifications, comme la CI
