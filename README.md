# AIS Career (ais_career)

TYPO3 v13 LTS Extbase/Fluid extension for job listings and applications.

## Features
- Job list with filters and world map
- Job detail with optional application form
- Email notifications and optional application persistence
- Clean routing with slug-based URLs

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
  applicationEnabled = 1
  applicationToEmail = hr@example.com
  applicationFromEmail = no-reply@example.com
  maxUploadSizeMB = 5
  allowedExtensions = pdf,doc,docx
  privacyPageUid = 123
}

page.includeCSS.aiscareer = EXT:ais_career/Resources/Public/Css/aiscareer.css
page.includeJSFooter.aiscareer = EXT:ais_career/Resources/Public/JavaScript/aiscareer.js
```

## World Map
`Resources/Public/Images/worldmap.svg` is bundled. Add `data-country` attributes to SVG paths for clickable filters, e.g. `data-country="DE"`.

## Backend
- Records are managed in the List module.
- Use `Job` records under a storage page.

## Screenshots
- Listing: `Resources/Public/Images/placeholder-listing.png`
- Detail: `Resources/Public/Images/placeholder-detail.png`
