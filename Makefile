.PHONY: up down build shell migrate seed test artisan

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

shell:
	docker compose exec app bash

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

test:
	docker compose exec app php artisan test

artisan:
	docker compose exec app php artisan $(CMD)
