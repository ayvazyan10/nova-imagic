# Release process

Imagic 2.0.0 is a major release. Do not tag it until the licensed compatibility
matrix and the manual Nova smoke checks below are green.

## One-time repository setup

Add these encrypted GitHub Actions secrets:

- `NOVA_USERNAME`
- `NOVA_LICENSE_KEY`

They are used only to authenticate Composer against Nova's private package
repository. Pull requests from forks do not receive these secrets, so their
licensed matrix is allowed to skip while the always-on metadata, PHP syntax, and
NPM lock checks still run.

## Release checklist

1. Confirm the CI matrix passes PHP 8.1–8.4, Laravel 9–12, Nova 4–5, and
   Intervention Image 2.7/3.11 combinations.
2. Run `composer audit` against the resolved release dependencies and
   `npm audit --omit=dev`.
3. Run `npm run production` and confirm `git diff --exit-code -- dist`.
4. In both Nova 4 and Nova 5, smoke-test:
   - single and multiple upload, preview, reorder, replace, and removal;
   - live crop and every server transformation used in the README;
   - local, S3, public, and private storage;
   - media picker reload from stable `media:<uuid>` references;
   - manager search, sort, filters, pagination, nested folders, rename, move,
     empty-folder deletion, and guarded bulk deletion;
   - owner isolation with two Nova users and an application authorization gate;
   - mobile layout, keyboard operation, light/dark themes, and error states.
5. Test the documented 1.3-to-2.0 upgrade against a database containing scalar,
   JSON, and legacy URL/path field values.
6. Confirm `CHANGELOG.md` contains the release version/date, a fresh empty
   `Unreleased` section, and correct comparison links.
7. Ensure the working tree contains no Composer credentials, Nova source,
   customer media, logs, caches, or unrelated generated files.
8. Tag `v2.0.0` and publish the GitHub release using
   `.github/release-notes/v2.0.0.md`, then verify Packagist and a clean install.
9. Close issues only after linking the released version. The 2.0 release should
   supersede, rather than merge, any older partial S3 URL patch.

## Rollback preparation

Before releasing, preserve a database and object-storage backup from the upgrade
fixture. The migration is additive, but 2.0 media references and catalog rows
are not understood by 1.x. See [UPGRADE.md](UPGRADE.md) for the safe rollback
boundary.
