# Security Policy — Shomer

Shomer (שומר, "watchman") is a WordPress security plugin. A security plugin that is itself insecure is worse than no plugin at all, so this document is not boilerplate: it is the set of hard engineering constraints every line of code in this repository is held to. A pull request that violates any rule below will be rejected regardless of what feature it delivers. **If a feature cannot be built within these constraints, we cut the feature, never the constraint.**

These rules exist because the incidents that motivated this project — an unauthenticated REST file-upload zero-day in a consent plugin, a vendor silently minting itself an admin-equivalent Application Password, and multiple poisoned-update supply-chain attacks — were each caused by a vendor breaking exactly one of them.

## The rules

1. **Zero unauthenticated surface.** No REST route, AJAX action, or `admin_post` handler is reachable without authentication. Every REST route's `permission_callback` requires `manage_options` or a dedicated capability. `__return_true` never appears as a permission callback. An automated self-audit test scans this repository's own source and registered routes and fails CI if this is violated.

2. **Capability + nonce on every state change.** Every action that changes state performs a `current_user_can()` check **and** nonce verification. All output is escaped (`esc_html`, `esc_attr`, `esc_url`). All database queries go through `$wpdb->prepare()`. String-concatenated SQL does not exist in this codebase.

3. **No vendor callback, ever.** No SaaS backchannel, no phone-home, no telemetry, no remotely fetched rules or code, no Application Password ever created for the vendor, no remotely controlled settings. The only permitted outbound HTTP destinations are `api.wordpress.org` (core checksums) and the site's own URL (self-probes), enforced by a single allowlist wrapper class that is the sole gateway for all outbound requests. Every permitted call is documented.

4. **Never eval, never write executable code.** No `eval`, `create_function`, `assert()` on strings, variable includes, or `call_user_func` on user-supplied strings. The plugin never writes a `.php` file, with one audited exception: the uploads-directory execution probe writes a randomly named benign canary and deletes it within the same probe; if deletion fails, the probe fails closed and alerts. Detection rules and wordlists ship as static data files inside the plugin.

5. **Least privilege, no self-elevation.** The plugin never grants capabilities (including to itself), never creates users, and never modifies other plugins' or themes' files. Every fix action is limited to a documented list, individually confirmed by the administrator, reversible, and logged.

6. **Fail closed and honestly.** A probe that cannot run reports `UNTESTABLE`, never `VERIFIED`. Interrupted scans report partial coverage. The plugin never displays a "your site is secure" summary.

7. **Tamper-evident logging.** Findings and administrative actions are logged append-only with hash chaining (each entry embeds the hash of the previous). An attacker with admin access can delete the log but cannot silently edit it.

8. **All input is hostile** — including input from this site's own database and filesystem, which may already be attacker-controlled. File paths are canonicalised with `realpath` and confined to expected roots before any read. No user-supplied path reaches `include`, `require`, or `unlink` without allowlist validation.

9. **Shared-hosting-safe by design.** No `exec` / `shell_exec` / `system` / `proc_open` anywhere. Scans run in time-boxed, resumable batches; files are read in bounded chunks; there is zero footprint on public page loads beyond O(1) passive hooks.

10. **Reviewable code only.** No obfuscated or minified PHP, no bundled binaries. Minimal (ideally zero) runtime Composer dependencies — every dependency is another maintainer who can be compromised. CI enforces PHPCS with the WordPress-Extra and WordPress-Security rulesets at zero errors.

## Reporting a vulnerability

If you find a security issue in Shomer, please report it privately — do not open a public issue.

- Use GitHub's **private vulnerability reporting** on this repository (Security tab → "Report a vulnerability").
- You will receive an acknowledgement within 72 hours and a status update at least weekly until resolution.
- Please include reproduction steps and the affected version/commit.
- Coordinated disclosure: we ask for up to 30 days to ship a fix before public disclosure; credit is given in the changelog and release notes unless you prefer otherwise.

No bug bounty is offered at this stage of the project, but reports are taken seriously and researchers are credited.

## Supported versions

Pre-release: only the latest commit on the default branch is supported. This section will list supported release lines once versioned releases begin.
