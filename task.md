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

## Phase 13: Historical Data Entry
- [ ] Transcribe 73 register photos in blood-doner-details/
- [ ] Review pass on flagged/ambiguous rows
- [ ] Import into donors table
