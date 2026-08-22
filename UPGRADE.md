# Upgrading to Imagic 2.0

Imagic 2.0 modernizes image processing and storage and introduces the Nova media
library. Its broad Composer constraints retain EOL migration compatibility, but
production applications should use the security-maintained baseline below.

## Requirements

- Current security-maintained baseline: PHP 8.2 or newer, Laravel 12, Laravel
  Nova 5, and Intervention Image 3.11 or newer
- EOL compatibility/migration only: Laravel 9–11, Nova 4–5, PHP 8.1 where the
  selected framework permits it, and Intervention Image 2.7 or 3.11+
- `fileinfo`, GD or Imagick, plus `mbstring`; EXIF is recommended for automatic
  orientation

Review the exact constraints in `composer.json` before upgrading a production
application.

> [!WARNING]
> Composer may refuse a fresh legacy resolution because active framework
> advisories have no patched release on those lines. Do not disable Composer
> security blocking in a production application to install Imagic. Upgrade the
> application stack; the CI-only legacy override exists solely to detect Imagic
> regressions.

## Upgrade procedure

1. Back up the database and the configured media disk.
2. Upgrade the package and resolve its PHP/Nova/Image dependencies:

   ```bash
   composer require ayvazyan10/nova-imagic:^2.0 --with-all-dependencies
   ```

3. Optionally publish the configuration, review every value, then clear cached
   configuration:

   ```bash
   php artisan vendor:publish --tag=imagic-config
   php artisan config:clear
   ```

4. Run the package migrations. They are loaded automatically while the media
   library is enabled, so publishing them is only necessary when your deployment
   process requires application-owned migration files:

   ```bash
   php artisan vendor:publish --tag=imagic-migrations
   php artisan migrate
   ```

5. Verify upload, preview, replacement, and deletion on every configured disk.
   For a remote disk, test both public and private visibility.

## Breaking changes

### PHP 7 is no longer supported

The 1.x Composer constraint claimed PHP 7 compatibility even though the source
already contained PHP 8-only syntax. Version 2.0 makes the effective requirement
explicit and raises it to PHP 8.1.

### Uploads are private by default

Standalone media-manager uploads use `imagic.visibility`, which defaults to
`private`. Direct field uploads use `imagic.field.visibility`, which inherits the
same private default. This differs from 1.x's effectively public field uploads.
Originals and thumbnails are served to authenticated Nova users through package
endpoints. Changing filesystem visibility changes the stored object's ACL, but
package-generated URLs remain authenticated Nova proxy URLs.

For an intentional public legacy-style field, call both
`->mediaLibrary(false)` and `->visibility('public')`. Review every such use rather
than changing the global default merely to preserve old behavior.

### Object names are opaque

New uploads use collision-resistant generated object names. The user-visible
name remains available in the media catalog, but renaming or moving a catalog
item does not rename its storage object. Do not derive business data from an
Imagic storage path.

### Validation is stricter

Version 2.0 rejects files that exceed configured byte, dimension, pixel, batch,
MIME, or extension limits. Review `config/imagic.php` if existing application
requirements differ from the safe defaults. SVG is deliberately not accepted.

### Deletion is physical

Deleting a managed item removes its original and generated thumbnail. A model
attribute that still contains that media URL will no longer display it. Confirm
references before allowing users to delete shared media.

## Existing field values

Existing scalar URL/path values and multiple-value JSON arrays remain readable.
Comma-separated legacy input is also normalized. Existing files are not moved,
renamed, or automatically inserted into the new media catalog. New managed
values are stored as `media:<uuid>` references (or JSON arrays of references)
and resolved to owner-authorized preview data in Nova.

This means an upgraded field can continue to show its legacy value, but the file
will not appear in the media manager until it is uploaded or explicitly imported
as managed media. Keep the legacy storage location available during the upgrade.

For multiple fields, continue using a `json`, `text`, or `longText` database
column. The field detects Laravel `array`, `json`, `object`, and `collection`
casts and assigns an appropriate value to avoid double encoding. Still test the
complete save/reload cycle with any custom cast.

## Intervention Image 2

The compatibility adapter allows a staged upgrade from Intervention Image 2.7,
but version 2 is no longer maintained upstream. Move to Intervention Image 3 and
run the image transformation suite with the same GD or Imagick driver used in
production.

## Rollback

The media-library migration is additive. Before rolling back application code,
retain both media tables and stored objects until you have confirmed that no
resource depends on media created by 2.0. Do not run the migration `down()` in
production unless the catalog data is backed up and intentionally being removed.
