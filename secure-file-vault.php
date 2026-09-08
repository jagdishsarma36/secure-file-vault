<?php
/**
 * Plugin Name: Secure File Vault
 * Plugin URI: https://github.com/jagdishsarma36/secure-file-vault
 * Description: Private file storage inside WordPress with Drive-style folders, colors, starring, and per-recipient share links, a LastPass-style Notes and Password Manager (searchable sidebar + detail pane, full-width rich-text editing, master-password vault lock, and sharing to other WP users or via public links), CSV import from LastPass/Google/Bitwarden, a [wfv_sticky_notes] shortcode for a pin/priority/filter note board that saves to the database when logged in or to the browser otherwise — all under one unified "Secure Vault" menu with a shared modern design system.
 * Version: 2.9.2
 * Author: Jagdish Sarma
 * Author URI: https://github.com/jagdishsarma36
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wfv
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI: https://github.com/jagdishsarma36/secure-file-vault
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WFV_VERSION', '2.9.2' );
define( 'WFV_PRIVATE_DIRNAME', 'wfv-private' );
define( 'WFV_FILE', __FILE__ );
define( 'WFV_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFV_URL', plugin_dir_url( __FILE__ ) );

require_once WFV_DIR . 'updater.php';

require_once WFV_DIR . 'includes/core.php';
require_once WFV_DIR . 'includes/access-control.php';
require_once WFV_DIR . 'includes/admin-menu.php';
require_once WFV_DIR . 'includes/file-actions.php';
require_once WFV_DIR . 'includes/csv-import.php';
require_once WFV_DIR . 'includes/notes.php';
require_once WFV_DIR . 'includes/master-password.php';
require_once WFV_DIR . 'includes/passwords.php';
require_once WFV_DIR . 'includes/frontend-share.php';
require_once WFV_DIR . 'includes/html-editor.php';
require_once WFV_DIR . 'includes/sticky-notes.php';
require_once WFV_DIR . 'includes/admin-preview.php';
require_once WFV_DIR . 'includes/admin-pages.php';

register_activation_hook( WFV_FILE, 'wfv_activate' );
function wfv_activate() {
    wfv_install_schema();
    wfv_prepare_private_dir();
    update_option( 'wfv_db_version', WFV_VERSION );
}

add_action( 'plugins_loaded', 'wfv_maybe_upgrade' );
function wfv_maybe_upgrade() {
    if ( get_option( 'wfv_db_version' ) !== WFV_VERSION ) {
        wfv_install_schema();
        wfv_prepare_private_dir();
        update_option( 'wfv_db_version', WFV_VERSION );
    }
}

add_action( 'admin_init', 'wfv_handle_admin_actions' );
