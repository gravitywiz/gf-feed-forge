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

	const TRANSIENT_CURRENT_BATCH_OPTION_NAMES = 'gfff_current_batch_option_names';

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
		$classes = include plugin_dir_path( __FILE__ ) . 'third-party/vendor/composer/autoload_classmap.php';
		if ( empty( $classes ) || ! is_array( $classes ) ) {
			return;
		}

		$class_map = array_merge(
			$classes
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

		if ( ! class_exists( 'GWiz_GF_Feed_Forge\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\Bootstrap' ) ) {
			return;
		}

		$this->updater = new GWiz_GF_Feed_Forge\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
			plugin_basename( plugin_dir_path( __FILE__ ) . 'gf-feed-forge.php' ),
			'gravitywiz',
			'gf-feed-forge',
			[
				'description_url' => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/master/README.md',
				'changelog_url'   => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/master/changelog.txt',
				'icons'           => [
					'svg' => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/master/assets/images/icon.svg',
					'1x'  => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/master/assets/images/icon-1x.png',
					'2x'  => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/master/assets/images/icon-2x.png',
				],
				'banners'         => [
					'low'  => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/master/assets/images/banner-low.png',
					'high' => 'https://raw.githubusercontent.com/gravitywiz/gf-feed-forge/master/assets/images/banner-high.png',
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

		if ( ! class_exists( 'GWiz_GF_Feed_Forge\Dependencies\Parsedown' ) ) {
			return $obj;
		}

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
		add_action( 'gform_pre_entry_list', [ $this, 'queue_status' ] );
	}

	public function queue_status() {
		$batch_option_names = get_transient( self::TRANSIENT_CURRENT_BATCH_OPTION_NAMES );

		if ( ! is_array( $batch_option_names ) ) {
			return;
		}

		$displayed_message = false;
		$remaining         = 0;

		foreach ( $batch_option_names as $batch_option_name ) {
			if ( ! get_site_option( $batch_option_name ) ) {
				continue;
			}

			$batch = get_site_option( $batch_option_name );

			// Remaining entries to process
			$remaining += count( $batch['data'] );

			$displayed_message = true;
		}

		if ( $remaining > 0 ) {
			GFCommon::add_message( sprintf(
				esc_html__( 'Feed Forge is currently processing a batch. %s remaining. Refresh to see the latest count.', 'gf-feed-forge' ),
				sprintf( _n( '%s entry', '%s entries', $remaining, 'gf-feed-forge' ), number_format_i18n( $remaining ) )
			) );
		}

		if ( ! $displayed_message ) {
			delete_transient( self::TRANSIENT_CURRENT_BATCH_OPTION_NAMES );
		}
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
				'callback'  => [ $this, 'add_gform_pre_entry_list_hook' ],
			],
		];

		return apply_filters( 'gfff_scripts', array_merge( parent::scripts(), $scripts ) );
	}

	public function add_gform_pre_entry_list_hook() {
		// Add hook to gform_pre_entry_list which will give us a reliable form ID for the wp_localize_script call instead
		// of relying on the $_GET['id'] which is not always available.
		add_action( 'gform_pre_entry_list', [ $this, 'localize_admin_script' ] );
	}

	public function localize_admin_script( $form_id ) {
		wp_localize_script(
			'gf-feed-forge-admin',
			'GFFF_ADMIN',
			[
				'nonce'             => wp_create_nonce( 'gf_process_feeds' ),
				'processor_nonce'   => wp_create_nonce( 'wp_gf_feed_processor' ), // Generate nonce for feed processor to pass to client. It is required by the processor during dispatch for nonce verification.
				'formId'            => $form_id,
				'entryString'       => __( 'entry', 'gf-feed-forge' ),
				'entriesString'     => __( 'entries', 'gf-feed-forge' ),
				'modalHeader'       => __( 'Process Feeds', 'gf-feed_forge' ),
				'modalDescription'  => __( 'Specify which feeds you would like to process for the selected entries.', 'gf-feed_forge' ),
				'successMsg'        => __( 'Feeds for %s were successfully added to the queue for processing.', 'gf-feed-forge' ),
				'noSelectedFeedMsg' => __( 'You must select at least one feed.', 'gf-feed_forge' ),
				'genericErrorMsg'   => __( 'Failed to create batch to process feeds. Try selecting fewer entries.', 'gf-feed-forge' ),
				'abortQueueMsg'     => __( 'Abort Queue', 'gf-feed-forge' ),
				'abortSuccessMsg'   => __( 'The queue has been aborted.', 'gf-feed-forge' ),
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
		// Make a list of all the feeds that are not supported by Feed Forge.
		$unsupported_feeds = array(
			'gravityflow',
			'gravityformscoupons',
		);

		$feeds = array_filter( self::addon_feeds(), function ( $feed ) use ( $unsupported_feeds ) {
			return ! in_array( rgar( $feed, 'addon_slug' ), $unsupported_feeds );
		} );
		?>
		<div id="feeds_modal_container" style="display:none;">
			<div id="feeds_container">
				<div class="panel-block-tabs__body--settings">
					<?php
					if ( empty( $feeds ) || ! is_array( $feeds ) ) {
						?>
						<div class="alert message error inline"><p><?php esc_html_e( 'You must configure at least one feed for this form before you can process feeds with Feed Forge.', 'gf-feed-forge' ); ?></p></div>
						<?php
					} else {
						?>
						<div id="process_feeds_options" style="height:340px;padding:0.2rem;overflow:auto;">
							<?php
							foreach ( $feeds as $feed ) {
								?>
								<input type="checkbox" class="gform_feeds" value="<?php echo esc_attr( $feed['id'] ); ?>" id="feed_<?php echo esc_attr( $feed['id'] ); ?>" />
								<label for="feed_<?php echo esc_attr( $feed['id'] ); ?>"><?php echo esc_html( $feed['title'] ); ?>: <?php echo esc_html( $this->get_feed_name( $feed ) ); ?></label>
								<br /><br />
								<?php
							}
							?>
						</div>
						<?php
					}
					?>
				</div>

			</div>

			<div class="modal_footer">
				<div class="panel-buttons" style="display:flex;gap:1rem;align-items:center;">

					<div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
						<!-- Process Feeds Button -->
						<input type="button" name="feed_process" value="<?php esc_attr_e( 'Process Feeds', 'gf-feed-forge' ); ?>" class="button" style="vertical-align:middle;" />

						<!-- Reprocess Feeds Checkbox -->
						<div id="gfff-reprocess-feeds-container" class="panel-block-tabs__body--settings">
							<input type="checkbox" class="gform_feeds" id="reprocess_feeds" style="margin:0;" />
							<label for="reprocess_feeds" style="margin: 0;"><?php esc_html_e( 'Reprocess feed that have already been processed.', 'gf-feed-forge' ); ?></label>
						</div>
					</div>

					<!-- Progress Bar -->
					<div id="gfff-progress-bar" style="border: 1px solid #e2e8f0; height: 1.375rem; width: 100%; padding: 2px; border-radius: 4px; display: none;">
						<span style="display: block; height: 100%; width: 0; background-color: #3e7da6; border-radius: 3px; transition: all 0.5s ease;"></span>
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
		$addon_feeds = [];

		/**
		 * Filter whether to include inactive feeds in the feed list.
		 *
		 * @param bool $include_inactive Whether to include inactive feeds. Defaults to false.
		 * @param int  $form_id          The ID of the current form.
		 *
		 * @since 1.1.9
		 */
		$include_inactive = gf_apply_filters( array( 'gfff_include_inactive_feeds', $form_id ), false );
		// If the filter is true, we pass `null` to the API to get all feeds.
		// Otherwise, we pass `true` to get only active feeds.
		$is_active_param = $include_inactive ? null : true;
		$feeds           = GFAPI::get_feeds( null, $form_id, null, $is_active_param );

		foreach ( $feeds as $feed ) {
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

		// Use client-provided nonce for processor verification.
		if ( isset( $_POST['processor_nonce'] ) ) {
			$_REQUEST['nonce'] = $_POST['processor_nonce'];
		}

		// Ensure that the form ID is provided.
		if ( empty( $form_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Form ID is required.', 'gf-feed-forge' ),
			) );
		}

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

		/**
		 * Filters the selected entries.
		 *
		 * @param array $leads The selected entries.
		 * @param array $feeds The selected feeds.
		 * @param int $form_id The form ID.
		 *
		 * @since 1.0.1
		 */
		$leads = apply_filters( 'gfff_selected_entries', $leads, $feeds, $form_id );

		$size   = $_POST['size'];
		$page   = $_POST['page'];
		$offset = ( $page * $size ) - $size;
		$count  = max( 0, (int) $_POST['count'] );
		$total  = count( $leads );

		$leads = array_slice( $leads, $offset, $size );
		$count = $count + count( $leads );

		$batch_option_name = self::process_entry_feeds( $leads, $feeds, $form_id );

		// If we don't have a batch_option_name, it failed to create the batch.
		if ( empty( $batch_option_name ) ) {
			wp_send_json_error( array(
				'message' => __( 'Failed to create batch to process feeds. Try selecting fewer entries.', 'gf-feed-forge' ),
			) );
		}

		// Set the batch_option_name in a transient, so we can display a message on the entry list page. Expire it in a day.
		$batch_option_names = get_transient( self::TRANSIENT_CURRENT_BATCH_OPTION_NAMES );

		if ( ! is_array( $batch_option_names ) ) {
			$batch_option_names = [];
		}

		$batch_option_names[] = $batch_option_name;

		set_transient( self::TRANSIENT_CURRENT_BATCH_OPTION_NAMES, $batch_option_names, DAY_IN_SECONDS );

		if ( $count >= $total ) {
			wp_send_json_success( 'done' );
		} else {
			$page ++;
			wp_send_json_success( compact( 'size', 'page', 'count', 'total', 'form_id' ) );
		}
	}

	/**
	 * Process selected entries and feeds
	 *
	 * @param array $entries The selected entries.
	 * @param array $feeds The selected feeds.
	 * @param int $form_id The entires form id.
	 *
	 * @return string The batch option name.
	 */
	public static function process_entry_feeds( $entries, $feeds, $form_id ) {
		$addons     = self::registered_addons();
		$form       = GFAPI::get_form( $form_id );
		$feed_cache = array();
		$direct_processed_count = 0; // Track entries processed directly (not queued)

		/**
		 * Filters whether to reprocess feeds that have already been processed.
		 *
		 * @param bool $reprocess_feeds Whether to reprocess feeds.
		 *
		 * @since 1.0.1
		 */
		$reprocess_feeds = gf_apply_filters( array( 'gfff_reprocess_feeds', $form_id ), false ) || rgpost( 'reprocess_feeds' ) === 'true';

		foreach ( $entries as $entry_id ) {
			foreach ( $feeds as $feed_id ) {
				$feed = isset( $feed_cache[ $feed_id ] ) ? $feed_cache[ $feed_id ] : GFAPI::get_feed( $feed_id );

				if ( ! isset( $feed_cache[ $feed_id ] ) ) {
					$feed_cache[ $feed_id ] = $feed;
				}

				if ( ! is_array( $feed ) ) {
					continue;
				}

				$addon              = $addons[ $feed['addon_slug'] ];
				$entry              = GFAPI::get_entry( $entry_id );
				$feed_condition_met = $addon->is_feed_condition_met( $feed, $form, $entry );

				/**
				 * Filters whether to reprocess feeds that have already been processed.
				 *
				 * @param bool $feed_condition_met Whether feed condition is met.
				 * @param array $feed The current feed.
				 * @param array $form The current form.
				 * @param array $entry The current entry.
				 *
				 * @since 1.0.2
				 */
				if ( ! gf_apply_filters( array( 'gfff_feed_condition_met', $form_id ), $feed_condition_met, $feed, $form, $entry ) ) {
					continue;
				}

				// Check if this is a previously processed entry for a GC plugin.
				$is_gc_plugin             = substr( $feed['addon_slug'], 0, 3 ) === 'gc-';
				$was_previously_processed = $is_gc_plugin && self::was_entry_previously_processed( $entry_id, $feed, $addon );

				if ( $reprocess_feeds && $was_previously_processed ) {
					self::clear_processed_feeds( $entry_id, $feed, $addon );
				}

				// Apply gform_addon_pre_process_feeds filters to allow feed modification before processing
				$feeds_to_process = apply_filters( 'gform_addon_pre_process_feeds', array( $feed ), $entry, $form );
				$feeds_to_process = apply_filters( "gform_addon_pre_process_feeds_{$form_id}", $feeds_to_process, $entry, $form );
				$feeds_to_process = apply_filters( "gform_{$addon->get_slug()}_pre_process_feeds", $feeds_to_process, $entry, $form );
				$feeds_to_process = apply_filters( "gform_{$addon->get_slug()}_pre_process_feeds_{$form_id}", $feeds_to_process, $entry, $form );

				// Skip if filters removed the feed or returned invalid data
				if ( empty( $feeds_to_process ) || ! is_array( $feeds_to_process ) ) {
					continue;
				}

				$feed = $feeds_to_process[0];

				if ( ! function_exists( 'gf_feed_processor' ) ) {
					continue;
				}

				if ( $reprocess_feeds && $was_previously_processed ) {
					// For GC plugins with previously processed entries, route to edit pathway
					$resource_id = $addon->get_resource_id( $entry, $feed );

					// Build the edit hook name from addon slug (e.g., gc-airtable -> gc_airtable_edit_entry_in_database)
					$edit_hook_name = str_replace( '-', '_', $feed['addon_slug'] ) . '_edit_entry_in_database';

					// Enqueue as edit action instead of normal add queue
					$addon->enqueue_async_action(
						$edit_hook_name,
						array(
							'entry_id'    => $entry_id,
							'feed_id'     => $feed['id'],
							'resource_id' => $resource_id,
							'trigger'     => 'feed_forge_edit',
						),
						$entry_id
					);

					// Track that we processed this entry directly.
					$direct_processed_count++;
				} else {
					// Normal processing for new entries or non-GC plugins.
					gf_feed_processor()->push_to_queue(
						[
							'addon'    => get_class( $addon ),
							'feed'     => $feed,
							'entry_id' => $entry_id,
							'form_id'  => $feed['form_id'],
						]
					);
				}

				/**
				 * Fires after an entry is queued for feed processing.
				 *
				 * @since 1.1.12
				 *
				 * @param array       $entry The entry object.
				 * @param array       $feed  The feed being processed.
				 * @param array       $form  The form object.
				 * @param GFFeedAddOn $addon The addon instance.
				 */
				do_action( 'gfff_entry_queued', $entry, $feed, $form, $addon );
			}
		}

		// Intercept update_site_option to figure out what the option name is for the batch.
		$batch_option_name = '';

		$callback = function ( $option, $value, $network_id ) use ( &$batch_option_name ) {
			$prefix = 'wp_gf_feed_processor_batch_blog_id_' . get_current_blog_id() . '_';

			if ( strpos( $option, $prefix ) === 0 ) {
				$batch_option_name = $option;
			}
		};

		add_action( 'add_site_option', $callback, 10, 3 );

		if ( ! function_exists( 'gf_feed_processor' ) ) {
			return '';
		}

		gf_feed_processor()->save();
		remove_action( 'pre_update_site_option', $callback );

		gf_feed_processor()->dispatch();

		// If we processed some entries directly but have no batch, create a dummy batch name
		if ( empty( $batch_option_name ) && $direct_processed_count > 0 ) {
			$batch_option_name = 'direct_processed_' . time() . '_' . rand( 1000, 9999 );
		}

		return $batch_option_name;
	}

	/**
	 * Check if an entry was previously processed by a specific feed.
	 * This helps determine if we're dealing with an edit vs a new entry.
	 *
	 * @param int $entry_id The entry ID.
	 * @param array $feed The feed array.
	 * @param GFFeedAddOn $addon The addon instance.
	 * @return bool True if entry was previously processed by this feed.
	 */
	public static function was_entry_previously_processed( $entry_id, $feed, $addon ) {
		$feed_id = $feed['id'];

		// Check standard processed_feeds meta first
		$processed_feeds = $addon->get_feeds_by_entry( $entry_id );
		if ( is_array( $processed_feeds ) && in_array( $feed_id, $processed_feeds ) ) {
			return true;
		}

		$entry = GFAPI::get_entry( $entry_id );

		// Check if addon uses External_Service_Feed_Processor trait (GC plugins)
		if ( method_exists( $addon, 'get_resource_id' ) ) {
			// Check for resource ID
			$resource_id = $addon->get_resource_id( $entry, $feed );
			if ( $resource_id ) {
				return true;
			}

			// Check for custom meta patterns
			$slug              = $addon->get_slug();
			$resource_meta_key = "{$slug}_resource_id_{$feed_id}";
			$resource_id       = gform_get_meta( $entry_id, $resource_meta_key );
			if ( $resource_id ) {
				return true;
			}

			$inserted_time = $addon->entry_meta_get_inserted_time( $entry, $feed );
			$updated_time  = method_exists( $addon, 'entry_meta_get_updated_time' )
				? $addon->entry_meta_get_updated_time( $entry, $feed )
				: null;

			if ( $inserted_time || $updated_time ) {
					return true;
			}
		}

		return false;
	}

	public static function clear_processed_feeds( $entry_id, $feed, $addon ) {
		$processed_feeds = $addon->get_feeds_by_entry( $entry_id );
		if ( is_array( $processed_feeds ) && in_array( $feed['id'], $processed_feeds ) ) {
			$all_processed_feeds = gform_get_meta( $entry_id, 'processed_feeds' );

			// Remove feed id from processed feeds
			$key = array_search( $feed['id'], $processed_feeds );

			unset( $processed_feeds[ $key ] );
			$all_processed_feeds[ $feed['addon_slug'] ] = array_values( $processed_feeds );

			gform_update_meta( $entry_id, 'processed_feeds', $all_processed_feeds, $feed['form_id'] );
		}
	}
}
