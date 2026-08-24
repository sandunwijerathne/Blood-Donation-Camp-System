# Blood Donor Management System — Task Tracker

## Phase 1: Core Infrastructure
- [x] Database SQL schema
- [x] config.php
- [x] composer.json
- [x] .htaccess
- [x] includes/db.php
- [x] includes/auth.php
- [x] includes/functions.php
- [x] includes/header.php
- [x] includes/footer.php

## Phase 2: Assets
- [x] assets/css/style.css
- [x] assets/js/app.js

## Phase 3: Authentication
- [x] login.php
- [x] logout.php
- [x] index.php
- [x] ajax/login.php

## Phase 4: Dashboard
- [x] admin/dashboard.php

## Phase 5: Donor Management
- [x] admin/donors.php
- [x] admin/donor-add.php
- [x] admin/donor-edit.php
- [x] ajax/donor-save.php
- [x] ajax/donor-delete.php
- [x] ajax/donor-list.php
- [x] ajax/donor-status.php
- [x] ajax/donor-import.php
- [x] ajax/donor-export.php

## Phase 6: Blood Camp Management
- [x] admin/camps.php
- [x] ajax/camp-save.php
- [x] ajax/camp-delete.php
- [x] ajax/camp-list.php

## Phase 7: Messaging Module
- [x] admin/messages.php
- [x] admin/templates.php
- [x] admin/emergency.php
- [x] ajax/send-whatsapp.php
- [x] ajax/send-sms.php
- [x] ajax/message-log.php
- [x] ajax/template-save.php
- [x] ajax/template-delete.php
- [x] ajax/template-list.php

## Phase 8: Reports
- [x] admin/reports.php
- [x] ajax/report-data.php
- [x] ajax/report-export.php

## Phase 9: Settings
- [x] admin/settings.php
- [x] ajax/settings-save.php

## Phase 10: Verification
- [x] PHP syntax check all files
- [x] Browser test
- [x] Composer install

## Phase 11: Camp Register (attendance by T.P. number)
The T.P. (mobile) number is the unique identifier for a person, matching
the paper register book. `donors`.`mobile` was already UNIQUE, so no
change to the donors table was needed.

- [x] migration-camp-register.sql (`camp_registrations` table)
- [x] database.sql updated for fresh installs
- [x] `normalizeMobile()` + `findDonorByMobile()` in includes/functions.php
- [x] ajax/registration-lookup.php (T.P. -> known donor / walk-in / already in)
- [x] ajax/registration-save.php (mark in, quick-add walk-ins, edit)
- [x] ajax/registration-list.php (server-side DataTable + per-camp tallies)
- [x] ajax/registration-delete.php
- [x] ajax/registration-export.php (Excel/CSV laid out like the book)
- [x] admin/camp-register.php (register desk UI)
- [x] Nav link + per-camp "Register" button on admin/camps.php
- [x] Tested: duplicate T.P. blocked across formats, serial numbering,
      Sinhala text, "Donated" stamps donor's last donation date

## Phase 12: Bug Fixes
- [x] config.php re-entry guard - Composer's `autoload.files` re-included
      config.php with a plain `require`, bypassing `require_once`. The
      resulting "Constant already defined" warnings were being written
      into binary downloads, corrupting every Excel export
      (donor-export, report-export, registration-export).
- [x] donor-import.php now uses normalizeMobile(), so an imported T.P.
      matches what the camp register stores (previously a "+94..." import
      would not match a "07..." lookup and silently created a duplicate).
- [x] donor-import.php rejects names that arrive as literal "?" - the
      signature of a CSV re-saved in the Windows ANSI codepage, which
      cannot represent Sinhala.

## Phase 13: Historical Data Entry
- [x] 73 register photos rotated upright (originals are sideways)
- [x] Pages 1-8 transcribed -> parts/p01-p08 + supplement
- [x] Pages 53-54 transcribed -> parts/p53, p54
- [x] 488 donors imported and verified (0 mangled, 0 malformed T.P.)
- [ ] Pages 9-52: fragment CSVs were never committed and are lost. The
      DONOR DATA itself is safe in the database (see donors-backup CSV);
      only the per-page working files are gone.
- [ ] Pages 55-73: not yet transcribed (~19 pages)
- [ ] ~150 no-blood-group contacts from pages 1-52 need re-transcribing

## Phase 14: Data Loss Incident (2026-08-15) - RESOLVED
Commit 915d091 "Delete blood-doner-details directory" removed 160 files.
Recovered from git history:
- [x] 73 original photos restored (4000x2252, 194.9 MB)
- [x] parts/p01-p08 + supplement + PROGRESS.md restored
- [x] Backup copies kept at Documents\blood-donor-images-backup\
- [x] 10 donor names destroyed by an Excel CSV round-trip, repaired from
      source fragments (repair-mangled-names.php)
- [x] 10 fabricated "O+" blood groups cleared back to blank

## Phase 15: WhatsApp Template Support
WhatsApp only delivers free-form text inside the 24-hour window opened
when a donor messages first. Announcing a camp is business-initiated and
must use a template Meta approved in advance, so the sender now supports
both modes.

- [x] migration-whatsapp-templates.sql - adds whatsapp_template_name,
      whatsapp_language, whatsapp_variables to message_templates
- [x] database.sql updated for fresh installs
- [x] buildTemplatePayload() maps named placeholders ({NAME}, {DATE}...)
      onto Meta's numbered {{1}},{{2}},... via the variable order
- [x] send-whatsapp.php sends either a template or free text; blank
      parameters are replaced with "-" because Meta rejects empty ones
- [x] Templates page captures the Meta template name, language and
      variable order, validated (lowercase/underscore names only)
- [x] Messages page: delivery-mode toggle, warns that free text only
      reaches donors who replied within 24h, blocks sending a template
      that has no Meta name set
- [x] Settings "Test WhatsApp" now sends the pre-approved `hello_world`
      template instead of free text, so it works as a real smoke test,
      and explains 401 / recipient-allowlist / missing-template errors
- [x] Tested: payload shape, variable ordering, Sinhala parameters,
      static templates, and all endpoint validation paths

## Phase 16: Bug Fixes
- [x] **config.php: APP_DEBUG set to `false`** - was leaking database
      errors and stack traces to whoever triggered them

## Phase 17: Admin Account Management
- [x] ajax/account-save.php - change display name, login email, password
- [x] Current password required for ANY change on the card, so an
      unlocked browser cannot be used to take the account over
- [x] Password rules: min 10 chars, must match confirmation, must differ
      from the current one; email must stay unique
- [x] session_regenerate_id(true) on password change, so a stolen
      session id stops working
- [x] Settings page shows a warning while still on admin@admin.com
- [x] Tested: 6 rejection paths, name/email-only save leaves the hash
      untouched, password change verified through the real login endpoint
      (new password works, old one refused)

## Phase 18: Bug Fixes (round 2)
- [x] **donor-save.php did not normalise the T.P. number.** A donor added
      by hand as "077 821 1176" was stored with the spaces, so the camp
      register's "0778211176" lookup missed and created a second copy of
      the same person. The Add/Edit form now normalises mobile and
      WhatsApp, and duplicate detection catches the same person entered
      as 077..., +94... or with spaces.
- [x] **emergency.php would have failed outright** - it posted to the
      WhatsApp sender without send_mode/template_id, and template is now
      the default. Added a template picker (Emergency Request first),
      client-side guards, and it passes send_mode. This was a regression
      introduced by the Phase 15 template work.
- [x] **report-data.php returned a 9th, nameless blood group** for the
      donors whose group was never recorded, which would have rendered as
      a blank pie slice. Chart is back to the eight real groups and the
      unknowns are reported as summary.unknown_blood_group.
- [x] **saveSetting() left a stale value in getSetting()'s static cache**,
      so a setting read back later in the same request was the old one.
- [x] **donor-status.php reported "Donor not found"** when a donor was set
      to the status they already had, because MySQL returns 0 affected
      rows for a no-op UPDATE. Existence is now checked separately.

## Phase 19: Bug Fixes (round 3)
- [x] **Settings test button broke on a real number.** The test sent
      Meta's `hello_world`, which is only allowed from Meta's public test
      numbers - once the org's own number was registered it failed with
      error 131058. The test panel now has a template picker listing the
      templates configured on the Templates page, and 131058 / missing
      template / missing payment errors are explained in plain words.
      (Regression from Phase 15.)
- [x] **Settings page rendered secrets into the page HTML.** The WhatsApp
      token and SMS secret were output as input values, so the live token
      was readable in View Source, DevTools, and anything that scraped the
      page. Both fields now render empty with a "saved" hint, and a blank
      box on save means "keep the stored value" rather than erasing it.

## Phase 19b: Settings page fatal error
- [x] The WhatsApp test-template picker called `$db->query()`, but
      settings.php never defines `$db` (it uses getSetting() helpers).
      That fatal killed the page mid-render, so Save Settings, Test
      WhatsApp, Test SMS and Update Account all vanished. Changed to
      getDB()->query(). All 11 admin pages now render fully with no
      errors - checked by rendering each one.

## Phase 20: OUTSTANDING
- [ ] **Set your own admin email + password** on Settings > Admin Account.
      The UI is ready; only you can choose the password.
- [ ] Create and get approval for the actual WhatsApp templates in
      WhatsApp Manager (`blood_camp_notification`, `emergency_blood_request`,
      `general_announcement`), then confirm the names on the Templates page
- [ ] Saved WhatsApp token is only 32 characters - real Meta tokens start
      "EAA" and run 200+ chars, so the current value is a placeholder
- [ ] Decide whether blood-doner-details/ (195 MB of donor photos) belongs
      in git - currently untracked, and these are personal health records
- [ ] Pages 55-73 not transcribed; ~150 no-blood-group contacts from
      pages 1-52 need re-transcribing
- [ ] No automated tests
- [ ] SMS (Dialog/Mobitel) not implemented - deliberately deferred,
      WhatsApp only for now

## Phase 21: Camp Budget, Donations and Expenses
People bring food, soft drinks and water bottles to a camp for the
donors, and the organisers spend money running it. Both are now tracked
per camp, but deliberately kept apart: donated GOODS are reported at an
estimated value on their own line and never folded into the cash
balance, because a hundred water bottles are not money the treasurer
can spend.

- [x] migration-camp-finance.sql - `camp_contributions`, `camp_expenses`,
      `blood_camps.budget_amount`, `currency_symbol` setting. The column
      add is guarded through information_schema so the file can be re-run
      (MySQL has no "ADD COLUMN IF NOT EXISTS")
- [x] database.sql updated for fresh installs
- [x] functions.php: formatMoney(), getCampFinanceSummary(),
      campContributionCategories(), campExpenseCategories(),
      campPaymentMethods(), contributionCategoryIcon()
- [x] admin/camp-finance.php - camp selector, six live money cards,
      budget planner with a paid/committed progress bar, and two tabs:
      Donations Received and Expenses
- [x] Contribution form changes shape by category: Cash wants an exact
      amount and hides the item lines; goods want an item, quantity and
      unit with only an optional estimated value
- [x] ajax/contribution-{save,list,delete}.php
- [x] ajax/expense-{save,list,delete}.php
- [x] ajax/camp-budget-save.php - blank box clears the budget rather than
      storing zero, so "no budget planned" stays distinct from "budgeted
      nothing"
- [x] ajax/camp-finance-export.php - three-sheet Excel workbook (Summary,
      Contributions, Expenses) or single-section CSV with a BOM so Excel
      reads Sinhala names
- [x] camps.php: Planned Budget field on the camp modal and a per-camp
      budget button on each row; camp-save.php persists it
- [x] Settings > General: Currency Symbol (defaults to Rs.)
- [x] Sidebar: "Budget & Donations" under Management
- [x] Tested end to end against the live database: every save, update,
      delete and validation path, the summary arithmetic, the filtered
      lists, and the exported workbook read back sheet by sheet. Test
      rows were removed afterwards - the tables ship empty.

## Phase 22: Bug Fixes (round 4)
- [x] **Every positive blood group rendered invisible.** The badge rules
      were written as `.badge-blood.A\+, .badge-blood[data-blood="A+"]`.
      In CSS `+` is the adjacent-sibling combinator, so the class half
      was an invalid selector - and one invalid selector in a
      comma-separated list voids the entire rule, taking the working
      attribute selector down with it. A+, B+, AB+ and O+ therefore had
      no background at all, and since `.badge-blood` sets `color: #fff`
      they were white text on a white table. The negative groups were
      unaffected, because an escaped `-` is a legal class character - so
      the bug hid on the ~4% of donors who are Rh-negative while
      affecting 460 of 489 records. Confirmed in the browser: only 5 of
      the 9 rules were being parsed. Class selectors removed (the markup
      only ever used `data-blood`), and `.badge-blood` now carries a
      neutral fallback background so no badge can go white-on-white again.
- [x] Donors table and the Reports eligible-donor list now show a dash
      for the ~10 donors transcribed with no blood group, instead of an
      empty badge.
