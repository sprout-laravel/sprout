.PHONY: test phpunit analyse tidy infection install

.DEFAULT_GOAL := test

test:
	composer test

phpunit:
	vendor/bin/phpunit $(ARGS)

analyse:
	composer analyse

tidy:
	composer tidy

# Infection spawns many parallel PHPStan/Larastan workers. On macOS the default
# open-files limit (often 256) can be too low; if you hit "Too many open files",
# raise it first: ulimit -n 65536
infection:
	composer infection

install:
	composer install
