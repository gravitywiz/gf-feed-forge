<?php
/**
 * @package gf-feed-forge
 * @copyright Copyright (c) 2022, Gravity Wiz, LLC
 * @author Gravity Wiz <support@gravitywiz.com>
 * @license GPLv2
 * @link https://github.com/gravitywiz/gf-feed-forge
 */
defined( 'ABSPATH' ) || die();

GFForms::include_feed_addon_framework();

class GWiz_GF_Feed_Forge extends GFAddOn {
	/**
	 * @var GWiz_GF_Feed_Forge\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\Bootstrap The updater instance.
	 */
	public $updater;

	/**
	 * @var null|GWiz_GF_Feed_Forge
	 */
	private static $instance = null;

	protected $_version     = GWIZ_GF_FEED_FORGE_VERSION;
	protected $_path        = 'gf-feed-forge/gf-feed-forge.php';
	protected $_full_path   = __FILE__;
	protected $_slug        = 'gf-feed-forge';
	protected $_title       = 'Gravity Forms Feed Forge';
	protected $_short_title = 'Feed Forge';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Defines the minimum requirements for the add-on.
	 *
	 * @return array
	 */
	public function minimum_requirements() {
		return [
			'gravityforms' => [
				'version' => '2.7',
			],
			'wordpress'    => [
				'version' => '4.8',
			],
		];
	}

	/**
	 * Load dependencies and initialize auto-updater
	 */
	public function pre_init() {
		parent::pre_init();

		$this->setup_autoload();
		$this->init_auto_updater();
	}

	/**
	 * @credit https://github.com/google/site-kit-wp
	 */
	public function setup_autoload() {
		$class_map = array_merge(
			include plugin_dir_path( __FILE__ ) . 'third-party/vendor/composer/autoload_classmap.php'
		);

		spl_autoload_register(
			function ( $class ) use ( $class_map ) {
				$namespace = 'GWiz_GF_Feed_Forge\\Dependencies';
				if ( isset( $class_map[ $class ] ) && substr( $class, 0, strlen( $namespace ) ) === $namespace ) {
					require_once $class_map[ $class ];
				}
			},
			true,
			true
		);
	}

	/**
	 * Initialize the auto-updater.
	 */
	public function init_auto_updater() {
		// Initialize GitHub auto-updater
		add_filter(
			'inc2734_github_plugin_updater_plugins_api_gravitywiz/gf-feed-forge',
			[ $this, 'filter_auto_updater_response' ], 10, 2
		);

		$this->updater = new GWiz_GF_Feed_Forge\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
			plugin_basename( plugin_dir_path( __FILE__ ) . 'gf-feed-forge.php' ),
			'gravitywiz',
			'gf-feed-forge',
			[
				'description_url' => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/main/readme.md',
				'changelog_url'   => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/main/changelog.txt',
				'icons'           => [
					'svg' => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/main/assets/images/icon.svg',
				],
				'banners'         => [
					'low' => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/main/assets/images/banner.jpg',
				],
				'requires_php'    => '5.6.0',
			]
		);
	}

	/**
	 * Filter the GitHub auto-updater response to remove sections we don't need and update various fields.
	 *
	 * @param stdClass $obj
	 * @param stdClass $response
	 *
	 * @return stdClass
	 */
	public function filter_auto_updater_response( $obj, $response ) {
		$remove_sections = [
			'installation',
			'faq',
			'screenshots',
			'reviews',
			'other_notes',
		];

		foreach ( $remove_sections as $section ) {
			if ( isset( $obj->sections[ $section ] ) ) {
				unset( $obj->sections[ $section ] );
			}
		}

		if ( isset( $obj->active_installs ) ) {
			unset( $obj->active_installs );
		}

		$obj->homepage = 'https://gravitywiz.com/gf-feed-forge/';
		$obj->author   = '<a href="https://gravitywiz.com/" target="_blank">Gravity Wiz</a>';

		$parsedown = new GWiz_GF_Feed_Forge\Dependencies\Parsedown();
		$changelog = trim( $obj->sections['changelog'] );

		// Remove the "Changelog" h1.
		$changelog = preg_replace( '/^# Changelog/m', '', $changelog );

		// Remove the tab before the list item so it's not treated as code.
		$changelog = preg_replace( '/^\t- /m', '- ', $changelog );

		// Convert h2 to h4 to avoid weird styles that add a lot of whitespace.
		$changelog = preg_replace( '/^## /m', '#### ', $changelog );

		$obj->sections['changelog'] = $parsedown->text( $changelog );

		return $obj;
	}

	/**
	 * Initialize the add-on. Similar to construct, but done later.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();

		load_plugin_textdomain( $this->_slug, false, basename( dirname( __file__ ) ) . '/languages/' );

		add_action( 'gform_post_entry_list', [ $this, 'modal_markup' ] );
		add_action( 'wp_ajax_gf_process_feeds', [ $this, 'process_feeds' ] );
		add_filter( 'gform_entry_list_bulk_actions', [ $this, 'action_process_feeds' ] );
	}

	public function scripts() {

		$scripts = [
			[
				'handle'    => 'gf-feed-forge-admin',
				'src'       => $this->get_base_url() . '/assets/js/gf-feed-forge-admin.js',
				'version'   => $this->_version,
				'deps'      => [ 'jquery' ],
				'in_footer' => true,
				'enqueue'   => [
					[ 'admin_page' => ['entry_list' ] ],
				],
				'callback'  => [ $this, 'localize_admin_scripts' ],
			],
		];

		return apply_filters( 'gfff_scripts', array_merge( parent::scripts(), $scripts ) );
	}

	public function localize_admin_scripts() {
		wp_localize_script(
			'gf-feed-forge-admin',
			'GFFF_ADMIN',
			[
				'nonce'             => wp_create_nonce( 'gf_process_feeds' ),
				'formId'            => rgget( 'id' ),
				'entryString'       => __( 'entry', 'gf-feed-forge' ),
				'entriesString'     => __( 'entries', 'gf-feed-forge' ),
				'modalHeader'       => __( 'Process Feeds', 'gf-feed_forge' ),
				'modalDescription'  => __( 'Specify which feeds you would like to process for the selected entries.', 'gf-feed_forge' ),
				'successMsg'        => __( 'Feeds for %s were successfully added to the queue for processing.', 'gf-feed-forge' ),
				'noSelectedFeedMsg' => __( 'You must select at least one feed.', 'gf-feed_forge' ),
			]
		);
	}

	public function action_process_feeds( $actions ) {
		// Insert `Process Feeds` option before `Resend Notifications`
		$insert_index = array_search( 'resend_notifications', array_keys( $actions ), true );
		return array_slice( $actions, 0, $insert_index, true ) +
			['process_feeds' => __( 'Process Feeds', 'gf-feed-forge' )] +
			array_slice( $actions, $insert_index, count( $actions ) - $insert_index, true );
	}

	/**
	 * Modal markup.
	 */
	public function modal_markup() {
		$feeds = self::addon_feeds();
		?>
		<div id="feeds_modal_container" style="display:none;">
			<div id="feeds_container">
				<div class="panel-block-tabs__body--settings">
					<div id="process_feeds_options">
						<?php
						if ( empty( $feeds ) || ! is_array( $feeds ) ) {
							?>
							<div class="alert message error inline"><p><?php esc_html_e( 'You must configure at least one feed for this form before you can process feeds with Feed Forge.', 'gf-feed-forge' ); ?></p></div>
							<?php
						} else {
							?>
							<?php
							foreach ( $feeds as $feed ) {
								?>
								<input type="checkbox" class="gform_feeds" value="<?php echo esc_attr( $feed['id'] ); ?>" id="feed_<?php echo esc_attr( $feed['id'] ); ?>" />
								<label for="feed_<?php echo esc_attr( $feed['id'] ); ?>"><?php echo esc_html( $feed['title'] ); ?>: <?php echo esc_html( $this->get_feed_name( $feed ) ); ?></label>
								<br /><br />
								<?php
							}
							?>
							<div class="modal_footer">
								<div class="panel-buttons">
									<input type="button" name="feed_process" value="<?php esc_attr_e( 'Process Feeds', 'gf-feed-forge' ); ?>" class="button" style="vertical-align:middle;" />
									<span id="feeds_please_wait_container" style="display:none; margin-left: 5px;vertical-align:middle;">
										<img src="<?php echo GFCommon::get_base_url(); ?>/images/spinner.svg" />
									</span>
								</div>
							</div>
							<?php
						}
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function get_feed_name( $feed ) {

		// GP Google Sheets is a rebel apparently... ¯\_(ツ)_/¯
		if ( isset( $feed['meta']['feed_name'] ) ) {
			return $feed['meta']['feed_name'];
		}

		return rgar( $feed['meta'], 'feedName' );
	}

	public static function addon_feeds() {
		$form_id     = rgget( 'id' );
		$addons      = self::registered_addons();
		$slugs       = array_keys( $addons );
		$feeds       = GFAPI::get_feeds( null, $form_id );
		$addon_feeds = [];

		foreach ( $feeds  as $feed ) {
			if ( isset( $feed['addon_slug'] ) && in_array( $feed['addon_slug'], $slugs, true ) ) {
				$feed['title'] = $addons[ $feed['addon_slug'] ]->get_short_title();
				$addon_feeds[] = $feed;
			}
		}

		return $addon_feeds;
	}

	public static function registered_addons() {
		$addons      = GFAddOn::get_registered_addons();
		$feed_addons = [];

		foreach ( $addons as $addon ) {
			$addon = call_user_func( [ $addon, 'get_instance' ] );
			if ( $addon instanceof GFFeedAddOn && ! $addon instanceof GFPaymentAddOn ) {
				$feed_addons[ $addon->get_slug() ] = $addon;
			}
		}

		/**
		 * Filter The feed addons.
		 *
		 * @param array $feed_addons  The feed addons.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'gfff_registered_addons', $feed_addons );
	}

	public function process_feeds() {
		check_admin_referer( 'gf_process_feeds', 'gf_process_feeds' );
		$form_id = absint( rgpost( 'formId' ) );
		$leads   = rgpost( 'leadIds' );
		$feeds   = json_decode( rgpost( 'feeds' ) );

		// Credits: Gravity Forms
		if ( 0 == $leads ) {
			// get all the lead ids for the current filter / search
			$filter = rgpost( 'filter' );
			$search = rgpost( 'search' );
			$star   = $filter == 'star' ? 1 : null;
			$read   = $filter == 'unread' ? 0 : null;
			$status = in_array( $filter, [ 'trash', 'spam' ] ) ? $filter : 'active';

			$search_criteria['status'] = $status;

			if ( $star ) {
				$search_criteria['field_filters'][] = [ 'key' => 'is_starred', 'value' => (bool) $star ];
			}
			if ( ! is_null( $read ) ) {
				$search_criteria['field_filters'][] = [ 'key' => 'is_read', 'value' => (bool) $read ];
			}

			$search_field_id = rgpost( 'fieldId' );

			if ( isset( $_POST['fieldId'] ) && $_POST['fieldId'] !== '' ) {
				$key            = $search_field_id;
				$val            = $search;
				$strpos_row_key = strpos( $search_field_id, '|' );
				if ( $strpos_row_key !== false ) { //multi-row
					$key_array = explode( '|', $search_field_id );
					$key       = $key_array[0];
					$val       = $key_array[1] . ':' . $val;
				}
				$search_criteria['field_filters'][] = [
					'key'      => $key,
					'operator' => rgempty( 'operator', $_POST ) ? 'is' : rgpost( 'operator' ),
					'value'    => $val,
				];
			}

			$leads = GFFormsModel::search_lead_ids( $form_id, $search_criteria );
		} else {
			$leads = ! is_array( $leads ) ? array( $leads ) : $leads;
		}

		self::process_entry_feeds( $leads, $feeds, $form_id );

		wp_send_json_success();
	}

	/**
	 * Process selected entries and feeds
	 *
	 * @param array $entries The selected entries.
	 * @param array $feeds The selected feeds.
	 * @param int $form_id The entires form id.
	 *
	 */
	public static function process_entry_feeds( $entries, $feeds, $form_id ) {
		$addons = self::registered_addons();

		$feed_cache = array();

		foreach ( $entries as $entry_id ) {
			foreach ( $feeds as $feed_id ) {
				$feed = isset( $feed_cache[ $feed_id ] ) ? $feed_cache[ $feed_id ] : GFAPI::get_feed( $feed_id );

				if ( ! isset( $feed_cache[ $feed_id ] ) ) {
					$feed_cache[ $feed_id ] = $feed;
				}

				if ( ! is_array( $feed ) ) {
					continue;
				}

				gf_feed_processor()->push_to_queue(
					[
						'addon'    => $addons[ $feed['addon_slug'] ],
						'feed'     => $feed,
						'entry_id' => $entry_id,
						'form_id'  => $feed['form_id'],
					]
				);
			}
		}

		gf_feed_processor()->save()->dispatch();
	}
}
