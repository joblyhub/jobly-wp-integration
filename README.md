# Jobly.si HRM — WordPress plugin

Connects a WordPress site to a company account on [Jobly.si](https://jobly.si): list and publish jobs, read applications, and give every open job its own page on the site. The plugin lives in [`jobly-integration/`](jobly-integration) — zip that folder to install. Details and the list of external requests: [`jobly-integration/readme.txt`](jobly-integration/readme.txt).

## What it does

wp-admin, menu **Jobly HRM** (Slovenian UI):

| Screen | |
|---|---|
| Pregled | connection status, open jobs, new applications (7 days), quick links |
| Delovna mesta | list (search, status filter, pagination, screen options) and detail per job |
| Dodaj novo | form mirroring `POST /api/v1/jobs` (validation errors shown beside the fields) |
| Prijave | read-only list and detail; applicant data is read live, never stored or cached in WP |
| Nastavitve | tabs Povezava (API key `jbl_…`, verify), Prikaz (links base, colour, height), Demo |

Public side:

* `/kariera/` — server-rendered list of open jobs; `/kariera/{job-slug}/` — job page with the Jobly embed (`show=full`); base is configurable, 404 for unknown slugs, canonical to the job on Jobly.
* Shortcodes: `[jobly]`, `[jobly job="slug" show="full"]`, `[jobly_jobs]`.

The application form, consent and anti-spam stay on Jobly (iframe); WordPress never handles applicant submissions.

**Demo mode** (default until a key is verified): an invented company "Primer d.o.o."; no request to Jobly is made.

## API used

`GET /api/v1/jobs`, `GET /api/v1/applications`, `POST /api/v1/jobs` with `Authorization: Bearer jbl_…`. The API has no update/show/filter endpoints, so editing is a link to Jobly, and job/application detail comes from the list responses (filtered locally; up to 5 pages of 25).

`define( 'JOBLY_API_BASE', 'http://host:port' );` in `wp-config.php` overrides the server-side API base (local testing); the browser-facing embed base stays the "Naslov Jobly" setting.

## Development

```
composer install        # phpcs + WordPress Coding Standards + PHPCompatibilityWP (dev only)
vendor/bin/phpcs        # must be clean
php -l <file>
```

Plugin Check: `wp plugin install plugin-check --activate && wp plugin check jobly-integration`. Translations template: `wp i18n make-pot jobly-integration jobly-integration/languages/jobly-integration.pot`. CI (`.github/workflows/ci.yml`) runs `php -l` and phpcs on PHP 7.4 and 8.3.

Release zip: `zip -r jobly-integration.zip jobly-integration`

License: GPL-2.0-or-later.
