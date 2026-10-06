# Jobly.si — WordPress integration

WordPress plugin that embeds the Jobly.si application form or a company's whole careers page with a `[jobly]` shortcode. It wraps Jobly's existing iframe embed (`/embed/companies/{slug}`, `/embed/jobs/{slug}`); applicant data never touches WordPress.

The plugin lives in [`jobly-integration/`](jobly-integration) — zip that folder to install. Usage: [`jobly-integration/readme.txt`](jobly-integration/readme.txt).

```
[jobly]                                  # company from Settings → Jobly.si
[jobly job="slug-oglasa" show="full"]    # one job ad with the form
[jobly filter="title,benefits" accent="#2563eb" height="900"]
```

Build a release zip: `cd jobly-integration/.. && zip -r jobly-integration.zip jobly-integration`

License: GPL-2.0-or-later.
