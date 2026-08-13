# Transcription Progress

Source images: `blood-doner-details/rotated/pNN_*.jpg` (73 pages, already rotated upright).
Each page transcribed into `parts/pNN.csv`, then combined with:

```bash
php merge-parts.php
```

## Fragment CSV column order (no header row in fragments)

`Name, Mobile, WhatsApp, Email, Address, Blood Group, Gender, Date Of Birth, Last Donation Date`

- Leave **Blood Group** blank when the register leaves it blank — the merge
  routes those to `donors-missing-bloodgroup.csv` rather than dropping them.
- **Last Donation Date** = the camp date written at the top of that book.
- Mobile and WhatsApp are the same T.P. number (10 digits, leading 0).

## Books / camp dates identified so far

| Pages | Rows | Camp date |
|-------|------|-----------|
| p01–p06 | 1–144 | 2025-01-14 |
| p07–?   | restarts at 01 | 2025-05-25 |

## Pages completed

- [x] p01 (rows 1–23)
- [x] p02 (rows 24–55)
- [x] p03 (rows 56–87)
- [x] p04 (rows 88–117)
- [x] p05 (rows 118–136)
- [x] p06 (rows 137–144, end of book 1)
- [x] p07 (rows 1–32, book 2 starts, 2025-05-25)
- [ ] p08 … p73  ← NEXT: p08

## Notes

- Roughly 1/3 of register rows have no blood group written. Those are kept
  as contacts in `donors-missing-bloodgroup.csv`.
- Repeat donors appear across camps (same T.P. in multiple books). The merge
  keeps one record per T.P., preferring the entry that has a blood group and
  the most recent camp date.
- Rows with a ditto mark instead of a T.P. number cannot be imported at all
  (no unique key) and are skipped.
