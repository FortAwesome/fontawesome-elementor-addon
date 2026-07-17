# "Kit zip file is not readable" error — trace & causes

This documents the error a customer saw after clicking **Setup Kit** in the admin
interface on `0.1.0-alpha2`, reported roughly as *"cannot read kit zip file."*

## The error

The customer-facing message is a paraphrase of this exact string:

> **"The Font Awesome kit zip file is not readable."**

It is raised with error code `fontawesome_invalid_zip_file` in the vendored Font
Awesome library:

- **File:** `plugin/vendor/fortawesome/wordpress-fontawesome-lib/src/Kit_Download.php`
- **Lines:** 574–586, inside the private `prepare_selfhosting()` method

```php
$zip = new \ZipArchive();
$dirs_for_extraction = [ 'css', 'webfonts', 'metadata' ];

if (
    ! $wp_filesystem->is_readable( $zip_file_path ) ||   // check A
    $zip->open( $zip_file_path ) !== true                 // check B
) {
    return new WP_Error(
        'fontawesome_invalid_zip_file',
        __( 'The Font Awesome kit zip file is not readable.', ... ),
        [ 'zip_file_path' => $zip_file_path ],
    );
}
```

Here `$zip_file_path` is `{temp_dir}/kit.zip` (defined at `Kit_Download.php:559`).

> **Note:** There are two copies of this vendored file — `plugin/vendor/...` and
> `plugin/wp-dist/vendor/...`. They are identical for this code. The copy active at
> runtime is `plugin/vendor/`.

## How it reaches the admin UI

The call chain triggered by the **Setup Kit** button:

1. `Setup_Kit` AJAX handler → `$kit_download->download_and_prepare_selfhosting(...)`
   — `plugin/includes/Setup_Kit.php:211`
2. → `download_and_prepare_selfhosting()` calls `self::prepare_selfhosting(...)`
   — `Kit_Download.php:485`
3. → `prepare_selfhosting()` returns the `fontawesome_invalid_zip_file` `WP_Error`
4. Back in `Setup_Kit.php:213–222`, the addon wraps it with its own message
   ("Font Awesome Elementor Addon was unable to download and prepare the Font Awesome
   Kit for self-hosting.") and calls `self::send_error( ..., 500 )`, which is what
   surfaced in the admin interface via the newly added diagnostics.

## Conditions that produce it

The error fires when **either** check fails. This method runs *after* `download()`
has already verified the file exists and has non-zero size
(`Kit_Download.php:397–408`), so the zip was downloaded successfully. That narrows the
likely causes.

### Check A — `is_readable()` returns false

- File permission / ownership problems on the temp directory. The temp file is created
  by the web-server user during download; if something (a restrictive umask, an
  SELinux/AppArmor policy, or a hardened host) makes it non-readable back to PHP, this
  trips.
- `open_basedir` restrictions that exclude the system temp dir (`get_temp_dir()`,
  filterable via the `fontawesome_lib_temp_dir` filter) — PHP then can't stat/read the
  path.

### Check B — `ZipArchive::open()` doesn't return `true`

- **The downloaded "zip" isn't actually a valid zip.** This is the most common
  real-world cause. `download()` only checks HTTP 200 + non-empty file — it does **not**
  validate the content. So if the kit-download URL returned a 200 with an HTML error
  page, a JSON error body, an auth/redirect/captcha/CDN interstitial page, or a
  truncated response, you get a non-empty file that isn't a zip, and `open()` fails with
  a corrupt/format error.
- A partially-written or truncated download (connection dropped after headers) that
  still passed the size > 0 check.
- Disk full / quota during the streamed write, leaving a corrupt archive.

## Diagnostic gap worth closing

`ZipArchive::open()` returns an int error code (not `false`) on failure, but the code
only surfaces a generic "not readable" message — the specific reason (e.g.
`ZipArchive::ER_NOZIP` for "not a zip archive" vs. a permission error) is discarded.

Given the diagnostics were just added, the highest-value follow-up is to capture the
`open()` return code and the file's first bytes into the `WP_Error` data. In practice,
**check B failing on a non-zip response body** (a masked auth/API error delivered as
HTTP 200) is the most probable cause here — not a genuine filesystem-permission issue.
