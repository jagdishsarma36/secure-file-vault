<?php
/**
 * GitHub auto-updater for Secure File Vault.
 *
 * Lets WordPress check https://github.com/jagdishsarma36/secure-file-vault
 * for new releases (or, if none are published, the latest tag) and offer
 * them through the normal Dashboard → Updates / Plugins screen — exactly
 * like a WordPress.org-hosted plugin, just pointed at GitHub instead.
 *
 * Self-contained on purpose: this whole feature is a single `require_once`
 * in the main plugin file. Comment out that one line (or delete this file)
 * to remove auto-updates entirely; nothing else in the plugin depends on it.
 *
 * How it decides there's an update: it compares the "Version:" header in
 * the running plugin against the latest GitHub release/tag name (a leading
 * "v" is stripped, so both "2.1.0" and "v2.1.0" work). Results are cached
 * for a few hours to stay well under GitHub's unauthenticated API rate
 * limit (60 requests/hour per site).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFV_GitHub_Updater' ) ) {

	class WFV_GitHub_Updater {

		/** @var string Absolute path to the main plugin file. */
		private $file;

		/** @var string "owner/repo" on GitHub. */
		private $repo;

		/** @var string Cache lifetime for the GitHub API response. */
		private $cache_hours;

		/** @var array Parsed plugin header data (Name, Version, etc). */
		private $plugin;

		/** @var string e.g. "secure-file-vault/secure-file-vault.php" */
		private $basename;

		/** @var string e.g. "secure-file-vault" */
		private $slug;

		/** @var array|null Cached GitHub API response for the latest release/tag. */
		private $release;

		public function __construct( $file, $repo, $cache_hours = 6 ) {
			$this->file        = $file;
			$this->repo        = trim( $repo, '/' );
			$this->cache_hours = max( 1, (int) $cache_hours );

			add_action( 'admin_init', array( $this, 'load_plugin_data' ) );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_info_popup' ), 10, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'fix_extracted_folder_name' ), 10, 4 );
			add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 2 );
		}

		public function load_plugin_data() {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$this->plugin   = get_plugin_data( $this->file, false, false );
			$this->basename = plugin_basename( $this->file );
			$this->slug     = dirname( $this->basename );
		}

		private function ensure_plugin_data() {
			if ( empty( $this->basename ) ) {
				$this->load_plugin_data();
			}
		}

		/** Fetches (and caches) the latest release from GitHub, falling back to the latest tag if the repo has no published releases. */
		private function get_release() {
			if ( null !== $this->release ) {
				return $this->release;
			}

			$cache_key = 'wfv_gh_release_' . md5( $this->repo );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				$this->release = $cached;
				return $this->release;
			}

			$data = $this->fetch_latest_release();
			if ( ! $data ) {
				$data = $this->fetch_latest_tag();
			}

			// Cache the outcome either way (including "nothing found") so a
			// misconfigured repo doesn't hammer the GitHub API every load.
			set_transient( $cache_key, $data ? $data : array(), $this->cache_hours * HOUR_IN_SECONDS );
			$this->release = $data ? $data : array();
			return $this->release;
		}

		private function api_get( $url ) {
			$response = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'Accept'     => 'application/vnd.github+json',
						'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
					),
					'timeout' => 12,
				)
			);
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return null;
			}
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return is_array( $body ) ? $body : null;
		}

		private function fetch_latest_release() {
			$data = $this->api_get( "https://api.github.com/repos/{$this->repo}/releases/latest" );
			if ( ! $data || empty( $data['tag_name'] ) ) {
				return null;
			}
			return array(
				'tag'      => $data['tag_name'],
				'name'     => ! empty( $data['name'] ) ? $data['name'] : $data['tag_name'],
				'notes'    => ! empty( $data['body'] ) ? $data['body'] : '',
				'url'      => ! empty( $data['html_url'] ) ? $data['html_url'] : "https://github.com/{$this->repo}",
				'zip'      => ! empty( $data['zipball_url'] ) ? $data['zipball_url'] : '',
				'date'     => ! empty( $data['published_at'] ) ? $data['published_at'] : '',
			);
		}

		private function fetch_latest_tag() {
			$tags = $this->api_get( "https://api.github.com/repos/{$this->repo}/tags" );
			if ( empty( $tags ) || empty( $tags[0]['name'] ) ) {
				return null;
			}
			$tag = $tags[0]['name'];
			return array(
				'tag'   => $tag,
				'name'  => $tag,
				'notes' => '',
				'url'   => "https://github.com/{$this->repo}/releases/tag/{$tag}",
				'zip'   => "https://github.com/{$this->repo}/archive/refs/tags/{$tag}.zip",
				'date'  => '',
			);
		}

		private function normalize_version( $tag ) {
			return ltrim( (string) $tag, 'vV' );
		}

		/** Hooked into pre_set_site_transient_update_plugins — the core "is there an update?" check. */
		public function inject_update( $transient ) {
			if ( empty( $transient ) || ! is_object( $transient ) ) {
				return $transient;
			}
			$this->ensure_plugin_data();
			if ( empty( $this->plugin ) ) {
				return $transient;
			}

			$release = $this->get_release();
			if ( empty( $release ) || empty( $release['zip'] ) ) {
				return $transient;
			}

			$remote_version  = $this->normalize_version( $release['tag'] );
			$current_version = $this->plugin['Version'];

			if ( version_compare( $remote_version, $current_version, '>' ) ) {
				$item                = new stdClass();
				$item->id             = 'github.com/' . $this->repo;
				$item->slug           = $this->slug;
				$item->plugin         = $this->basename;
				$item->new_version    = $remote_version;
				$item->url            = $release['url'];
				$item->package        = $release['zip'];
				$item->tested         = get_bloginfo( 'version' );
				$item->requires       = '5.8';
				$item->requires_php   = '7.4';
				$item->icons          = array();
				$item->banners        = array();
				$item->banners_rtl    = array();
				$item->compatibility  = new stdClass();

				$transient->response[ $this->basename ] = $item;
				unset( $transient->no_update[ $this->basename ] );
			} else {
				// Explicitly mark as "no update" so WP doesn't keep re-checking oddly and the UI shows "up to date".
				$item              = new stdClass();
				$item->id          = 'github.com/' . $this->repo;
				$item->slug        = $this->slug;
				$item->plugin      = $this->basename;
				$item->new_version = $current_version;
				$item->url         = $release['url'];
				$item->package     = '';
				$transient->no_update[ $this->basename ] = $item;
			}

			return $transient;
		}

		/** Hooked into plugins_api — powers the "View version details" popup. */
		public function plugin_info_popup( $result, $action, $args ) {
			if ( 'plugin_information' !== $action ) {
				return $result;
			}
			$this->ensure_plugin_data();
			if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
				return $result;
			}

			$release = $this->get_release();
			if ( empty( $release ) ) {
				return $result;
			}

			$info                 = new stdClass();
			$info->name           = $this->plugin['Name'];
			$info->slug           = $this->slug;
			$info->version        = $this->normalize_version( $release['tag'] );
			$info->author         = ! empty( $this->plugin['AuthorName'] ) ? $this->plugin['AuthorName'] : $this->plugin['Author'];
			$info->homepage       = "https://github.com/{$this->repo}";
			$info->requires       = '5.8';
			$info->requires_php   = '7.4';
			$info->last_updated   = $release['date'];
			$info->download_link  = $release['zip'];
			$info->sections       = array(
				'description' => wp_kses_post( $this->plugin['Description'] ),
				'changelog'   => $release['notes']
					? wpautop( wp_kses_post( $release['notes'] ) )
					: '<p>See the <a href="' . esc_url( $release['url'] ) . '" target="_blank" rel="noopener">GitHub release</a> for details.</p>',
			);

			return $info;
		}

		/**
		 * GitHub's zip archives extract into a folder like
		 * "jagdishsarma36-secure-file-vault-abcdef0" (release zipball) or
		 * "secure-file-vault-2.1.0" (tag archive) — neither matches our
		 * actual plugin folder ("secure-file-vault"), so WordPress would
		 * install it as a brand-new, separate plugin. This renames the
		 * extracted folder to the correct slug before install.
		 */
		public function fix_extracted_folder_name( $source, $remote_source, $upgrader, $extra = null ) {
			global $wp_filesystem;

			if ( empty( $extra['plugin'] ) || $extra['plugin'] !== $this->basename ) {
				return $source;
			}
			if ( ! $wp_filesystem || ! is_object( $wp_filesystem ) ) {
				return $source;
			}

			$desired = trailingslashit( $remote_source ) . $this->slug . '/';
			if ( trailingslashit( $source ) === $desired ) {
				return $source; // already correctly named
			}
			if ( $wp_filesystem->move( $source, $desired, true ) ) {
				return $desired;
			}
			return $source;
		}

		public function add_row_meta_links( $links, $plugin_file ) {
			$this->ensure_plugin_data();
			if ( $plugin_file === $this->basename ) {
				$links[] = '<a href="https://github.com/' . esc_attr( $this->repo ) . '" target="_blank" rel="noopener">GitHub</a>';
			}
			return $links;
		}
	}
}

if ( is_admin() && defined( 'WFV_FILE' ) ) {
	new WFV_GitHub_Updater( WFV_FILE, 'jagdishsarma36/secure-file-vault' );
}
