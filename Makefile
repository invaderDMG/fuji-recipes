# Fuji Recipes — atajos para el día a día. `make` sin argumentos muestra la ayuda.
#
#   make update          busca recetas nuevas y muestra qué cambiaría
#   make import-force    aplica los cambios (con copia de seguridad)
#   make serve           abre la app en http://127.0.0.1:8000

.DEFAULT_GOAL := help
.PHONY: help serve update scrape import import-force test lint check setup setup-python migrate backup storage-link

PY      := .venv/bin/python
WEB     := web
ARTISAN := cd $(WEB) && php artisan
# UTC, como las copias de recipes:import, para que se ordenen juntas
STAMP    = $(shell date -u +%Y%m%d-%H%M%S)

# Opcionales:
#   SENSOR=x-trans-v   scrape: solo ese índice de fujixweekly.com
#   ONLY=fxw|mine      import: solo ese origen
SENSOR ?=
ONLY   ?=

help: ## Muestra esta ayuda
	@echo "Uso: make <objetivo> [SENSOR=x-trans-v] [ONLY=fxw|mine]"
	@echo
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------- día a día

serve: ## Arranca la app en http://127.0.0.1:8000 (admite fotos de hasta 64 MB)
	cd $(WEB)/public && php -d upload_max_filesize=64M -d post_max_size=64M \
		-S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php

update: scrape import ## Busca recetas nuevas y muestra el diff (no escribe en la app)

scrape: ## Descarga los índices y solo los posts nuevos (el resto sale de la caché)
	$(PY) fxw_scraper.py $(if $(SENSOR),--only $(SENSOR))

import: ## Simula la importación y muestra el diff (no escribe)
	$(ARTISAN) recipes:import $(if $(ONLY),--only=$(ONLY))

import-force: ## Importa de verdad (antes copia la BD a web/storage/app/backups)
	$(ARTISAN) recipes:import --force $(if $(ONLY),--only=$(ONLY))

# ---------------------------------------------------------------- desarrollo

test: ## Ejecuta los tests
	$(ARTISAN) test

lint: ## Formatea el código con Pint
	cd $(WEB) && ./vendor/bin/pint

check: lint test ## Pint + tests

# ---------------------------------------------------------------- instalación y BD

setup: setup-python ## Primera instalación: dependencias, .env, BD, sensores y usuario local
	cd $(WEB) && composer install
	test -f $(WEB)/.env || (cp $(WEB)/.env.example $(WEB)/.env && $(ARTISAN) key:generate)
	touch $(WEB)/database/database.sqlite
	$(ARTISAN) migrate --seed
	$(ARTISAN) storage:link
	cd $(WEB) && npm install
	@echo "Revisa FXW_SOURCE_DB en web/.env y ejecuta: make update"

setup-python: ## Crea .venv con las dependencias del scraper
	test -x $(PY) || python3 -m venv .venv
	$(PY) -m pip install --quiet requests beautifulsoup4

migrate: backup ## Aplica migraciones pendientes (con copia de seguridad antes)
	$(ARTISAN) migrate

backup: ## Copia la BD de la app a web/storage/app/backups
	mkdir -p $(WEB)/storage/app/backups
	sqlite3 $(WEB)/database/database.sqlite ".backup '$(WEB)/storage/app/backups/database-$(STAMP)-manual.sqlite'"
	@echo "Copia: $(WEB)/storage/app/backups/database-$(STAMP)-manual.sqlite"

storage-link: ## Enlaza web/public/storage para servir tus fotos
	$(ARTISAN) storage:link
