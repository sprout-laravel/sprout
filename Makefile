.PHONY: test phpunit infection analyse tidy install shell build

.DEFAULT_GOAL := test

test:
	docker compose run --rm -T php composer test

phpunit:
	docker compose run --rm -T php vendor/bin/phpunit $(ARGS)

infection:
	docker compose run --rm -T php composer infection

analyse:
	docker compose run --rm -T php composer analyse

tidy:
	docker compose run --rm -T php composer tidy

install:
	docker compose run --rm -T php composer install

shell:
	docker compose run --rm php bash

build:
	docker compose build
