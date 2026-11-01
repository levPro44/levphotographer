# Configuration

PHP 8.4 and Imagick are required for conversion. Existing GD-only servers can browse but conversion stays disabled; there is no orientation-unsafe GD fallback. AVIF is shown only when Imagick reports support, and actual output is validated during conversion.

`LEVPRO_SITE` points to the directory containing `httpdocs` and `private`. Alternatively `LEVPRO_CONFIG` points to a private PHP file returning absolute `web`, `images`, and `data` paths. No production hostname, SSH address, system username, passwords or session data belong in this repository.

Example private file:

```php
<?php
return [
  'web' => '/srv/example/httpdocs',
  'images' => '/srv/example/httpdocs/images',
  'data' => '/srv/example/private/levpro-photo-manager',
];
```

Defaults: WebP, maximum width 1600, quality 88; allowed widths 1200/1600/1920/2560, quality 50–100. Inputs over 80 megapixels or 100 MiB fail explicitly. Queued groups retain their configured settings. The server limits image processing memory/map/thread use; monitor wall time on staging before production use.

Password authentication uses `data/password.hash` (PHP `password_hash`). Sessions use `data/sessions`. Both stay private. Serve only over HTTPS; session cookies are always Secure. Authentication attempts are rate-limited per direct client IP; reverse-proxy deployments must review the rate-limiting policy without blindly trusting forwarded headers.
