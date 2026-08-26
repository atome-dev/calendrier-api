# School Holidays Calendar API

[![Laravel 13](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![API](https://img.shields.io/badge/API-REST-0A7EA4)](#api-reference)

> **FR — Résumé :** API Laravel qui synchronise les vacances scolaires françaises depuis des fichiers ICS, conserve les changements en base de données et expose un calendrier JSON multi-zones.
>
> **EN — Summary:** Laravel API that synchronizes French school holidays from ICS files, persists changes in a database, and exposes a multi-zone JSON calendar.

## Table of contents / Sommaire

- [Project description / Description](#project-description--description)
- [Features / Fonctionnalités](#features--fonctionnalités)
- [Tech stack / Stack technique](#tech-stack--stack-technique)
- [Requirements / Prérequis](#requirements--prérequis)
- [Installation / Installation](#installation--installation)
- [Configuration / Configuration](#configuration--configuration)
- [Usage / Utilisation](#usage--utilisation)
- [API reference / Référence API](#api-reference--référence-api)
- [Scheduling / Planification](#scheduling--planification)
- [Project structure / Structure](#project-structure--structure)
- [Testing / Tests](#testing--tests)
- [Contributing / Contribuer](#contributing--contribuer)
- [License / Licence](#license--licence)
- [Support / Assistance](#support--assistance)

## Project description / Description

This project provides a small, automation-friendly API for keeping school holiday data current. It retrieves French school calendar data in ICS format, normalizes events by zone and holiday period, compares the incoming calendar with the stored version, and records additions, modifications, and deletions before making the resulting JSON available to client applications.

Le projet fournit une API simple à automatiser pour maintenir à jour les vacances scolaires. Les événements ICS sont récupérés, normalisés par zone et par période, comparés aux données existantes, puis les ajouts, modifications et suppressions sont suivis avant publication du calendrier JSON.

## Features / Fonctionnalités

- ICS import for French school calendars.
- All French zones; the data model can support additional territories when supplied by the source.
- Database persistence with synchronization tracking:
  - added events;
  - modified events;
  - deleted events.
- JSON export suitable for web, mobile, education, and planning applications.
- REST endpoint for consuming the current calendar.
- Weekly synchronization through the Laravel scheduler.
- Production scheduling compatible with Laravel Forge.
- Automated test coverage, including `HolidaysSyncTest`.

## Tech stack / Stack technique

- **PHP 8.4+**
- **Laravel 13**
- **Composer** for PHP dependencies
- **Laravel Artisan** for CLI operations
- **Laravel Scheduler** for recurring synchronization
- A Laravel-supported relational database (SQLite, MySQL, or PostgreSQL)
- **ICS/iCalendar** as the source format
- **JSON/REST** as the public format
- **PHPUnit / Laravel test suite** for automated tests

## Requirements / Prérequis

Install the following before beginning:

- PHP 8.4 or newer with the extensions required by Laravel 13;
- Composer 2.x;
- a supported database;
- Git;
- a web server or PHP's local development server.

## Installation / Installation

```bash
git clone https://github.com/atome-dev/calendrier-api.git school-holidays-api
cd school-holidays-api

composer install
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`, then create the schema:

```bash
php artisan migrate
```

Start the local API:

```bash
php artisan serve
```

The application is then available at `http://127.0.0.1:8000` unless a different host or port is configured.

### Optional local setup

If the project uses a frontend build or publishes application assets, install and build the Node dependencies as appropriate for the repository:

```bash
npm install
npm run build
```

For a development-only server, `npm run dev` may be used alongside `php artisan serve`.

## Configuration / Configuration

Copy `.env.example` to `.env` and set at least the application URL, database connection, and ICS source configuration. Exact variable names should match the configuration files shipped with the application. A typical setup looks like this:

```dotenv
APP_NAME="School Holidays API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/database/database.sqlite

# Use the names defined by the application's config/services.php or sync config.
HOLIDAYS_ICS_URL=https://example.org/school-holidays.ics
HOLIDAYS_JSON_PATH=storage/app/public/holidays.json
```

For SQLite, create the database file if it does not exist:

```bash
touch database/database.sqlite
php artisan migrate
```

### Production configuration checklist

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Use a managed MySQL or PostgreSQL database where appropriate.
- Configure the production ICS source URL(s) and any authentication required by the source.
- Set a stable `APP_KEY`; never commit `.env` or secrets.
- Configure `APP_URL`, cache, queue, mail, and logging values for the deployment environment.
- Ensure the process can write to `storage/` and `bootstrap/cache/`.
- If JSON is served from a public disk, run `php artisan storage:link` when required.
- Restrict CORS and API access according to the consumers of the service.

## Usage / Utilisation

### Synchronize the calendar

Run the synchronization command manually whenever you need to refresh the data:

```bash
php artisan holidays:sync
```

A successful run should fetch the configured ICS source, compare events with the database, apply additions/updates/deletions, and regenerate the JSON export. If the command name differs in your application, use `php artisan list` to identify the registered holidays command.

Useful Laravel diagnostics:

```bash
php artisan route:list
php artisan schedule:list
php artisan about
```

### Consume the JSON API

```bash
curl -H "Accept: application/json" \
  http://127.0.0.1:8000/api/vacances
```

The response is JSON and is intended to contain the available holiday periods grouped or filterable by zone. A representative response shape is:

```json
{
  "data": {
    "A": [],
    "B": [],
    "C": [],
    "Corse": [],
    "Guadeloupe": [],
    ...  
  }
}
```

The exact envelope, field names, date format, and query parameters are defined by the application's route/controller/resource implementation. Keep API consumers tolerant of additional zones and fields.

## API reference / Référence API

### `GET /api/holidays`

Returns the current school holiday calendar as JSON.

**Headers**

```http
Accept: application/json
```

**Example request**

```bash
curl -sS -H 'Accept: application/json' \
  'https://api.example.com/api/vacances'
```

**Suggested client handling**

- Treat dates as ISO-8601 calendar dates or timestamps according to the response contract.
- Do not hard-code only zones A/B/C; Corse and overseas zones may be present.
- Cache responses where appropriate and refresh after the scheduled synchronization.
- Handle non-2xx responses and temporary source/synchronization failures gracefully.

Use `php artisan route:list --path=api` to verify the exact route in a deployed instance.

## Scheduling / Planification

The Laravel scheduler should invoke the synchronization weekly. Inspect `routes/console.php` or the application's console/bootstrap configuration to confirm the registered schedule:

```bash
php artisan schedule:list
```

### Local scheduler check

Run the scheduler once:

```bash
php artisan schedule:run
```

For continuous local execution, use:

```bash
php artisan schedule:work
```

### Server cron

Laravel requires one cron entry that runs every minute; Laravel decides whether the weekly task is due:

```cron
* * * * * cd /var/www/school-holidays-api && php artisan schedule:run >> /dev/null 2>&1
```

### Laravel Forge

On Forge, configure the site/server scheduler with the same once-per-minute command (adjust the deployment path and PHP binary):

```bash
cd /home/forge/example.com && php artisan schedule:run
```

Confirm that the Forge user has database, network, and storage permissions, and review `storage/logs/laravel.log` after the first scheduled execution.

## Project structure / Structure

```text
app/
├── Console/             # Artisan commands and scheduler integration
├── Http/
│   ├── Controllers/     # REST API controllers
│   └── Resources/       # JSON response resources, if used
├── Models/              # Holiday and synchronization models
└── Services/            # ICS parsing, synchronization, and export logic
bootstrap/               # Laravel application bootstrap
config/                  # Application, database, services, and sync configuration
database/
├── factories/           # Model factories
├── migrations/          # Database schema
└── seeders/             # Optional seed data
routes/
├── api.php              # API routes
└── console.php          # Console commands and scheduled tasks
storage/
├── app/                 # Generated JSON and application files
└── logs/                # Application logs
tests/
├── Feature/             # HTTP and integration tests
└── Unit/                # Focused unit tests
```

File and class names can vary; use the repository's actual namespaces and Laravel conventions when extending the project.

## Testing / Tests

Run the complete test suite:

```bash
php artisan test
```

Run the synchronization test specifically:

```bash
php artisan test --filter=HolidaysSyncTest
```

You can also use PHPUnit directly when configured:

```bash
vendor/bin/phpunit
```

Before submitting a change, verify migrations, synchronization behavior, API responses, and generated JSON. Tests should cover idempotent re-runs and all three change categories: additions, modifications, and deletions.

## Contributing / Contribuer

Contributions are welcome in both English and French.

1. Fork the repository and create a focused branch:
   ```bash
   git checkout -b feat/improved-zone-filter
   ```
2. Make the smallest coherent change possible.
3. Add or update tests, especially for synchronization edge cases.
4. Run formatting/static-analysis tools configured by the repository, then run `php artisan test`.
5. Update the README or API documentation when behavior changes.
6. Open a pull request with a clear description, test results, migration notes, and any compatibility impact.

Please do not commit credentials, downloaded production calendars, generated secrets, or private data. Preserve the source calendar's licensing and attribution requirements.

## License / Licence

This project is distributed under the **MIT License** unless a different license is provided in the repository. See [LICENSE](LICENSE) for the complete terms.

The upstream ICS calendar may have its own terms of use; check and respect those terms separately from this software license.

## Support / Assistance

For bugs and feature requests, open an issue in the project's repository and include:

- application and PHP/Laravel versions;
- the command or endpoint involved;
- relevant logs with secrets and personal data removed;
- steps to reproduce;
- expected and actual behavior.

For security vulnerabilities, do **not** open a public issue. Contact the project maintainers through the repository's private security channel or the security contact documented there.

---

**FR :** Les noms exacts des variables d'environnement, de la commande Artisan et des routes doivent rester alignés avec l'implémentation du dépôt. **EN:** Keep environment variable names, Artisan command names, and route details synchronized with the repository implementation.
