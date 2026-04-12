.PHONY: test phpunit analyse install shell build

.DEFAULT_GOAL := test

test:
	docker compose run --rm -T php composer test

phpunit:
	docker compose run --rm -T php vendor/bin/phpunit $(ARGS)

analyse:
	docker compose run --rm -T php composer analyse

install:
	docker compose run --rm -T php composer install

shell:
	docker compose run --rm php bash

build:
	docker compose build
