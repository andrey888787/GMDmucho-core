# PHP Runtime Policy

MuchoCore uses PHP-FPM in Docker and treats PHP as a replaceable runtime layer.

## Production runtime

The default container runtime is PHP 8.5:

    php:8.5-fpm-bookworm

The Dockerfile keeps the version behind `PHP_VERSION`, so the runtime can move to a future PHP release without redesigning the application image.

## Compatibility policy

MuchoCore currently declares:

    PHP >= 8.3 and < 9.0

CI continuously checks PHP 8.3, 8.4 and 8.5. PHP 8.5 is the production target; the older supported branches are compatibility coverage.

This split lets MuchoCore ship on the current runtime while avoiding unnecessary breakage for installations that still need an older supported PHP branch.

## Updating PHP

Patch releases are picked up by rebuilding the same minor-version image tag. Minor or major runtime upgrades are handled as a normal repository change:

1. add the new PHP version to the CI matrix;
2. change the default `PHP_VERSION`;
3. run the full Validate workflow;
4. rebuild the Docker image;
5. run the VPS health check and application smoke tests;
6. only then promote the new runtime to the production default.

Do not upgrade PHP by installing a second PHP runtime inside a running MuchoCore container. Replace the container image instead so the runtime remains reproducible.

## Current upstream state

PHP 8.5 is the current stable branch as of September 2026. PHP 8.5.11 was released on September 24, 2026. The upstream support schedule keeps PHP 8.5 in active support through December 31, 2027 and security support through December 31, 2029.

See the official PHP support and migration documentation before promoting a new minor or major version.