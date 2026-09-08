# Project architecture

## Current layers

- Root PHP files are legacy public pages and API compatibility entry points.
- `app/` contains the application layer: core services, controllers, repositories, and models.
- `assets/` contains browser-delivered CSS, JavaScript, images, and map data.
- `config/` contains the canonical database and application configuration.
- `storage/` contains runtime data and private blockchain keys. It must not be served publicly.
- `sql/` contains schema and database setup files.
- `scripts/` contains maintenance and migration commands.
- `tests/` contains test utilities and fixtures.
- `analysis/` contains data-analysis code and generated output.

## Compatibility policy

Existing root URLs remain available while the application is migrated. New backend code should use `app/` and new public entry points should be placed under `public/` where possible.

The root `config.php` file is a compatibility wrapper. Use `config/config.php` as the canonical configuration source.

## Deployment notes

Set Apache's document root to `public/` for a production deployment, or keep the project root as the temporary document root with the root `.htaccess` protection enabled. Never expose `storage/`, `config/`, `app/`, `scripts/`, `sql/`, or `analysis/` directly.
