#!/usr/bin/env bash

rm -Rf build/*

cd build

git clone --depth=1 git@github.com:ebanx/woocommerce-gateway-ebanx-local.git woocommerce-gateway-ebanx-pay

cd woocommerce-gateway-ebanx-pay

composer install --no-ansi --no-dev --no-interaction --no-suggest --optimize-autoloader

rm -Rf .scripts .editorconfig .env.example .git .gitignore .travis.tml composer.* deploy.sh docker-compose.yml Dockerfile Dockerfile.dev package-lock.json phpcs.xml phpunit.xml pre-commit yarn.lock

zip -r1q ../woocommerce-gateway-ebanx-pay.zip ./*
