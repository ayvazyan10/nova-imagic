# Contributing

Thank you for helping improve Imagic. Bug fixes, compatibility updates,
documentation, and focused feature work are welcome.

## Before opening an issue

- Search the existing issues and release notes.
- Use GitHub Discussions or a support channel for application-specific setup
  questions, when one is available.
- Report security vulnerabilities privately as described in
  [SECURITY.md](SECURITY.md).

For a bug report, include the smallest reproducible example and the exact PHP,
Laravel, Nova, filesystem driver, image driver, browser, and Imagic versions.
Storage bugs should say whether the disk is local, S3, or S3-compatible, but
must not include credentials, signed URLs, or private object names.

## Development setup

This package is a Nova field and therefore needs access to a licensed Nova
installation for the full PHP test suite and frontend build. The frontend build
expects the checkout at `nova-components/Imagic` inside a Nova application and
Nova at that application's `vendor/laravel/nova` path.

```bash
composer install
npm ci
```

Do not commit Composer authentication files or Nova credentials. See the
[official Nova CI authentication guidance](https://nova.laravel.com/docs/v5/installation#authenticating-nova-in-ci-environments)
for local and CI setup. The repository workflow expects encrypted
`NOVA_USERNAME` and `NOVA_LICENSE_KEY` secrets; without them it runs the public
metadata, syntax, and dependency-lock checks but skips the licensed matrix.

## Quality checks

Run the same checks used by CI before submitting a pull request:

```bash
composer validate --strict
composer test
npm run production
git diff --exit-code -- dist
```

The last command confirms that the compiled assets committed in `dist/` match
the source assets. Add tests only when they protect changed behavior or a
meaningful regression. Storage changes should normally cover both Laravel's
fake local storage and behavior that does not rely on a local filesystem path.

## Pull requests

- Keep each pull request focused and explain the user-visible behavior.
- Add a changelog entry under `Unreleased`.
- Update the README and upgrade guide for public API or behavior changes.
- Preserve backward compatibility within a major version.
- Include regression coverage for bug fixes and risk-sensitive upload,
  authorization, or deletion behavior.
- Never commit credentials, customer media, Nova source code, or generated test
  artifacts.

Imagic follows [Semantic Versioning](https://semver.org/). Breaking changes are
reserved for major releases. Maintainers should follow
[RELEASING.md](RELEASING.md) for the licensed matrix and release checklist.
