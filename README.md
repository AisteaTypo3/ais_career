# AIS Career (`ais_career`)

TYPO3 v13 LTS Extbase/Fluid extension for job listings, applications and GDPR-compliant job alerts.

## Core Features
- Job list with filters and optional world map
- Job detail page with apply form and share actions
- Application persistence + notification email
- Job Alert subscription with double opt-in and one-click unsubscribe
- Dedicated Job Alert page + CTA boxes on list/detail
- Manual backend trigger: send job alerts on job save
- HTML email templates for opt-in + digest
- Backend analytics module and CSV exports

## Installation
1. Require the package (path/VCS/composer as used in your project)
2. Install extension in TYPO3 backend
3. Include static TypoScript: `AIS Career`
4. Run database compare in Install Tool

## Plugins
- `AIS Career: Job List` (`AisCareer` / `JobList`)
- `AIS Career: Job Detail` (`AisCareer` / `JobDetail`)
- `AIS Career: Job Alert` (`AisCareer` / `JobAlert`)

## Job Alert Setup
1. Create a dedicated page for Job Alert form
2. Add plugin `AIS Career: Job Alert` to this page
3. In Job Alert plugin FlexForm set:
- `listPid` (job listing page)
- `jobAlertStoragePid` (where subscribers are stored)
- `privacyPageUid`
- `jobAlertFromEmail` / `jobAlertFromName` (recommended)
4. In Job List + Job Detail plugin set `jobAlertPagePid` to your alert page

Result:
- List/detail render a Job Alert link box
- Subscription records are visible in backend list module (`tx_aiscareer_domain_model_jobalert`)

## Manual Trigger on Job Save
In job record:
- set `Trigger job alert now`
- save record

Behavior:
- dispatch starts immediately (no scheduler required)
- mails go to all confirmed and active subscribers
- toggle is reset automatically
- `alert_triggered_at` is written

Sender resolution (current order):
1. Job Alert plugin FlexForm (`jobAlertFromEmail`, `jobAlertFromName`)
2. TYPO3 defaults (`MAIL.defaultMailFromAddress`, `MAIL.defaultMailFromName`)

If no valid sender email is found, dispatch is skipped and logged.

## Mail / SMTP
The extension uses TYPO3 `MailMessage`. If SMTP is configured in TYPO3 (`MAIL` transport/DSN), mails are sent via SMTP automatically.

## Optional CLI Command
- `vendor/bin/typo3 aiscareer:purge-applications --days=180`

## Routing Example
```yaml
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

## TypoScript (minimal)
```typoscript
plugin.tx_aiscareer.settings {
  listPid = 123
  detailPid = 124
  jobAlertPagePid = 125
  jobAlertStoragePid = 126
  privacyPageUid = 127

  applicationToEmail = hr@example.com
  applicationFromEmail = no-reply@example.com

  jobAlertFromEmail = no-reply@example.com
  jobAlertFromName = AIS Career
}

page.includeCSS.aiscareer = EXT:ais_career/Resources/Public/Css/aiscareer.css
page.includeJSFooter.aiscareer = EXT:ais_career/Resources/Public/JavaScript/aiscareer.js
```

## Data / Tables
- Jobs: `tx_aiscareer_domain_model_job`
- Applications: `tx_aiscareer_domain_model_application`
- Job Alerts: `tx_aiscareer_domain_model_jobalert`

## Notes
- Unsubscribe and alert actions are configured with cacheHash excludes in `ext_localconf.php`.
- Language labels are in `Resources/Private/Language/locallang*.xlf`.
