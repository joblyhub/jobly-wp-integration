=== Jobly.si HRM ===
Contributors: joblyhub
Tags: jobs, careers, job board, recruitment, application form
Requires at least: 6.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show your company's Jobly.si jobs on your WordPress site, publish new ones and read applications from wp-admin.

== Description ==

Jobly.si HRM connects a WordPress site to a company account on Jobly.si (a Slovenian job portal).

* **Connection.** Paste an API key (Jobly, Integrations, API tokens) in Jobly HRM, Settings, Connection, and verify it.
* **Jobs.** List the company's jobs, view details, copy a shortcode, open the job on Jobly.
* **Add new.** Publish a job on Jobly from wp-admin. The form mirrors the Jobly API.
* **Applications.** Read-only list and detail of applications. Applicant data is read live from Jobly and is never stored in WordPress.
* **Careers pages.** Every open job gets its own page on your site, `/kariera/` (list) and `/kariera/{job-slug}/`; the base is configurable.
* **Demo mode.** Until a company is connected the plugin shows an invented company ("Primer d.o.o.") and makes no request to Jobly.

* **Setup wizard.** Connect, pick the careers page and appearance in three steps.
* **Careers page.** Search, filters (location, type, remote), cards, pagination; job pages with a sticky summary.
* **Google for Jobs.** JobPosting structured data from real API fields and a jobs sitemap, both optional.
* **Blocks.** "Jobly - job list" and "Jobly - application form"; shortcodes keep working.
* **Theme overrides.** Copy templates to `yourtheme/jobly/`.

The application form, consent and spam protection stay on Jobly (iframe embed). WordPress never handles applicant submissions.

Shortcodes:

* `[jobly]` - the company's careers embed
* `[jobly job="job-slug"]` - application form for one job
* `[jobly job="job-slug" show="full"]` - job content and form
* `[jobly filter="title,meta,benefits"]` - selected parts only
* `[jobly accent="#2563eb" height="900"]`
* `[jobly_jobs]` - server-rendered list of open jobs, linking to their pages
* `[jobly demo="1"]` - invented example, no request to Jobly

The admin interface is in Slovenian.

== External services ==

This plugin talks to Jobly.si (https://jobly.si), the service it integrates. Terms: https://jobly.si/terms - Privacy: https://jobly.si/privacy

**Server-side API calls** (from your WordPress server, with your API key as a Bearer token, only when demo mode is off and a key is saved):

* `GET /api/v1/jobs` - when you save or verify the key; when a Jobs, Overview or add-job screen needs the job list; when a visitor opens `/kariera/`, a job page or a page with `[jobly_jobs]`. The job list is cached for 5 minutes; "Osvezi" clears it.
* `GET /api/v1/applications` - when you open Overview, Applications or a job's detail screen in wp-admin. Not cached.
* `POST /api/v1/jobs` - when you submit the "Add new" form. Sends the job data you entered.

No visitor data is sent by these calls.

**Browser requests.** Pages with `[jobly]` or a job page load the Jobly embed (`/embed/jobs/{slug}`, `/embed/companies/{slug}`) in an iframe, so the visitor's browser connects to jobly.si.

Every request also sends the integration's `App-Token` header (constant `JOBLY_APP_TOKEN` in wp-config.php or the Connection setting). Further read calls: `GET /api/v1/company` (when you save or verify the key, cached 1 hour), `GET /api/v1/jobs/{slug}` (job page and job detail, cached 5 minutes), `GET /api/v1/categories` (Add new screen, cached 1 day).

Demo mode makes none of these requests.

The Jobly address can be changed in settings (testing); the server-side base can be overridden with the `JOBLY_API_BASE` constant in wp-config.php.

== Installation ==

1. Upload the `jobly-integration` folder to `/wp-content/plugins/` and activate it.
2. Open Jobly HRM, Settings, Connection; paste the API key and save.
3. Visit Settings, Permalinks once if `/kariera/` shows a 404 (a permalink structure other than "Plain" is required).

== Screenshots ==

1. Overview: stat cards, setup checklist, latest applications and jobs.
2. Setup wizard: connect your company.
3. Setup wizard: choose the careers page.
4. Jobs list with status badges.
5. Job detail: details, applications, links, embed codes.
6. Settings: Google for Jobs.
7. Careers page with search and filters.
8. Job page with sticky summary and application form.
9. Job list block in the editor.

== Changelog ==

= 0.3.0 =
* Branded admin: header, section tabs, dashboard with stats, checklist and panels, two-column job detail, copy buttons, empty states, Help tab, footer.
* Setup wizard (redirects once after activation, skippable).
* Careers page: search, filters, cards, pagination; job page with breadcrumb, chips, sticky summary.
* Google for Jobs: JobPosting JSON-LD and core sitemap provider (optional).
* Blocks: job list and application form. Optional own careers page (`[jobly_jobs]`).
* Theme overrides (`yourtheme/jobly/`), hooks, en_US translation, release workflow.
* Company, job content and categories read via the API key; `App-Token` support.
* Requires WordPress 6.1.


= 0.2.0 =
* Admin: Overview, Jobs (list + detail), Add new, Applications (list + detail), Settings (Connection, Display, Demo).
* API key connection with verification; demo turns off when a key is verified.
* Careers pages: `/kariera/` and `/kariera/{slug}/`, `[jobly_jobs]` shortcode.
* `JOBLY_API_BASE` constant, translations template, coding-standards tooling and CI.

= 0.1.0 =
* `[jobly]` shortcode and settings page.
