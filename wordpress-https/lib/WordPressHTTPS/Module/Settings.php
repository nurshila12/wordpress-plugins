<?php 
/**
 * Admin Settings Module
 * 
 * Adds the settings page.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 * 
 */

class WordPressHTTPS_Module_Settings extends Mvied_Plugin_Module {

	/**
	 * Initialize Module
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( is_admin() && isset($_GET['page']) && strpos($_GET['page'], $this->getPlugin()->getSlug()) !== false ) {
			if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'wphttps-settings' ) {
				add_action('plugins_loaded', array(&$this, 'save'), 1);
			}

			// Add meta boxes
			add_action('admin_init', array(&$this, 'add_meta_boxes'));

			// Add scripts
			add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
		}

		// Add admin menus
		add_action('admin_menu', array(&$this, 'admin_menu'));
	}

	/**
	 * Admin panel menu option
	 * WordPress Hook - admin_menu
	 *
	 * @param none
	 * @return void
	 */
	public function admin_menu() {
		if ( $this->getPlugin()->getSetting('admin_menu') === 'side' ) {
			add_menu_page('HTTPS', 'HTTPS', 'manage_options', $this->getPlugin()->getSlug(), array($this->getPlugin()->getModule('Settings'), 'dispatch'), '', 88);
		} else {
			add_options_page('HTTPS', 'HTTPS', 'manage_options', $this->getPlugin()->getSlug(), array($this->getPlugin()->getModule('Settings'), 'dispatch'));
		}
	}

	/**
	 * Add meta boxes to WordPress HTTPS Settings page.
	 *
	 * @param none
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			$this->getPlugin()->getSlug() . '_settings',
			__( 'General Settings', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'main',
			'high',
			array( 'metabox' => 'settings' )
		);
		add_meta_box(
			$this->getPlugin()->getSlug() . '_updates',
			__( 'Developer Updates', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'side',
			'high',
			array( 'metabox' => 'ajax', 'url' => 'http://wordpresshttps.com/client/updates.php' )
		);
		add_meta_box(
			$this->getPlugin()->getSlug() . '_support',
			__( 'Support', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'side',
			'high',
			array( 'metabox' => 'ajax', 'url' => 'http://wordpresshttps.com/client/support.php' )
		);
		add_meta_box(
			$this->getPlugin()->getSlug() . '_rate',
			__( 'Feedback', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'side',
			'core',
			array( 'metabox' => 'ajax', 'url' => 'http://wordpresshttps.com/client/rate.php' )
		);
		add_meta_box(
			$this->getPlugin()->getSlug() . '_donate',
			__( 'Donate', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'side',
			'core',
			array( 'metabox' => 'ajax', 'url' => 'http://wordpresshttps.com/client/donate.php' )
		);
		add_meta_box(
			$this->getPlugin()->getSlug() . '_donate2',
			__( 'Loading...', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'main',
			'low',
			array( 'metabox' => 'ajax', 'url' => 'http://wordpresshttps.com/client/donate2.php' )
		);
	}

	/**
	 * Dispatch request for settings page
	 *
	 * @param none
	 * @return void
	 */
	public function dispatch() {
		if ( !current_user_can('manage_options') ) {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}

		self::render();
	}

	/**
	 * Adds javascript and stylesheets to settings page in the admin panel.
	 * WordPress Hook - enqueue_scripts
	 *
	 * @param none
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style($this->getPlugin()->getSlug() . '-admin-page', $this->getPlugin()->getPluginUrl() . '/admin/css/settings.css', array(), $this->getPlugin()->getVersion());
		wp_enqueue_script('jquery-form');
		wp_enqueue_script('post');
		
		if ( function_exists('add_thickbox') ) {
			add_thickbox();
		}
	}

	/**
	 * Render settings page
	 *
	 * @param none
	 * @return void
	 */
	public function render() {
		require_once($this->getPlugin()->getDirectory() . '/admin/templates/settings.php');
	}
	
	/**
	 * Save Settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function save() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug() . '-options') ) {
			return false;
		}

		$message = "Settings saved.";
		$errors = array();
		$reload = false;
		$logout = false;
		if ( isset($_POST['settings-save']) ) {
			foreach ($this->getPlugin()->getSettings() as $key => $default) {
				if ( !array_key_exists($key, $_POST) && $default == 0 ) {
					$_POST[$key] = 0;
					$this->getPlugin()->setSetting($key, $_POST[$key]);
				} else if ( array_key_exists($key, $_POST) ) {
					if ( $key == 'ssl_host' ) {
						if ( $_POST[$key] != '' ) {
							$_POST[$key] = strtolower($_POST[$key]);
							// Add Scheme
							if ( strpos($_POST[$key], 'http://') === false && strpos($_POST[$key], 'https://') === false ) {
								$_POST[$key] = 'https://' . $_POST[$key];
							}

							$ssl_host = WordPressHTTPS_Url::fromString($_POST[$key]);

							// Add Port
							$port = ((isset($_POST['ssl_port']) && is_int($_POST['ssl_port']) ) ? $_POST['ssl_port'] : $ssl_host->getPort());
							$port = (($port != 80 && $port != 443) ? $port : null);
							$ssl_host->setPort($port);

							// Add Path
							if ( strpos($ssl_host->getPath(), $this->getPlugin()->getHttpUrl()->getPath()) !== true ) {
								$path = '/'. ltrim(str_replace(rtrim($this->getPlugin()->getHttpUrl()->getPath(), '/'), '', $ssl_host->getPath()), '/');
								$ssl_host->setPath(rtrim($path, '/') . $this->getPlugin()->getHttpUrl()->getPath());
							}
							$ssl_host->setPath(rtrim($ssl_host->getPath(), '/') . '/');

							if ( $ssl_host->toString() != $this->getPlugin()->getHttpsUrl()->toString() ) {
								// Ensure that the WordPress installation is accessible at this host
								//if ( $ssl_host->isValid() ) {
									// If secure domain has changed and currently on SSL, logout user
									if ( $this->getPlugin()->isSsl() ) {
										$logout = true;
									}
									$_POST[$key] = $ssl_host->setPort('')->toString();
								/*} else {
									$errors[] = '<strong>SSL Host</strong> - Invalid WordPress installation at ' . $ssl_host;
									$_POST[$key] = get_option($key);
								}*/
							} else {
								$_POST[$key] = $this->getPlugin()->getHttpsUrl()->toString();
							}
						} else {
							$_POST[$key] = get_option($key);
						}
					} else if ( $key == 'ssl_proxy' ) {
						// Reload if we're auto detecting the proxy and we're not in SSL
						if ( $_POST[$key] == 'auto' && ! $this->getPlugin()->isSsl() ) {
							$reload = true;
						}
					} else if ( $key == 'ssl_admin' ) {
						if ( force_ssl_admin() && $this->getPlugin()->getSetting('ssl_host_diff') ) {
							$errors[] = '<strong>SSL Admin</strong> - FORCE_SSL_ADMIN should not be set to true in your wp-config.php while using a non-default SSL Host.';
						// If forcing SSL Admin and currently not SSL, logout user
						} else if ( $_POST[$key] == 1 && !$this->getPlugin()->isSsl() ) {
							$logout = true;
						}
					} else if ( $key == 'ssl_host_subdomain' ) {
						// Checks to see if the SSL Host is a subdomain
						$http_domain = $this->getPlugin()->getHttpUrl()->getBaseHost();
						$https_domain = $this->getPlugin()->getHttpsUrl()->getBaseHost();

						if ( $ssl_host->setScheme('http') != $this->getPlugin()->getHttpUrl() && $http_domain == $https_domain ) {
							$_POST[$key] = 1;
						} else {
							$_POST[$key] = 0;
						}
					}

					$this->getPlugin()->setSetting($key, $_POST[$key]);
				}
			}
		} else if ( isset($_POST['settings-reset']) ) {
			foreach ($this->getPlugin()->getSettings() as $key => $default) {
				$this->getPlugin()->setSetting($key, $default);
			}
			$this->getPlugin()->install();
			$reload = true;
		}

		if ( $logout ) {
			wp_logout();
		}

		require_once($this->getPlugin()->getDirectory() . '/admin/templates/ajax_message.php');
	}
	
}
