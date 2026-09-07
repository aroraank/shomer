# Shomer

Shomer (שומר) means watchman. It is a WordPress security plugin that answers a simple question: did this site's trust surface change, and did a human authorise that change?

It is not a signature antivirus. It will not replace Wordfence or Patchstack. Install it beside them. Those tools hunt known bad. Shomer learns what looks normal on *your* site and flags when that drifts.

## What it does today (0.1.0)

Foundation plus the first hardening probes:

- Custom tables for findings, an append only event log, snapshots, and custody events
- A hash chained log so silent edits to history are harder to hide
- An admin dashboard that reports verified / failed / untestable counts honestly (it never says "your site is secure")
- A resumable scan engine
- Probe: PHP execution inside uploads (canary write, request, delete)
- Probe: unauthenticated REST user listing
- An HTTP allowlist so outbound calls only go to `api.wordpress.org` or your own site
- A self audit that greps the plugin source for forbidden unauthenticated hooks

## What is coming next

- More hardening probes from the WordPress hardening handbook
- Persistence hunter (mu plugins, weird PHP in uploads, core file integrity)
- Credential custody for Application Passwords and surprise admin users
- Trust surface snapshots before and after updates

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer

## Install

1. Clone or copy this folder into `wp-content/plugins/shomer`
2. Activate Shomer under Plugins in wp-admin
3. Open the Shomer menu
4. Click Scan now once to confirm the dummy finding appears

## Develop

Smoke tests (no full WordPress bootstrap required for most of them):

```bash
php tests/run-smoke.php
```

Coding style matches wordpress.org plugins: plain PHP classes, `require_once`, no Composer `vendor` folder in the shipped plugin.

## Security rules for this plugin

See [SECURITY.md](SECURITY.md). Short version: no unauthenticated admin endpoints, no vendor phone home, no eval, fail closed when a probe cannot run.

## License

GPLv2 or later.
