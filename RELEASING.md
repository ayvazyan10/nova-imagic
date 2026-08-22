# Release process

Imagic 2.0.0 is a major release. Do not tag it until the current
security-maintained lane, the licensed compatibility checks, and the manual Nova
smoke checks below are green.

## One-time repository setup

Add these encrypted GitHub Actions secrets:

- `NOVA_USERNAME`
- `NOVA_LICENSE_KEY`

They are used only to authenticate Composer against Nova's private package
repository. Pull requests from forks do not receive these secrets, so their
licensed matrix is allowed to skip while the always-on metadata, PHP syntax, and
NPM lock checks still run.

## Release checklist

1. Confirm the current security gate passes on Laravel 12, Nova 5, Intervention
   Image 3, and the PHP 8.2+ runtime baseline. The CI representative uses PHP
   8.4 and must pass dependency resolution with Composer's default advisory
   blocking, `composer audit --locked`, PHPUnit, the production asset build, and
   committed-dist verification.
2. Confirm the explicitly labeled EOL compatibility rows complete their required
   PHPUnit runs and configured asset/dist checks. Their command-scoped CI
   override and non-blocking audit result exist only to detect Imagic regressions;
   a green legacy row is not evidence of secure framework support.
3. Review `composer audit` for the resolved current Laravel 12 dependencies and
   run `npm audit --omit=dev`. Current-lane Composer advisories are release
   blockers; legacy-lane advisories must remain visible in the workflow output.
4. Run `npm run production` and confirm `git diff --exit-code -- dist`.
5. In both Nova 4 and Nova 5, smoke-test:
   - single and multiple upload, preview, reorder, replace, and removal;
   - live crop and every server transformation used in the README;
   - local, S3, public, and private storage;
   - media picker reload from stable `media:<uuid>` references;
   - manager search, sort, filters, pagination, nested folders, rename, move,
     empty-folder deletion, and guarded bulk deletion;
   - owner isolation with two Nova users and an application authorization gate;
   - mobile layout, keyboard operation, light/dark themes, and error states.
6. Test the documented 1.3-to-2.0 upgrade against a database containing scalar,
   JSON, and legacy URL/path field values.
7. Confirm `CHANGELOG.md` contains the release version/date, a fresh empty
   `Unreleased` section, and correct comparison links.
8. Ensure the working tree contains no Composer credentials, Nova source,
   customer media, logs, caches, or unrelated generated files.
9. Tag `v2.0.0` and publish the GitHub release using
   `.github/release-notes/v2.0.0.md`, then verify Packagist and a clean install.
10. Close issues only after linking the released version. The 2.0 release should
   supersede, rather than merge, any older partial S3 URL patch.

> [!WARNING]
> Composer may refuse a fresh legacy resolution because active framework
> advisories have no patched release on those lines. Do not disable Composer
> security blocking in a production application to install Imagic. Upgrade the
> application stack; the CI-only legacy override exists solely to detect Imagic
> regressions.

## Rollback preparation

Before releasing, preserve a database and object-storage backup from the upgrade
fixture. The migration is additive, but 2.0 media references and catalog rows
are not understood by 1.x. See [UPGRADE.md](UPGRADE.md) for the safe rollback
boundary.
