# GitHub Copilot / AI agent instructions for Grupo NH

Quick, actionable guidance to help an AI agent be productive editing and testing this codebase.

## Big picture
- Monolithic PHP web application (no framework) served via Apache/XAMPP. Files are in project root (many `*.php` endpoints) and static assets under `assets/`.
- Database: MySQL (PDO usage via `Database::connect()` in `database.php`). Business logic lives in procedural scripts and large helper file `funciones.php`.
- UI/UX: Classic server-rendered PHP pages + AJAX endpoints (many `ajax*.php` files) that return JSON. Frontend uses CKEditor and jQuery-like patterns.

## Where to look first (file map)
- `config.php` — session lifecycle, APP_ENV handling and global bootstrapping.
- `database.php` — PDO connection helper and credentials (update here for local DB).
- `funciones.php` — central helper functions: SQL helpers (`debugQuery`, `debugExecute`), notification and mail logic, common business operations.
- `PHPMailer/` — bundled mail library used by `funciones.php` and mail-related scripts.
- `composer.json` — external PHP deps (e.g., phpspreadsheet); run `composer install` in the project root when needed.

## Environment & local setup (how to run)
- Run on Windows with XAMPP (Apache + PHP + MySQL). Create a database named `gruponh` (or update `database.php`).
- Set APP_ENV via `env.php` or environment variables. Example (repo README):
  - `env.php` (ignored by Git): `<?php putenv('APP_ENV=development');`
- For SMTP in development copy `config.dev.php.example` → `config.dev.php` and fill credentials; `funciones.php` will use it when `APP_ENV=development`.
- Run `composer install` to pull `phpoffice/phpspreadsheet` if you modify spreadsheet code.

## Project-specific conventions & patterns
- Debugging SQL: Many scripts use `debugExecute($pdo, $sql, $params, $modoDebug, $label)` and `debugQuery()` to print parameterized SQL. To trigger debug output while browsing, append `?debug=1` to a page URL.
  - Example: `marcarItemsEntregadoCompra.php` prints debug SQL with `debugQuery()`.
- AJAX/session expiry: `config.php` detects AJAX via `HTTP_X_REQUESTED_WITH` and returns JSON 401 on expired sessions. When writing AJAX endpoints, follow existing pattern.
- Session & auth: `config.php` enforces session login early; many endpoints require `$_SESSION['user']` and `$_SESSION['user']['permisos']`.
- Naming: `ajax*` endpoints are API-like, other `*.php` files are full-page controllers. Keep that pattern when adding new endpoints.
- SQL execution: prefer prepared statements & the `debugExecute` wrapper for consistency with existing logging conventions.

## Integration points & external dependencies
- PHPMailer (local copy) for sending emails; see `funciones.php` and `nuevaPackingList.php` for examples.
- PhpSpreadsheet is used for Excel exports — run `composer install` to ensure it's available.
- Several vendor/readme folders exist (PHPMailer, PhpSpreadsheet, php-imap) — inspect those when modifying related features.

## Editing & PR tips for AI agents
- Keep changes minimal and localized; the codebase is procedural and brittle to large refactors without tests.
- If changing DB schema or queries, search for all uses of affected columns/tables (grep for table names) and update dependent logic (SQL queries across multiple scripts).
- Use `?debug=1` when testing DB updates to view executed SQL and row counts.
- When adding new functionality, add a short comment at top of changed files describing intent and reference existing similar files (e.g., copy structure from another `ajax*.php` if creating an AJAX handler).

## Examples to reference
- Show SQL debug: `marcarItemsEntregadoCompra.php` uses `debugQuery($pdo, $sql, $params)` and prints labelled SQL sections.
- Mail sending: `nuevaPackingList.php` / `funciones.php` use `PHPMailer` and load dev credentials from `config.dev.php` when `APP_ENV=development`.
- DB connect: `database.php::connect()` — update credentials here for local work.

## Safety & testing notes
- No automated tests are present. Validate changes manually in a local XAMPP environment and test key user flows (login/session expiry, AJAX endpoints, email sending in dev with `config.dev.php`).

---
If you'd like, I can merge this into an existing `.github/copilot-instructions.md` (if you already have one), or iterate on wording and add short file examples/snippets. Please tell me any missing details you'd like included.