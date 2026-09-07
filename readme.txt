=== Shomer ===
Contributors: ankit087087
Tags: security, hardening, integrity, monitoring, malware
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Watchman for WordPress. Notices when your site's trust surface changes and asks whether a human allowed it.

== Description ==

Shomer (שומר) means watchman. Most security plugins hunt signatures and known CVEs. That work matters. It also misses the boring path attackers use after a cleanup: a dropper that puts the malware back.

Shomer takes a different cut. It builds a picture of what is normal on this specific site and reports when that picture changes. Use it next to Wordfence, Patchstack, or Solid Security. It is not a replacement for them.

= What 0.1.0 includes =

* Admin dashboard with honest status counts (verified, failed, untestable)
* Findings store and a tamper evident event log
* Resumable scan engine safe for shared hosting limits
* First hardening probes: PHP in uploads, REST user listing
* HTTP allowlist for the few outbound calls the plugin is allowed to make
* Source self audit for zero unauthenticated surface

= Coming later =

* More hardening handbook probes
* Persistence hunter (mu-plugins, PHP in uploads inventory, core integrity)
* Credential custody for Application Passwords and unexpected admins
* Trust surface diffs around plugin and theme updates

= Trust promise =

No vendor callbacks. No telemetry in v1. No unauthenticated REST or AJAX handlers. Update path is wordpress.org only when published there.

== Installation ==

1. Upload the `shomer` folder to `/wp-content/plugins/`
2. Activate Shomer from the Plugins screen
3. Open Shomer in the admin menu
4. Run Scan now once to confirm the dummy finding shows up

== Frequently Asked Questions ==

= Does this replace Wordfence? =

No. Keep your scanner and WAF. Shomer covers drift and human authorisation.

= Does it phone home? =

No. The only outbound HTTP it allows is to api.wordpress.org (checksums) and your own site URL (self probes).

= Will it say my site is secure? =

No. The top line always shows verified, failed, and untestable counts.

== Changelog ==

= 0.1.0 =
* Foundation release: tables, findings store, hash chained log, admin shell, scan engine, dummy check, smoke tests
