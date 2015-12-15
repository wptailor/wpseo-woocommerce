<?php
/**
 * Plugin Name:	Yoast WooCommerce SEO
 * Version:     1.1.0
 * Plugin URI:  http://yoast.com/wordpress/yoast-woocommerce-seo/
 * Description: This extension to WooCommerce and WordPress SEO by Yoast makes sure there's perfect communication between the two plugins.
 * Author:      Joost de Valk
 * Author URI:  http://yoast.com
 * Text Domain:	yoast-woo-seo
 * Domain Path:	/languages/
 *
 * Copyright 2013 Joost de Valk (email: joost@yoast.com)
 */

if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

class Yoast_WooCommerce_SEO {
	
	/**
	 * @var object $option_instance	Instance of the WooCommerce_SEO option management class
	 */
	var $option_instance;

	/**
	 * @var array $options
	 */
	var $options = array();

	/**
	 * @var string Name of the option to store plugins setting
	 */
	var $short_name;

	/**
	 * @var string Name of the plugin for updating from update host
	 */
	var $plugin_name = 'WooCommerce Yoast SEO';

	/**
	 * @var string Name of the plugin author for updating from update host
	 */
	var $plugin_author = 'Joost de Valk';

	/**
	 * @var string Update host
	 */
	var $update_host = 'http://yoast.com/';

	/**
	 * @var string Version of the plugin.
	 */
	var $version = '1.1.0';

	/**
	 * @var bool
	 */
	var $license_active = false;

	/**
	 * Class constructor, basically hooks all the required functionality.
	 *
	 * @since 1.0
	 */
	function __construct() {
		// Initialize the options
		require_once( plugin_dir_path( __FILE__ ) . 'class-wpseo-option-woo.php' );
		$this->option_instance = WPSEO_Option_Woo::get_instance();
		$this->short_name      = $this->option_instance->option_name;
		$this->options         = get_option( $this->short_name );
		
		// Make sure the options property is always current
		add_action( 'add_option_' . $this->short_name, array( $this, 'refresh_options_property' ) );
		add_action( 'update_option_' . $this->short_name, array( $this, 'refresh_options_property' ) );


		if ( $this->options['license-status'] === 'valid' ) {
			$this->license_active = true;
		}

		if ( $this->license_active ) {
			// Check if the options need updating
			if ( $this->option_instance->db_version > $this->options['dbversion'] ) {
				$this->upgrade();
			}
		}

		if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			$this->initialize_update_class();

			// Admin page
			add_action( 'admin_menu', array( $this, 'register_settings_page' ), 20 );
			add_action( 'admin_print_styles', array( $this, 'config_page_styles' ) );

			if ( $this->license_active ) {
				// Products tab columns
				if ( $this->options['hide_columns'] === true ) {
					add_filter( 'manage_product_posts_columns', array( $this, 'column_heading' ), 11, 1 );
				}

				// Move Woo box above SEO box
				if ( $this->options['metabox_woo_top'] === true ) {
					add_action( 'admin_footer', array( $this, 'footer_js' ) );
				}

				add_filter( 'wpseo_body_length_score', array( $this, 'change_body_length_requirements' ), 10, 2 );
				add_filter( 'wpseo_linkdex_results', array( $this, 'add_woocommerce_specific_content_analysis_tests' ), 10, 3 );
				add_filter( 'wpseo_pre_analysis_post_content', array( $this, 'add_product_images_to_content' ), 10, 2 );
			}
		}
		else if ( $this->license_active ) {
			$wpseo_options = WPSEO_Options::get_all();

			// OpenGraph
			add_filter( 'wpseo_opengraph_type', array( $this, 'return_type_product' ) );
			add_filter( 'wpseo_opengraph_desc', array( $this, 'og_desc_enhancement' ) );
			add_action( 'wpseo_opengraph', array( $this, 'og_enhancement' ) );

			// Twitter
			add_filter( 'wpseo_twitter_card_type', array( $this, 'return_type_product' ) );
			add_filter( 'wpseo_twitter_domain', array( $this, 'filter_twitter_domain' ) );
			add_action( 'wpseo_twitter', array( $this, 'twitter_enhancement' ) );

			add_filter( 'wpseo_sitemap_exclude_post_type', array( $this, 'xml_sitemap_post_types' ), 10, 2 );
			add_filter( 'wpseo_sitemap_exclude_taxonomy', array( $this, 'xml_sitemap_taxonomies' ), 10, 2 );

			add_filter( 'woocommerce_attribute', array( $this, 'schema_filter' ), 10, 2 );

			if ( $this->options['breadcrumbs'] === true	&& $wpseo_options['breadcrumbs-enable'] === true ) {
				remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );
				add_action( 'woocommerce_before_main_content', array( $this, 'woo_wpseo_breadcrumbs' ) );
			}
		}
	}
	
	/**
	 * Refresh the options property on add/update of the option to ensure it's always current
	 */
	function refresh_options_property() {
		$this->options        = get_option( $this->short_name );
		$this->license_active = ( $this->options['license-status'] === 'valid' ) ? true : false;
	}

	/**
	 * Changes the body copy length requirements for products.
	 *
	 * @param array $lengthReqs The length requirements currently set.
	 * @param array $job        The analysis job array.
	 *
	 * @return array
	 */
	function change_body_length_requirements( $lengthReqs, $job ) {
		if ( $job['post_type'] === 'product' ) {
			return array(
				'good' => 200,
				'ok'   => 150,
				'poor' => 100,
				'bad'  => 75,
			);
		}
		return $lengthReqs;
	}

	/**
	 * Check whether the current post is a product, if so, do some WooCommerce specific testing.
	 *
	 * @param array  $results The results for the content analysis.
	 * @param array  $job     The analysis job array.
	 * @param object $post    The post object for which we're running the analysis.
	 *
	 * @return mixed
	 */
	function add_woocommerce_specific_content_analysis_tests( $results, $job, $post ) {
		if ( $post->post_type === 'product' ) {
			$results = $this->test_short_description( $results, $post );
		}
		return $results;
	}

	/**
	 * Test whether the short description is actually of decent length.
	 *
	 * @param array  $results The results for the content analysis.
	 * @param object $post    The post object for which we're running the analysis.
	 *
	 * @return array
	 */
	function test_short_description( $results, $post ) {
		global $wpseo_metabox;

		$word_count = $wpseo_metabox->statistics->word_count( strip_tags( $post->post_excerpt ) );
		if ( $word_count == 0 ) {
			$wpseo_metabox->save_score_result( $results, 1, __( 'You should write a short description for this product.', 'yoast-woo-seo' ), 'woocommerce_shortdesc' );
		}
		else if ( $word_count < 20 ) {
			$wpseo_metabox->save_score_result( $results, 5, __( 'The short description for this product too short.', 'yoast-woo-seo' ), 'woocommerce_shortdesc' );
		}
		else {
			$wpseo_metabox->save_score_result( $results, 9, __( 'Your short description has a good length.', 'yoast-woo-seo' ), 'woocommerce_shortdesc' );
		}
		return $results;
	}

	/**
	 * Make sure the product images are used in calculating the score.
	 *
	 * @param string $content The content to modify
	 * @param object $post    The post object for which we're doing the analysis.
	 *
	 * @return string
	 */
	function add_product_images_to_content( $content, $post ) {
		if ( $post->post_type === 'product' ) {
			$args = array(
				'post_type'   => 'attachment',
				'numberposts' => - 1,
				'post_status' => 'inherit',
				'post_parent' => $post->ID,
			);

			$attachments = get_posts( $args );
			if ( is_array( $attachments ) && $attachments !== array() ) {
				foreach ( $attachments as $attachment ) {
					$content .= wp_get_attachment_image( $attachment->ID, 'thumbnail' ) . ' ';
				}
			}
		}
		return $content;
	}

	/**
	 * Initialize plugin update functionality
	 */
	function initialize_update_class() {

		if ( $this->options['license'] !== '' && $this->options['license-status'] === 'valid' ) {
			// Conditionally load the custom updater
			if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
				require_once( plugin_dir_path( __FILE__ ) . 'EDD_SL_Plugin_Updater.php' );
			}

			/* @todo [JRF=>Yoast] I'm pretty sure this shouldn't be a global as this way the updaters for various
			   plugins will override each other */
			global $edd_updater;
			$edd_updater = new EDD_SL_Plugin_Updater(
				$this->update_host,
				__FILE__,
				array(
					'version'   => $this->version, // current version number
					'license'   => trim( $this->options['license'] ), // license key
					'item_name' => $this->plugin_name, // name of this plugin
					'author'    => $this->plugin_author, // author of this plugin
				)
			);
		}
	}


	/**
	 * Perform upgrade procedures to the settings
	 */
	function upgrade() {
		// Nothing yet.
		$this->option_instance->clean();
	}

	/**
	 * Registers the settings page in the WP SEO menu
	 *
	 * @since 1.0
	 */
	function register_settings_page() {
		add_submenu_page( 'wpseo_dashboard', __( 'WooCommerce SEO Settings', 'yoast-woo-seo' ), __( 'WooCommerce SEO', 'yoast-woo-seo' ), 'manage_options', $this->short_name, array( $this, 'admin_panel' ) );
	}

	/**
	 * Loads some CSS
	 *
	 * @since 1.0
	 */
	function config_page_styles() {
		global $pagenow;
		if ( $pagenow == 'admin.php' && ( isset( $_GET['page'] ) && $_GET['page'] === 'wpseo_woo' ) && ( defined( 'WPSEO_PATH' ) && defined( 'WPSEO_CSSJS_SUFFIX' ) && defined( 'WPSEO_VERSION' ) ) ) {
			wp_enqueue_style( 'yoast-admin-css', plugins_url( 'css/yst_plugin_tools' . WPSEO_CSSJS_SUFFIX . '.css', WPSEO_PATH . 'dummy.txt' ), array(), WPSEO_VERSION );
		}
	}

	/**
	 * Builds the admin page
	 *
	 * @since 1.0
	 */
	function admin_panel() {

		if ( isset( $_GET['deactivate'] ) && 'true' == $_GET['deactivate'] ) {

			if ( wp_verify_nonce( $_GET['nonce'], 'yoast_woo_seo_deactivate_license' ) === false ) {
				return;
			}

			// data to send in our API request
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $this->options['license'],
				'item_name'  => $this->plugin_name,
			);

			// Send the remote request
			$url = add_query_arg( $api_params, $this->update_host );

			$response = wp_remote_get( $url, array( 'timeout' => 25, 'sslverify' => false ) );

			if ( ! is_wp_error( $response ) ) {
				$response = json_decode( $response['body'] );

				if ( is_string( $response->license ) && ( 'deactivated' === $response->license || 'failed' === $response->license ) ) {
					$this->options['license']        = '';
					$this->options['license-status'] = 'invalid';
					update_option( $this->short_name, $this->options );
				}
			}

			echo '<script type="text/javascript">document.location = "' . esc_js( admin_url( 'admin.php?page=' . $this->short_name ) ) . '"</script>';
		}


		if ( ! isset( $GLOBALS['wpseo_admin_pages'] ) ) {
			$GLOBALS['wpseo_admin_pages'] = new WPSEO_Admin_Pages;
		}
		$GLOBALS['wpseo_admin_pages']->admin_header( true, $this->option_instance->group_name, $this->short_name, false );

		// @todo [JRF => whomever] change the form fields so they use the methods as defined in WPSEO_Admin_Pages
		echo '
		<h2>' . __( 'License', 'yoast-woo-seo' ) . '</h2>
		<label class="textinput" for="license">' . __( 'License Key', 'yoast-woo-seo' ) . ':</label>
		<input id="license" class="textinput" type="text" name="' . esc_attr( $this->short_name . '[license]' ) . '" value="' . esc_attr( $this->options['license'] ) . '"/><br/>
		<p class="clear description">' . __( 'License Status', 'yoast-woo-seo' ) . ': ' . ( ( $this->license_active ) ? '<span style="color:#090; font-weight:bold">' . __( 'active', 'yoast-woo-seo' ) . '</span>' : '<span style="color:#f00; font-weight:bold">' . __( 'inactive', 'yoast-woo-seo' ) . '</span>' ) . '</p>';

		if ( $this->license_active ) {

			$taxonomies = get_object_taxonomies( 'product', 'objects' );

			echo '
			<p><a href="' . esc_url( admin_url( 'admin.php?page=' . $this->short_name . '&deactivate=true&nonce=' . wp_create_nonce( 'yoast_woo_seo_deactivate_license' ) ) ) . '" class="button">' . __( 'Deactivate License', 'yoast-woo-seo' ) . '</a></p>';

			echo '
			<h2>' . __( 'Twitter Product Cards', 'yoast-woo-seo' ) . '</h2>
			<p>' . __( 'Twitter allows you to display two pieces of data in the Twitter card, pick which two are shown:', 'yoast-woo-seo' ) . '</p>';

			$i = 1;
			while ( $i < 3 ) {
				echo '
				<label class="select" for="datatype' . $i . '">' . sprintf( __( 'Data %d', 'yoast-woo-seo' ), $i ) . ':</label>
				<select class="select" id="datatype' . $i . '" name="' . esc_attr( $this->short_name . '[data' . $i . '_type]' ) . '">' . "\n";
				foreach ( $this->option_instance->valid_data_types as $data_type => $translation ) {
					$sel = selected( $data_type, $this->options['data' . $i . '_type'], false );
					echo '<option value="' . esc_attr( $data_type ) . '"' . $sel . '>' . esc_html( $translation ) . "</option>\n";
				}
				unset( $sel, $data_type, $translation );

				if ( is_array( $taxonomies ) && $taxonomies !== array() ) {
					foreach ( $taxonomies as $tax ) {
						$sel = selected( strtolower( $tax->name ), $this->options['data' . $i . '_type'], false );
						echo '<option value="' . esc_attr( strtolower( $tax->name ) ) . '"' . $sel . '>' . esc_html( $tax->labels->name ) . "</option>\n";
					}
					unset( $tax, $sel );
				}


				echo '</select>';
				if ( $i === 1 ) {
					echo '<br class="clear"/>';
				}
				$i++;
			}

			echo '<br class="clear"/>
			<h2>' . __( 'Schema & OpenGraph additions', 'yoast-woo-seo' ) . '</h2>
			<p>' . __( 'If you have product attributes for the following types, select them here, the plugin will make sure they\'re used for the appropriate Schema.org and OpenGraph markup.', 'yoast-woo-seo' ) . '</p>
			<label class="select" for="schema_brand">' . sprintf( __( 'Brand', 'yoast-woo-seo' ), $i ) . ':</label>
			<select class="select" id="schema_brand" name="' . esc_attr( $this->short_name . '[schema_brand]' ) . '">
				<option value="">-</option>' . "\n";
			if ( is_array( $taxonomies ) && $taxonomies !== array() ) {
				foreach ( $taxonomies as $tax ) {
					$sel = selected( strtolower( $tax->name ), $this->options['schema_brand'], false );
					echo '<option value="' . esc_attr( strtolower( $tax->name ) ) . '"' . $sel . '>' . esc_html( $tax->labels->name ) . "</option>\n";
				}
			}
			unset( $tax, $sel );
			echo '
			</select>
			<br class="clear"/>
			
			<label class="select" for="schema_manufacturer">' . sprintf( __( 'Manufacturer', 'yoast-woo-seo' ), $i ) . ':</label>
			<select class="select" id="schema_manufacturer" name="' . esc_attr( $this->short_name . '[schema_manufacturer]' ) . '">
				<option value="">-</option>' . "\n";
			if ( is_array( $taxonomies ) && $taxonomies !== array() ) {
				foreach ( $taxonomies as $tax ) {
					$sel = selected( strtolower( $tax->name ), $this->options['schema_manufacturer'], false );
					echo '<option value="' . esc_attr( strtolower( $tax->name ) ) . '"' . $sel . '>' . esc_html( $tax->labels->name ) . "</option>\n";
				}
			}
			unset( $tax, $sel );
			echo '
			</select>';

			$wpseo_options = WPSEO_Options::get_all();
			if ( $wpseo_options['breadcrumbs-enable'] === true ) {
				echo '
			<h2>' . __( 'Breadcrumbs', 'yoast-woo-seo' ) . '</h2>
			<p>' . sprintf( __( 'Both WooCommerce and WordPress SEO by Yoast have breadcrumbs functionality. The WP SEO breadcrumbs have a slightly higher chance of being picked up by search engines and you can configure them a bit more, on the %1$sInternal Links settings page%2$s. To enable them, check the box below and the WooCommerce breadcrumbs will be replaced.', 'yoast-woo-seo' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wpseo_internal-links' ) ) . '">', '</a>' ) . "</p>\n";
				$this->checkbox( 'breadcrumbs', __( 'Replace WooCommerce Breadcrumbs', 'yoast-woo-seo' ) );
			}
			else if ( $this->options['breadcrumbs'] === true ) {
				echo '<input name="' . esc_attr( $this->short_name . '[breadcrumbs]' ) . '" value="on" />' . "\n";
			}

			echo '
			<br class="clear"/>
			<h2>' . __( 'Admin', 'yoast-woo-seo' ) . '</h2>
			<p>' . __( 'Both WooCommerce and WordPress SEO by Yoast add columns to the product page, to remove all but the SEO score column from Yoast on that page, check this box.', 'yoast-woo-seo' ) . "</p>\n";
			$this->checkbox( 'hide_columns', __( 'Remove WordPress SEO columns', 'yoast-woo-seo' ) );

			echo '
			<br class="clear"/>
			<p>' . __( 'Both WooCommerce and WordPress SEO by Yoast add metaboxes to the edit product page, if you want WooCommerce to be above WordPress SEO, check the box.', 'yoast-woo-seo' ) . "</p>\n";
			$this->checkbox( 'metabox_woo_top', __( 'Move WooCommerce Up', 'yoast-woo-seo' ) );
		}
		else {
			echo '<input name="' . esc_attr( $this->short_name . '[short_form]' ) . '" value="on" />' . "\n";
		}

		echo '<br class="clear"/>';
		
		// Submit button and debug info
		if ( ! isset( $GLOBALS['wpseo_admin_pages'] ) ) {
			$GLOBALS['wpseo_admin_pages'] = new WPSEO_Admin_Pages;
		}
		$GLOBALS['wpseo_admin_pages']->admin_footer( true, false );
	}

	/**
	 * Simple helper function to show a checkbox.
	 *
	 * @param string $id    The ID and option name for the checkbox
	 * @param string $label The label for the checkbox
	 */
	function checkbox( $id, $label ) {
		$current = false;
		if ( isset( $this->options[$id] ) && $this->options[$id] === true ) {
			$current = 'on';
		}

		echo '<input class="checkbox" type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $this->short_name . '[' . $id . ']' ) . '" value="on" ' . checked( $current, 'on', false ) . '> ';
		echo '<label for="' . esc_attr( $id ) . '" class="checkbox">' . $label . '</label> ';
	}

	/**
	 * Adds a bit of JS that moves the meta box for WP SEO below the WooCommerce box.
	 *
	 * @since 1.0
	 */
	function footer_js() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				// Show WooCommerce box before WP SEO metabox
				if ($('#woocommerce-product-data').length > 0 && $('#wpseo_meta').length > 0) {
					$('#woocommerce-product-data').insertBefore($('#wpseo_meta'));
				}
			});
		</script>
	<?php
	}

	/**
	 * Clean up the columns in the edit products page.
	 *
	 * @since 1.0
	 *
	 * @param array $columns
	 *
	 * @return mixed
	 */
	function column_heading( $columns ) {
		unset( $columns['wpseo-title'], $columns['wpseo-metadesc'], $columns['wpseo-focuskw'] );

		return $columns;
	}

	/**
	 * Output WordPress SEO crafted breadcrumbs, instead of WooCommerce ones.
	 *
	 * @since 1.0
	 */
	function woo_wpseo_breadcrumbs() {
		if ( ! isset( $GLOBALS['wpseo_bc'] ) || ! is_object( $GLOBALS['wpseo_bc'] ) ) {
			$GLOBALS['wpseo_bc'] = new WPSEO_Breadcrumbs;
		}
		$GLOBALS['wpseo_bc']->breadcrumb( '<nav class="woocommerce-breadcrumb">', '</nav>' );
	}

	/**
	 * Make sure product variations and shop coupons are not included in the XML sitemap
	 *
	 * @since 1.0
	 *
	 * @param bool   $bool    Whether or not to include this post type in the XML sitemap
	 * @param string $post_type
	 *
	 * @return bool
	 */
	function xml_sitemap_post_types( $bool, $post_type ) {
		if ( $post_type === 'product_variation' || $post_type === 'shop_coupon' ) {
			return true;
		}
		return $bool;
	}

	/**
	 * Make sure product attribute taxonomies are not included in the XML sitemap
	 *
	 * @since 1.0
	 *
	 * @param bool   $bool    Whether or not to include this post type in the XML sitemap
	 * @param string $taxonomy
	 *
	 * @return bool
	 */
	function xml_sitemap_taxonomies( $bool, $taxonomy ) {
		if ( $taxonomy === 'product_type' || $taxonomy === 'product_shipping_class' || $taxonomy === 'shop_order_status' ) {
			return true;
		}

		if ( substr( $taxonomy, 0, 3 ) === 'pa_' ) {
			return true;
		}

		return $bool;
	}

	/**
	 * Adds the other product images to the OpenGraph output
	 *
	 * @since 1.0
	 */
	function og_enhancement() {
		if ( ! is_singular( 'product' ) || ! function_exists( 'get_product' ) ) {
			return;
		}

		global $wpseo_og;

		$product = get_product( get_the_ID() );
		if ( ! is_object( $product ) ) {
			return;
		}

		$img_ids = $product->get_gallery_attachment_ids();

		if ( is_array( $img_ids ) && $img_ids !== array() ) {
			foreach ( $img_ids as $img_id ) {
				$img_url = wp_get_attachment_url( $img_id );
				$wpseo_og->image_output( $img_url );
			}
		}

		if ( $this->options['schema_brand'] !== '' ) {
			$terms = get_the_terms( get_the_ID(), $this->options['schema_brand'] );
			if ( is_array( $terms ) && count( $terms ) > 0 ) {
				$term = array_shift( array_values( $terms ) );
				echo '<meta property="og:brand" content="' . esc_attr( $term->name ) . "\"/>\n";
			}
		}
		echo '<meta property="og:price:amount" content="' . esc_attr( $product->get_price() ) . "\"/>\n";
		echo '<meta property="og:price:currency" content="' . esc_attr( get_woocommerce_currency() ) . "\"/>\n";
		if ( $product->is_in_stock() ) {
			echo '<meta property="og:price:availability" content="instock"/>' . "\n";
		}
	}

	/**
	 * Make sure the OpenGraph description is put out.
	 *
	 * @since 1.0
	 *
	 * @param string $desc The current description, will be overwritten if we're on a product page.
	 *
	 * @return string
	 */
	function og_desc_enhancement( $desc ) {
		if ( is_singular( 'product' ) ) {
			$desc = strip_tags( get_the_excerpt() );
		}

		if ( is_product_taxonomy() ) {
			$term_desc = term_description();
			if ( ! empty( $term_desc ) ) {
				$desc = trim( strip_tags( $term_desc ) );
			}
		}
		$desc = strip_shortcodes( $desc );

		return $desc;
	}


	/**
	 * Keep old behaviour of getting the twitter domain in a different way than in WPSEO, but prevent duplicate
	 * twitter:domain meta tags
	 *
	 * @param	string	$domain
	 */
	function filter_twitter_domain( $domain ) {
		return get_bloginfo( 'site_name' );
	}


	/**
	 * Output the extra data for the Twitter Card
	 *
	 * @since 1.0
	 */
	function twitter_enhancement() {
		if ( ! is_singular( 'product' ) || ! function_exists( 'get_product' ) ) {
			return;
		}

		$product = get_product( get_the_ID() );

		$product_atts = array();

		$i = 1;
		while ( $i < 3 ) {
			switch ( $this->options['data' . $i . '_type'] ) {
				case 'stock':
					$product_atts['label' . $i] = __( 'In stock', 'woocommerce' );
					$product_atts['data' . $i]  = ( $product->is_in_stock() ) ? __( 'Yes' ) : __( 'No' );
					break;

				case 'category':
					$product_atts['label' . $i] = __( 'Category', 'woocommerce' );
					$product_atts['data' . $i]  = strip_tags( get_the_term_list( get_the_ID(), 'product_cat', '', ', ' ) );
					break;

				case 'price':
					$product_atts['label' . $i] = __( 'Price', 'woocommerce' );
					$product_atts['data' . $i]  = get_woocommerce_currency_symbol() . ' ' . $product->get_price();
					break;

				default:
					$tax                        = get_taxonomy( $this->options['data' . $i . '_type'] );
					$product_atts['label' . $i] = $tax->labels->name;
					$product_atts['data' . $i]  = strip_tags( get_the_term_list( get_the_ID(), $tax->name, '', ', ' ) );
					break;
			}
			$i ++;
		}

		foreach ( $product_atts as $label => $value ) {
			echo '<meta name="' . esc_attr( 'twitter:' . $label ) . '" content="' . esc_attr( $value ) . '"/>' . "\n";
		}
	}

	/**
	 * Return 'product' when current page is, well... a product.
	 *
	 * @since 1.0
	 *
	 * @param string $type Passed on without changing if not a product.
	 *
	 * @return string
	 */
	function return_type_product( $type ) {
		if ( is_singular( 'product' ) ) {
			return 'product';
		}
		return $type;
	}

	/**
	 * Filter the output of attributes and add schema.org attributes where possible
	 *
	 * @since 1.0
	 *
	 * @param string $text      The text of the attribute.
	 * @param array  $attribute The array containing the attributes.
	 *
	 * @return string
	 */
	function schema_filter( $text, $attribute ) {
		if ( 1 == $attribute['is_taxonomy'] ) {
			if ( $this->options['schema_brand'] === $attribute['name'] ) {
				return str_replace( '<p', '<p itemprop="brand"', $text );
			}
			if ( $this->options['schema_manufacturer'] === $attribute['name'] ) {
				return str_replace( '<p', '<p itemprop="manufacturer"', $text );
			}
		}
		return $text;
	}
}

/**
 * Throw an error if WordPress SEO is not installed.
 *
 * @since 1.0.1
 */
function yoast_wpseo_woocommerce_missing_error() {
	echo '<div class="error"><p>' . sprintf( __( 'Please %sinstall &amp; activate WordPress SEO by Yoast%s and then enable its XML sitemap functionality to allow the WooCommerce SEO module to work.', 'yoast-woo-seo' ), '<a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&type=term&s=wordpress+seo&plugin-search-input=Search+Plugins' ) ) . '">', '</a>' ) . '</p></div>';
}

/**
 * Throw an error if WordPress SEO is not installed.
 *
 * @since 1.0.1
 */
function yoast_wpseo_woocommerce_wordpress_upgrade_error() {
	echo '<div class="error"><p>' . __( 'Please upgrade WordPress to the latest version to allow WordPress and the WooCommerce SEO module to work properly.', 'yoast-woo-seo' ) . '</p></div>';
}

/**
 * Throw an error if WordPress SEO is not installed.
 *
 * @since 1.0.1
 */
function yoast_wpseo_woocommerce_upgrade_error() {
	echo '<div class="error"><p>' . __( 'Please upgrade the WordPress SEO plugin to the latest version to allow the WooCommerce SEO module to work.', 'yoast-woo-seo' ) . '</p></div>';
}


/**
 * Initialize the plugin class, to make sure all the required functionality is loaded, do this after plugins_loaded.
 *
 * @since 1.0
 */
function initialize_yoast_woocommerce_seo() {
	global $yoast_woo_seo;
	global $wp_version;

	if ( ! version_compare( $wp_version, '3.5', '>=' ) ) {
		add_action( 'all_admin_notices', 'yoast_wpseo_woocommerce_wordpress_upgrade_error' );
	}
	else if ( defined( 'WPSEO_VERSION' ) ) {
		if ( version_compare( WPSEO_VERSION, '1.5', '>=' ) ) {
			$yoast_woo_seo = new Yoast_WooCommerce_SEO();
		}
		else {
			add_action( 'all_admin_notices', 'yoast_wpseo_woocommerce_upgrade_error' );
		}
	}
	else {
		add_action( 'all_admin_notices', 'yoast_wpseo_woocommerce_missing_error' );
	}
}

add_action( 'plugins_loaded', 'initialize_yoast_woocommerce_seo', 20 );