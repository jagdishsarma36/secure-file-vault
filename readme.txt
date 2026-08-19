=== Secure File Vault ===
Contributors: jagdishsarma36
Tags: file sharing, password manager, notes, private files, security
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private file storage with Drive-style folders, a LastPass-style notes and password manager, CSV import, an HTML editor with shortcode embedding, and revocable share links — all self-hosted inside your own WordPress site.

== Description ==

**Secure File Vault** turns WordPress into a private workspace for your team: files, notes, and passwords, each kept private to the person who created them, with nothing relying on a third-party cloud service.

= Files =

* Upload files into Drive-style folders with 10 color tags, starring, and drag-free navigation via breadcrumbs
* Generate a unique, revocable share link per recipient — never the same URL for two people
* Each link can have its own optional password, expiry date, and download limit
* Images and PDFs preview inline in the browser instead of forcing a download
* Every user sees only their own files and folders; site admins can additionally see everyone's (useful for team oversight)

= Notes =

* A LastPass-style layout: searchable list on the left, full detail pane on the right
* Full-width rich-text editing powered by WordPress's own native TinyMCE editor — no external library
* Tags, custom sort orders, drag-and-drop manual ordering, and pinning
* Strictly private — not even site administrators can see another user's notes

= Passwords =

* The same searchable list + detail pane layout as Notes
* Every password is encrypted at rest (AES-256) using a key derived from your site's own WordPress secret keys
* Optional **master password**, set from your WordPress profile page, which re-encrypts your vault with a key that's never stored anywhere — your vault then has to be unlocked each session
* Share a password with another WordPress user, or generate a public link (with an optional access password, expiry, and view limit) for people without an account
* Built-in password generator and strength meter
* Import from **LastPass**, **Google Password Manager**, or **Bitwarden** CSV exports, or a generic CSV — auto-detected by format, with logins and secure notes routed to the right place automatically
* No admin override, anywhere — password entries are private to their owner even from site administrators

= HTML Editor =

* A dual-pane composer — HTML source on the left, a live preview on the right, similar in spirit to html5-editor.net
* Demo content, one-click minify, find & replace, a color picker, adjustable font size, and an optional Bootstrap CDN toggle for previewing (not saved into your HTML)
* Every saved page gets a shortcode, `[wfv_html id="X"]`, to embed it on any post or page on your site — or `[wfv_html id="X" iframe="yes"]` to render it inside a sandboxed iframe instead

= Auto-updates =

This plugin isn't distributed on WordPress.org. Instead, it checks its [GitHub repository](https://github.com/jagdishsarma36/secure-file-vault) for new releases and lets you update it right from the normal Plugins screen, same as any other plugin. See `updater.php` if you'd like to see exactly how, or want to disable it.

== Installation ==

1. Download the plugin zip (or clone the [GitHub repo](https://github.com/jagdishsarma36/secure-file-vault))
2. Upload the `secure-file-vault` folder to `/wp-content/plugins/`
3. Activate the plugin through the "Plugins" screen in WordPress
4. Look for the new **Secure Vault** menu in your WordPress admin sidebar

= Restricting access =

By default, every logged-in role (Subscriber and up) can use the Files/Notes/Passwords modules. To restrict it, add this to your theme's `functions.php` or a small site-specific plugin:

`add_filter( 'wfv_allowed_roles', function() {
    return array( 'administrator', 'editor' );
} );`

== Frequently Asked Questions ==

= Where are files actually stored? =

In a private directory inside your uploads folder (`wp-content/uploads/wfv-private/`), blocked from direct web access and served only through a PHP handler that checks share-link permissions on every request.

= What happens if I forget my master password? =

There's no recovery, by design — that's what makes it stronger than the default encryption. If you forget it, those password entries stay permanently encrypted; you'd need to delete and re-add them. This is stated clearly on both your profile page and the vault's lock screen before you set one.

= Can site admins see my files, notes, or passwords? =

Admins can see everyone's **files** (useful for team/site oversight). They **cannot** see other users' **notes** or **passwords**, ever — those are strictly private to their owner, with no admin bypass in the code.

= Does this require any external services? =

No. Everything — file storage, encryption, the rich-text editor, password generation — runs on your own server using WordPress's built-in APIs. The only outbound connection is the optional GitHub update check.

= How does the GitHub updater work? =

It checks the GitHub repo's latest release (or tag, if no release is published) every few hours, and if it's newer than your installed version, WordPress will show a normal "Update available" notice on the Plugins page. To disable it entirely, open `secure-file-vault.php` and comment out the line that requires `updater.php`, or delete that file.

= Can I import from my old password manager? =

Yes — from either the Notes or Passwords screen, use "⬆ Import from CSV". It auto-detects LastPass, Google Password Manager, and Bitwarden export formats, plus a generic `title,username,password,url,notes,tags` CSV. Bitwarden and LastPass exports that mix logins and secure notes in one file are split automatically — logins go to Passwords, notes go to Notes. Delete the original export file from your computer after importing, since it contains plaintext passwords.

= Is the HTML Editor's saved content sanitized? =

No — like WordPress's own Custom HTML block, a saved page is rendered exactly as written, including any `<script>` tags, wherever you place its shortcode. That's the point (it needs to actually work as real HTML/CSS/JS), but it also means only people you trust with the HTML Editor screen should have access to it — restrict `wfv_allowed_roles` if needed. Use `iframe="yes"` on the shortcode if you want a page's script/CSS sandboxed away from the rest of your site.

== Screenshots ==

1. Files — folders with color tags and starring
2. Notes — searchable list with a full-width rich-text detail pane
3. Passwords — the same layout, with reveal/copy/generate and a master-password lock screen

== Changelog ==

= 2.3.0 =
* Added: HTML Editor — a dual-pane HTML source/live-preview composer (demo content, minify, find & replace, color picker, Bootstrap preview toggle). Saved pages get a `[wfv_html id="X"]` shortcode to embed on the front end, with an optional sandboxed-iframe mode.

= 2.2.0 =
* Added: CSV import for Passwords and Notes, from LastPass, Google Password Manager, or Bitwarden exports (auto-detected), or a generic CSV. Mixed exports (Bitwarden/LastPass secure notes alongside logins) are automatically split between the two modules.

= 2.1.0 =
* Added: self-contained GitHub-based auto-updater (see `updater.php`), toggled by a single line in the main plugin file
* Added: standard `readme.txt`, plugin header metadata (Plugin URI, Author URI, License URI, Requires PHP, Update URI)
* Fixed: TinyMCE in the Notes detail pane showing stale content from the previously-selected note when switching notes

= 2.0.0 =
* Redesigned Notes and Passwords with a LastPass-style layout: searchable sidebar list + detail pane, replacing the card grid and popup modals

= 1.9.0 =
* Combined Files, Notes, and Passwords under a single "Secure Vault" admin menu with submenus
* Introduced a shared design system and in-app top navigation tabs

= 1.8.0 =
* Added password sharing: to other WordPress users, and via public, revocable links (mirroring the file-sharing model)

= 1.7.0 =
* Added optional master password (set from your WordPress profile) with a full vault lock/unlock flow and session-based unlocking

= 1.6.0 =
* Added the encrypted Password Manager module (AES-256 at rest), fully private with no admin bypass

= 1.5.0 =
* Added full-width note editing with WordPress's native TinyMCE rich-text editor

= 1.4.0 =
* Added tags, multiple sort modes, and drag-and-drop manual ordering to Notes

= 1.3.0 =
* Added the Notes module (private, taggable, color-coded)

= 1.2.0 =
* Added Drive-style folders with color tags and starring for files

= 1.1.0 =
* Added multi-user support: every user manages their own files, admins can additionally see all files

= 1.0.0 =
* Initial release: private file storage with per-recipient revocable share links (optional password, expiry, download limit)

== Upgrade Notice ==

= 2.3.0 =
Adds the HTML Editor tool (dual-pane live preview) with a `[wfv_html id="X"]` shortcode for front-end embedding.

= 2.1.0 =
Adds auto-updates via GitHub and fixes a bug where editing a second note in a row could show the previous note's content in the editor. Recommended for all users.
