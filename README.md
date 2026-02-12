# AIS Career (ais_career)

TYPO3 v13 LTS Extbase/Fluid extension for job listings and applications.

## Features
- Job list with filters and interactive world map
- Job detail with optional application form and inline share actions
- Email notifications and optional application persistence
- HTML email notifications
- Inline form validation + basic anti-bot checks
- Application draft autosave (browser local storage) with configurable expiry
- Structured salary fields (min/max/currency/period) with frontend output + JobPosting JSON-LD
- Clean routing with slug-based URLs
- AJAX filtering without page reload
- Backend analytics with tabs, share tracking, and CSV exports

## Installation
1. Add the package to your project (local path or VCS):
   - `composer req aistea/ais-career`
2. Install extension in TYPO3 backend.
3. Include TypoScript: `AIS Career`.

## Plugins
- **AIS Career: Job List** (`AisCareer` / `JobList`)
- **AIS Career: Job Detail** (`AisCareer` / `JobDetail`)

## Routing
Add the route enhancer to your site config (or include the file):

```
routeEnhancers:
  AisCareerJobDetail:
    type: Extbase
    extension: AisCareer
    plugin: JobDetail
    routes:
      - routePath: '/jobs/{job-slug}'
        _controller: 'Job::show'
        _arguments:
          job-slug: job
    defaultController: 'Job::show'
    aspects:
      job-slug:
        type: PersistedAliasMapper
        tableName: tx_aiscareer_domain_model_job
        routeFieldName: slug
```

## TypoScript (example)
```
plugin.tx_aiscareer.settings {
  itemsPerPage = 12
  enableFilters = 1
  mapEnabled = 1
  listPid = 123
  detailPid = 124
  applicationEnabled = 1
  applicationToEmail = hr@example.com
  applicationFromEmail = no-reply@example.com
  applicationDraftExpiryDays = 7
  maxUploadSizeMB = 5
  maxTotalUploadSizeMB = 10
  allowedExtensions = pdf,png,jpg,jpeg
  privacyPageUid = 123

  botMinSeconds = 3
  botMaxSeconds = 86400
  botRateLimit = 5
  botRateWindowSeconds = 3600
  botRequireHeaders = 0

  gdprPurgeDays = 180
}

page.includeCSS.aiscareer = EXT:ais_career/Resources/Public/Css/aiscareer.css
page.includeJSFooter.aiscareer = EXT:ais_career/Resources/Public/JavaScript/aiscareer.js
```

## GDPR Purge (Scheduler)
You can purge old applications and their upload folders via CLI:

```bash
vendor/bin/typo3 aiscareer:purge-applications --days=180
```

To run this automatically with the TYPO3 Scheduler:
1. Backend: **System > Scheduler**
2. Add new task: **Execute console commands**
3. Command: `aiscareer:purge-applications --days=180`
4. Set an interval (e.g. daily)
5. Save and run the scheduler

## FlexForms
**Job List**
- `itemsPerPage`
- `listPid` (list page UID)
- `detailPid` (detail page UID)
- `mapEnabled`
- `enableFilters` + individual filter toggles

**Job Detail**
- `applicationEnabled`
- `applicationToEmail`
- `applicationFromEmail`
- `applicationDraftExpiryDays`
- `maxUploadSizeMB`
- `allowedExtensions`
- `privacyPageUid`
- `contactDefault*` fallback fields

## Salary Fields
Job records support:
- `salary_min`
- `salary_max`
- `salary_currency` (e.g. `EUR`)
- `salary_period` (`hour`, `day`, `week`, `month`, `year`)

The values are shown on list/detail pages and included in JobPosting JSON-LD (`baseSalary`) when present.

## Share Tracking
Detail page share actions (Copy, Email, LinkedIn, WhatsApp, X) are tracked as analytics events:
- `share_copy`
- `share_email`
- `share_linkedin`
- `share_whatsapp`
- `share_x`

In Backend Analytics:
- “Shares by channel” card (mini bars)
- “Shares by job” table
- CSV export buttons for shares/jobs/funnel tables

## Anti-bot (internal)
The form includes an internal anti-bot layer (honeypot, timing window, rate limiting).
Tune via TypoScript:
- `botMinSeconds` (0 disables timing check)
- `botMaxSeconds` (0 disables max age)
- `botRateLimit` + `botRateWindowSeconds`
- `botRequireHeaders` (1 enables basic header check)

## i18n
Backend and frontend labels are available in English and German:
- `Resources/Private/Language/locallang*.xlf`

## World Map
`Resources/Public/Images/worldmap.svg` is bundled and rendered via `<object>`. Countries are highlighted and clickable if their ISO‑2 code (e.g. `DE`) exists as an `id` on a `<g>` (or `<path>`) inside the SVG. Jobs should store ISO‑2 codes in the `country` field.

## Backend
- Records are managed in the List module.
- Use `Job` records under a storage page.

## Screenshots
- Listing: `Resources/Public/Images/placeholder-listing.png`
- Detail: `Resources/Public/Images/placeholder-detail.png`
