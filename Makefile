# Fuji Recipes — atajos para el día a día. `make` sin argumentos muestra la ayuda.
#
#   make update          busca recetas nuevas y muestra qué cambiaría
#   make import-force    aplica los cambios (con copia de seguridad)
#   make serve           abre la app en http://127.0.0.1:8000
#   make deploy          git push y actualiza el droplet (DROPLET=fuji@tu-dominio)
#   make pull            baja del droplet la BD y las fotos para trabajar en local

.DEFAULT_GOAL := help
.PHONY: help serve update scrape import import-force test lint check setup setup-python migrate backup storage-link \
        deploy push-data pull server-update

PY      := .venv/bin/python
WEB     := web
ARTISAN := cd $(WEB) && php artisan
# UTC, como las copias de recipes:import, para que se ordenen juntas
STAMP    = $(shell date -u +%Y%m%d-%H%M%S)

# Opcionales:
#   SENSOR=x-trans-v   scrape: solo ese índice de fujixweekly.com
#   ONLY=fxw|mine      import: solo ese origen
#   DROPLET=fuji@host  deploy, push-data y pull (o export DROPLET=... en tu shell)
SENSOR  ?=
ONLY    ?=
DROPLET ?=
REMOTE  := fuji-recipes

help: ## Muestra esta ayuda
	@echo "Uso: make <objetivo> [SENSOR=x-trans-v] [ONLY=fxw|mine] [DROPLET=fuji@host]"
	@echo
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------- día a día

serve: ## Arranca la app en http://127.0.0.1:8000 (admite fotos de hasta 64 MB)
	cd $(WEB)/public && php -d upload_max_filesize=64M -d post_max_size=64M -d max_execution_time=120 \
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

setup-python: ## Crea .venv con las dependencias del scraper y del OCR de capturas
	test -x $(PY) || python3 -m venv .venv
	$(PY) -m pip install --quiet requests beautifulsoup4
# OCR: Vision en macOS; en Linux, Tesseract (paquete del sistema)
ifeq ($(shell uname -s),Darwin)
	$(PY) -m pip install --quiet pyobjc-framework-Vision
else
	@command -v tesseract >/dev/null || echo "Para leer capturas instala Tesseract: sudo apt install tesseract-ocr"
endif

migrate: backup ## Aplica migraciones pendientes (con copia de seguridad antes)
	$(ARTISAN) migrate

backup: ## Copia la BD de la app a web/storage/app/backups
	mkdir -p $(WEB)/storage/app/backups
	sqlite3 $(WEB)/database/database.sqlite ".backup '$(WEB)/storage/app/backups/database-$(STAMP)-manual.sqlite'"
	@echo "Copia: $(WEB)/storage/app/backups/database-$(STAMP)-manual.sqlite"

storage-link: ## Enlaza web/public/storage para servir tus fotos
	$(ARTISAN) storage:link

# ---------------------------------------------------------------- droplet (ver deploy/provision.sh)

need-droplet:
	@test -n "$(DROPLET)" || (echo "Falta DROPLET, p. ej.: make $(MAKECMDGOALS) DROPLET=fuji@tu-dominio"; exit 1)

deploy: need-droplet ## Hace git push de main y el droplet hace pull y server-update (no toca datos ni .env)
	@test "$$(git branch --show-current)" = main || (echo "Despliega desde main"; exit 1)
	@git diff --quiet HEAD || (echo "Hay cambios sin commit: haz commit antes de desplegar"; exit 1)
	git push origin main
	ssh $(DROPLET) 'cd $(REMOTE) && git pull --ff-only && make server-update'

push-data: need-droplet ## Primera vez: sube las BDs, tus fotos, la caché del scraper y el .env de plantilla
	@ssh $(DROPLET) 'test -d $(REMOTE)/.git' || (echo "El droplet no tiene el repo: ejecuta antes deploy/provision.sh"; exit 1)
	@ssh $(DROPLET) 'test ! -e $(REMOTE)/web/database/database.sqlite' || \
		(echo "El droplet ya tiene BD: no la piso (make pull para bajarla)"; exit 1)
	mkdir -p $(WEB)/storage/app/backups
	sqlite3 $(WEB)/database/database.sqlite ".backup '$(WEB)/storage/app/backups/push-data.sqlite'"
	rsync -az $(WEB)/storage/app/backups/push-data.sqlite $(DROPLET):$(REMOTE)/web/database/database.sqlite
	rm $(WEB)/storage/app/backups/push-data.sqlite
	rsync -az fujixweekly.db .fxw_cache $(DROPLET):$(REMOTE)/
	rsync -az $(WEB)/storage/app/public/ $(DROPLET):$(REMOTE)/web/storage/app/public/
	ssh $(DROPLET) 'test -e $(REMOTE)/web/.env || cp $(REMOTE)/deploy/production.env.example $(REMOTE)/web/.env'
	@echo "Ahora en el droplet: rellena $(REMOTE)/web/.env y ejecuta: cd $(REMOTE) && make server-update"

pull: need-droplet ## Baja del droplet la BD de la app, la de origen y tus fotos (copia antes la local)
	@test ! -e $(WEB)/database/database.sqlite || $(MAKE) --no-print-directory backup
	ssh $(DROPLET) 'sqlite3 $(REMOTE)/web/database/database.sqlite ".backup /tmp/fuji-pull.sqlite"'
	rsync -az $(DROPLET):/tmp/fuji-pull.sqlite $(WEB)/database/database.sqlite
	ssh $(DROPLET) 'rm /tmp/fuji-pull.sqlite'
	rsync -az $(DROPLET):$(REMOTE)/fujixweekly.db .
	rsync -az $(DROPLET):$(REMOTE)/web/storage/app/public/ $(WEB)/storage/app/public/

server-update: setup-python ## (En el droplet) Dependencias, migraciones con copia y cachés
	cd $(WEB) && composer install --no-dev --optimize-autoloader --no-interaction
	grep -q '^APP_KEY=.\+' $(WEB)/.env || $(ARTISAN) key:generate --force
	$(MAKE) --no-print-directory backup
	$(ARTISAN) migrate --force
	test -L $(WEB)/public/storage || $(ARTISAN) storage:link
	$(ARTISAN) optimize
	$(ARTISAN) filament:optimize
