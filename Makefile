COMPOSE = docker compose
APP = $(COMPOSE) exec app
APP_RUN = $(COMPOSE) run --rm app
APP_RUN_NO_DEPS = $(COMPOSE) run --rm --no-deps app

.PHONY: up down migrate seed test queue shell composer

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

migrate:
	$(APP) php artisan migrate

seed:
	$(APP) php artisan db:seed

test:
	$(APP_RUN_NO_DEPS) -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_HOST= -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array php artisan test

queue:
	$(APP) supervisord -n -c /etc/supervisor/supervisord.conf

shell:
	$(APP) sh

composer:
	$(APP_RUN_NO_DEPS) composer install
