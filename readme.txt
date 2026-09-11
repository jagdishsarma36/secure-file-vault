=== Secure File Vault ===
Contributors: jagdishsarma36
Tags: file sharing, password manager, notes, private files, security
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private file storage with Drive-style folders, a LastPass-style notes and password manager, CSV import, an HTML editor with shortcode embedding, and revocable share links — all self-hosted inside your own WordPress site.

== Description ==

**Secure File Vault** turns WordPress into a private workspace for your team: files, notes, and passwords, each kept private to the person who created them, with nothing relying on a third-party cloud service.

= Files =

* Upload files into Drive-style folders with 10 color tags, starring, and drag-free navigation via breadcrumbs
* Multi-file uploads run over AJAX one file at a time with a live progress bar — safe for many or large files (no single-request timeout)
* Generate a unique, revocable share link per recipient — never the same URL for two people
* **Share whole folders** two ways: a public, revocable link (with its own password, expiry and download limit, listing a read-only snapshot of the folder) or share directly with specific WordPress users who then see it under "Shared with me"
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

* A professional, IDE-style dual-pane composer — a real syntax-highlighted code editor (CodeMirror 6, bundled locally with the plugin, with line numbers, bracket matching, auto-closing tags, auto-complete, and a built-in find panel) on the left, a directly-editable visual preview on the right, similar in spirit to html5-editor.net
* Full formatting toolbar with icon buttons, on the preview side: Bold, Italic, Underline, Strikethrough, heading styles (H1–H4), blockquote, code block, bullet/numbered lists, indent/outdent, alignment, links, images, horizontal rule, clear formatting, undo/redo — edits there sync back into the raw HTML automatically
* Device-width preview toggle (desktop / tablet / mobile) to check responsive layouts at a glance
* Nothing is saved anywhere on the server — it's a pure front-end tool. Drop `[wfv_html_editor]` into any post or page and anyone viewing it (no login required) gets a working live editor in their browser
* Demo content, one-click minify, inline-style cleanup, find &amp; replace, a color picker, one-click copy to clipboard, a live status bar (cursor position + character/line counts), adjustable font size, and an optional Bootstrap CDN toggle for previewing
* Optional shortcode attributes: `[wfv_html_editor height="600" demo="no"]`
* Place it multiple times on the same page — each instance runs independently
* CodeMirror 6 is bundled locally with the plugin (no external CDN, no runtime dependency on third-party servers), loaded only once per page however many times the shortcode is used

= Sticky Notes =

* A Post-it-style note board embeddable anywhere with `[wfv_sticky_notes]` — no admin setup required
* **Signed-in visitors**: notes save to the database and follow them across devices, private to that person (no admin bypass)
* **Signed-out visitors**: notes save only in that browser's `localStorage` — nothing touches the server, and the notes won't appear on another device or browser (the widget shows which mode is active)
* Full formatting when writing a note — Bold, Italic, Underline, Strikethrough, text color, highlight color, bullet/numbered lists, code blocks, and links — preserved through saving and restricted to a small safe tag allow-list enforced both server-side and client-side, so formatting never becomes a way to inject scripts or arbitrary markup
* Give every note its own background color — pick from preset swatches or any custom color from the picker
* Pin notes to keep them on top, set a priority (Low/Medium/High, color-coded), and filter the board by All / Pinned / High / Medium / Low
* The note listing has a fixed, scrollable height (`[wfv_sticky_notes height="480"]` to customize) so the widget doesn't keep growing taller as notes pile up

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

Almost nothing does. Files, Notes, Passwords, encryption, the Notes rich-text editor, and the HTML Editor's code pane (CodeMirror 6, bundled locally) all run entirely on your own server using WordPress's built-in APIs — no external runtime calls. The only external request is the optional GitHub update check.

= How does the GitHub updater work? =

It checks the GitHub repo's latest release (or tag, if no release is published) every few hours, and if it's newer than your installed version, WordPress will show a normal "Update available" notice on the Plugins page. To disable it entirely, open `secure-file-vault.php` and comment out the line that requires `updater.php`, or delete that file.

= Can I import from my old password manager? =

Yes — from either the Notes or Passwords screen, use "⬆ Import from CSV". It auto-detects LastPass, Google Password Manager, and Bitwarden export formats, plus a generic `title,username,password,url,notes,tags` CSV. Bitwarden and LastPass exports that mix logins and secure notes in one file are split automatically — logins go to Passwords, notes go to Notes. Delete the original export file from your computer after importing, since it contains plaintext passwords.

= Does the HTML Editor save anything on the server? =

No. It's purely a front-end tool — nothing is written to your database or filesystem. Anyone viewing a page with `[wfv_html_editor]` gets a live, working editor in their own browser; if they navigate away, whatever they typed is gone, same as the reference tool it's modeled on. There's no login requirement and no admin screen for it — it's just the shortcode.

= Where do Sticky Notes get saved? =

It depends on whether the visitor is logged in. Logged-in visitors get their notes saved to the database, tied to their account, so they show up on any device. Signed-out visitors get their notes saved only in that one browser's `localStorage` — nothing is sent to the server, but that also means the notes won't follow them to another browser or device, and clearing browser data will erase them. The widget always shows a small badge telling you which mode is active.

== Screenshots ==

1. Files — folders with color tags and starring
2. Notes — searchable list with a full-width rich-text detail pane
3. Passwords — the same layout, with reveal/copy/generate and a master-password lock screen

== Changelog ==

= 2.9.0 =
* **Upload reliability:** file uploads now go over AJAX one file at a time with a live progress bar, so uploading many or large files no longer trips a single-request timeout
* **New:** Folder sharing — create a revocable public share link for any folder (optional password, expiry, download limit) that shows a read-only, browsable page of the folder's files and subfolders
* **New:** Share a folder directly with selected WordPress users; they see it under "Shared with me" and can browse and download its contents (read-only)
* Direct download button for your own files
* New folder-share database tables are created automatically on upgrade

= 2.8.1 =
* Fixed: Sticky Notes line breaks were still lost after saving — the client sanitizer appended each unwrapped block's `<br>` boundary only *after* moving it into the parent, so the check always saw an empty fragment. Boundaries are now decided before the append; line breaks and blank lines survive the save → reload round-trip.
* Fixed: Sticky Notes color swatches now show their actual color — the composer's preset swatches are filled with their background, and the active swatch matches the note color case-insensitively (so the custom picker also highlights the right preset).
* Fixed: text/highlight colors are normalized to `<span style="color:…">` / `<span style="background-color:…">` instead of keeping a `<font>` wrapper that the server-side sanitizer would have stripped.

= 2.8.0 =
* Changed: Sticky Notes formatting is now reliably preserved — the composer forces tag-based formatting (`styleWithCSS` off), keeps the text selection when toolbar buttons are clicked, and turns browser-emitted `<span style>` into `<b>`/`<i>`/`<u>`/`<s>` instead of stripping it.
* Added: text-color and highlight-color pickers to the Sticky Notes composer (color / background-color spans survive the sanitizer, allowed server-side too).
* Added: HTML Editor polish — a status bar (cursor line/column, character and line count), a "Copy HTML" button, and a "Clean styles" button that strips every inline `style="…"` attribute and leftover `<font>`/empty `<span>` tags.

= 2.7.0 =
* Added: Sticky Notes can now store code blocks — a "code" toolbar button wraps the selection in a monospace `<pre>` block (styled dark), and `pre`/`code` were added to the server- and client-side allow-lists.
* Added: custom note colors — a color row in the composer with preset swatches plus a full color picker; the chosen background is saved per-note (new `color` column, migrated automatically on update).
* Fixed: multi-line notes lost their line breaks (`<div>` wrappers produced by the editor were being silently unwrapped). The sanitizer now preserves block breaks as `<br>` boundaries, in both the browser and server-side sanitizer, and no longer lets disallowed elements hidden inside an unwrapped wrapper (e.g. a `<script>` inside a `<div>`) slip through.

= 2.6.0 =
* Added: a formatting toolbar to Sticky Notes (Bold, Italic, Underline, Strikethrough, lists, links) — restricted to a safe tag allow-list enforced both server-side (wp_kses) and client-side (for localStorage-only notes, which never reach the server).
* Changed: the note listing now has a fixed, scrollable height instead of growing indefinitely as notes are added — customizable via `[wfv_sticky_notes height="480"]`.

= 2.5.0 =
* Fixed: the HTML Editor's formatting toolbar (Bold, Italic, headings, lists, links, etc.) wasn't responding to clicks at all — a CSS selector bug meant no click listeners were ever attached to it. All formatting buttons now work correctly.
* Added: Sticky Notes — a new `[wfv_sticky_notes]` shortcode for a pin/priority/filter note board, embeddable on any page. Saves to the database for logged-in visitors, or to browser localStorage for anonymous ones.

= 2.4.0 =
* Changed: the HTML Editor now looks and feels like a professional dev tool — a real syntax-highlighted code editor (CodeMirror, dark theme, line numbers, bracket matching, auto-closing tags) replaces the plain textarea, the emoji toolbar was replaced with a clean SVG icon toolbar, and a desktop/tablet/mobile device-width preview toggle was added.

= 2.3.2 =
* Added: a real formatting toolbar to the HTML Editor — Bold, Italic, Underline, headings, lists, blockquote, alignment, links, images, and more, applied directly in the visual preview and synced back into the raw HTML source automatically.

= 2.3.1 =
* Changed: the HTML Editor is now a pure, stateless front-end tool — `[wfv_html_editor]` embeds a live dual-pane editor directly, usable by any visitor without login, with nothing saved to the database. (Corrects 2.3.0, which mistakenly added server-side storage and an admin management screen for something that was only ever meant to be a client-side tool.)

= 2.3.0 =
* Added: HTML Editor — a dual-pane HTML source/live-preview composer (demo content, minify, find & replace, color picker, Bootstrap preview toggle).

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

= 2.8.1 =
Sticky Notes line breaks are no longer lost on save, color swatches display and highlight correctly, and text/highlight colors survive saving.

= 2.8.0 =
Sticky Notes formatting (bold, colors, highlights, line breaks) survives saving, and the HTML Editor gained a status bar, Copy button, and an inline-style cleanup tool.

= 2.7.0 =
Sticky Notes can now hold code blocks and custom colors, and multiline notes keep their line breaks after saving.

= 2.6.0 =
Sticky Notes now support basic formatting (bold, lists, links) and the note listing scrolls within a fixed height instead of growing indefinitely.

= 2.5.0 =
Fixes a bug where the HTML Editor's formatting toolbar didn't respond to clicks at all. Also adds Sticky Notes, a new pin/priority/filter note board shortcode. Recommended for all users of the HTML Editor.

= 2.4.0 =
The HTML Editor now has a real syntax-highlighted code editor, a clean icon toolbar, and a device-width preview toggle — a full visual upgrade.

= 2.3.2 =
Adds a proper formatting toolbar (Bold, headings, lists, links, etc.) to the HTML Editor's visual preview.

= 2.3.1 =
Fixes the HTML Editor to work as intended: `[wfv_html_editor]` is now a stateless, front-end-only live editor with nothing saved server-side.

= 2.1.0 =
Adds auto-updates via GitHub and fixes a bug where editing a second note in a row could show the previous note's content in the editor. Recommended for all users.
