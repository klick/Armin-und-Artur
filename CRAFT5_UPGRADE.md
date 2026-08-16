# Craft 5 Upgrade Notes

This branch was upgraded locally to Craft CMS 5.10.13.2 on PHP 8.2 and MySQL 8.0.40. The local target lock has no Composer security advisories.

## Redirect Manager legacy table

The old Redirect Manager migration created the catch-all URL table with the malformed name `craft_dolphiq_redirects_catch_all_urlscraft_`. Redirect Manager 5.1.1 expects `craft_dolphiq_redirects_catch_all_urls`, so its Craft 5 migration otherwise fails while adding 404 analytics tables.

Before a future deployment runs `php craft up`, take a database backup and verify the table prefix and both table names. If the malformed table exists and the correctly named table does not, rename it before `craft up`:

```sql
RENAME TABLE `craft_dolphiq_redirects_catch_all_urlscraft_`
  TO `craft_dolphiq_redirects_catch_all_urls`;
```

The local upgrade performed this rename after restoring the `pre-craft5-migration` snapshot; the legacy table contained zero rows. Do not assume that is true in another environment—verify before renaming.

## Remaining upgrade follow-up

- `jalendport/craft-preparse` is on its Craft-5 alpha release (`3.0.0-alpha.2`); exercise preparse-dependent content before production.
- Composer reports `dolphiq/redirect` as abandoned, despite its Craft-5 release. Plan a maintained redirect solution or a vendor support decision.
- Craft Update reports CKEditor 5.7.0 as available. This branch deliberately retains the audited Craft-5-compatible CKEditor 4.11.4 constraint; review that major update separately.
- The Craft 5 migration intentionally changes Project Config (including entry-type and field-group representation). Deploy the complete Project Config set atomically; do not omit YAML files.
- The database was already `utf8mb4` with `utf8mb4_unicode_520_ci`, so `craft db/convert-charset` was not run. Confirm the production database's charset/collation before deciding whether it is needed there.

## Story detail layout regression

`templates/geschichten/_entry.twig` must retain the Bootstrap-based story structure used by the deployed stylesheet: `container`, `story`, `row`, and `col-*` classes. The old `content/page/grid/contents` markup is not covered by `styles-90c39af821.css`, resulting in an unstyled full-width detail page even though the stylesheet loads correctly. The Craft-5 template therefore restores the existing production structure while using the Craft-5 section handle `sammlungen` for the optional collection aside.
