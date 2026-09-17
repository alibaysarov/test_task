# Repository Guidelines

## Project Structure & Module Organization

This repository is a Laravel 12 referral API requiring PHP 8.2+. API routes live in `routes/api.php`; HTTP controllers and middleware belong in `app/Http/`. Eloquent models are in `app/Models/`, referral logic in `app/Services/Referral/`, and payment event handling in `app/Observers/`. Configuration lives in `config/`, schema changes and demo data in `database/migrations/` and `database/seeders/`. `bootstrap/` initializes Laravel, `public/` contains the HTTP entry point, and `storage/` holds runtime files. There is no frontend asset build.

## Build, Test, and Development Commands

Run from the repository root with PHP, Composer, and SQLite support:

- `composer install` — install locked dependencies.
- `cp -n .env.example .env` — initialize configuration without replacing existing settings.
- `touch database/database.sqlite` — create the SQLite database.
- `php artisan key:generate` — generate the application key.
- `php artisan migrate --seed` — apply migrations and populate demo data.
- `php artisan serve` — run the local API at `http://localhost:8000`; align `APP_URL` accordingly.
- `php artisan route:list` — inspect registered routes.
- `vendor/bin/phpunit` — run the feature test suite.
- `composer docs` — regenerate Scribe API documentation after endpoint changes.

Docker Compose configuration and `ReferralController` are included; the README documents both Compose and local execution.

## Coding Style & Naming Conventions

Match existing PHP style: four-space indentation, class and method opening braces on separate lines, and explicit parameter and return types where practical. Follow PSR-4 namespaces under `App\`. Use PascalCase classes, camelCase methods, snake_case database fields, and UPPER_SNAKE_CASE constants. No formatter or linter is currently configured.

## PHPDoc Documentation

Models in `app/Models/` and services in `app/Services/` must have up-to-date Russian PHPDoc for classes and declared methods, including Eloquent attributes and relationships. When creating, changing, or reviewing documentation for these classes, apply the project skill [phpdoc](.agents/skills/phpdoc/SKILL.md). Document actual behavior and keep PHPDoc synchronized with code changes.

## Testing Guidelines

PHPUnit 11 is configured for `tests/Feature/` with an in-memory SQLite database. Currently only `tests/TestCase.php` exists; add feature tests as `*Test.php` classes extending `Tests\TestCase`. No coverage threshold is configured. Cover duplicate attachments, self-referrals, invalid codes, payment eligibility, and earnings totals. Preserve the existing reward calculation in `ReferralService` and eligibility behavior in `PaymentObserver`.

## Commit & Pull Request Guidelines

History contains only `Initial commit`, so no established commit convention exists. Use concise imperative subjects, such as `Add referral attachment validation`. PRs should describe behavior changes, link relevant issues, and include validation commands and results. For API changes, provide representative requests and responses and regenerate documentation.

## Configuration & Security

Keep `.env`, SQLite databases, and generated dependencies out of commits. `X-Master-Id` is a development identity stub, not production authentication. Treat `migrate:fresh --seed` as destructive: it drops existing tables.
