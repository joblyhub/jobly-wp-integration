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

## v0.3.0 highlights

* Branded admin (header, section tabs, status pill, dashboard with stat cards, checklist, latest applications and jobs, empty states), first-run **setup wizard** (connect, careers page, appearance), job detail in two columns with copy buttons, Help tab and footer on plugin screens.
* Careers page `/kariera/` with search, filters (location, type, remote; plain GET params, no JavaScript needed), cards, pagination; job page with breadcrumb, chips, sticky summary and the Jobly embed.
* **Google for Jobs**: `JobPosting` JSON-LD built only from API fields, jobs in the core sitemap (`wp-sitemap.xml`); both can be switched off.
* Blocks: `jobly/jobs-list` and `jobly/apply-form` (server-rendered, no build step). Shortcodes stay.
* Theme overrides, hooks, en_US translation, release zip workflow.

## Theme overrides

Copy any file from `jobly-integration/templates/` to `yourtheme/jobly/` (child theme wins over parent):

| File | |
|---|---|
| `archive-jobs.php` | list: count, filters, cards, pagination |
| `single-job.php` | job page |
| `parts/job-card.php`, `parts/filters.php`, `parts/pagination.php`, `parts/empty.php`, `parts/job-sidebar.php` | pieces |

Variables arrive in `$args`. Folder name: filter `jobly_integration_template_path` (default `jobly`).

## Hooks

| Hook | Type | |
|---|---|---|
| `jobly_integration_jobs_query_args` | filter | search, location, type, remote, per_page, page before the list is queried |
| `jobly_integration_jobs` | filter | result of the query (`jobs`, `total`, `pages`, `page`, `all`) and its args |
| `jobly_integration_job_card` | filter | HTML of one card, plus `$job` and list args |
| `jobly_integration_job_schema` | filter | `JobPosting` array of a job (return `array()` to omit) |
| `jobly_integration_template_path` | filter | theme folder for overrides |
| `jobly_integration_template` | filter | resolved template path and name |
| `jobly_integration_before_jobs_list` / `_after_jobs_list` | action | around the list |
| `jobly_integration_before_single_job` / `_after_single_job` | action | around the job page |

The API returns no job description, so the JSON-LD description is a plain statement of the real fields unless you filter it (or Jobly adds a `description` field, which is used automatically).

## Credentials

Every API request carries two credentials: the company key `Authorization: Bearer jbl_…` and the integration's app token `App-Token: jba_…` (issued by Jobly for approved integrations). The app token comes from, first match wins, the `JOBLY_APP_TOKEN` constant in `wp-config.php` or Jobly HRM, Settings, Connection (stored masked, never echoed, not autoloaded). Neither is hard-coded in the plugin. Jobly's two 401 answers (missing/invalid App-Token vs wrong company key) are shown as separate messages. The company (name, logo, links) is read from the key via `GET /api/v1/company`, job content from `GET /api/v1/jobs/{slug}`, categories from `GET /api/v1/categories`; each falls back gracefully on older Jobly (404).

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

Release: push a tag `v0.3.0`; `.github/workflows/release.yml` checks it against the plugin header, builds `jobly-integration.zip` (plugin folder only, no dev files) and attaches it to the GitHub release.

License: GPL-2.0-or-later.
