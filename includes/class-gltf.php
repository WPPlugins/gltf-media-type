<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://goldsounds.com
 * @since      1.0.0
 *
 * @package    Gltf
 * @subpackage Gltf/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Gltf
 * @subpackage Gltf/includes
 * @author     Daniel Walmsley <goldsounds@gmail.com>
 */
class Gltf {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Gltf_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	// @see https://github.com/KhronosGroup/glTF/tree/master/specification/1.0#mimetypes
	// @see https://github.com/KhronosGroup/glTF/tree/master/extensions/Khronos/KHR_binary_glTF#mime-type
	protected $gltf_mime_types = array( 
		'glb'   => 'model/gltf.binary',
		'gltf'  => 'model/gltf+json',
		'bin'   => 'application/octet-stream',
		'glsl'  => 'text/plain',
		'vert'  => 'text/x-glsl',
		'vsh'   => 'text/x-glsl',
		'gsh'   => 'text/x-glsl',
		'frag'  => 'text/x-glsl',
		'glsl'  => 'text/x-glsl',
		'shader'=> 'text/x-glsl',
	);

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->plugin_name = 'gltf';
		$this->version = '1.0.0';

		$this->load_dependencies();
		$this->set_locale();
		$this->register_media();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Gltf_Loader. Orchestrates the hooks of the plugin.
	 * - Gltf_i18n. Defines internationalization functionality.
	 * - Gltf_Admin. Defines all hooks for the admin area.
	 * - Gltf_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-gltf-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-gltf-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-gltf-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-gltf-public.php';

		$this->loader = new Gltf_Loader();

	}

	private function register_media() {
		add_filter( 'upload_mimes', array( $this, 'upload_mime_types' ) );
		add_filter( 'wp_mime_type_icon', array( $this, 'mime_types_icons' ), 10, 3 );
		add_shortcode( 'gltf_model', array( $this, 'model_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_model_render_script' ) );
		add_action( 'init', array( $this, 'register_scene_post_type' ) );
		add_action( 'add_meta_boxes_gltf_scene', array( $this, 'add_scene_metaboxes' ) );
		add_action( 'save_post_gltf_scene', array( $this, 'save_scene_metaboxes' ), 10, 3 );
		add_filter( 'single_template', array( $this, 'register_scene_template' ) );

		// remove the sidebars before rendering a scene
		add_filter( 'body_class', function( $classes ) {
			global $post;
			
			if ( $post && $post->post_type == 'gltf_scene' && in_array( 'has-sidebar', $classes ) ) {
				$classes = array_diff( $classes, array('has-sidebar') );
			}
			
			return $classes;
		}, 99, 1 );
	}

	public function upload_mime_types( $mimes ) {
		foreach( $this->gltf_mime_types as $ext => $type ) {
			error_log("permitting $ext");
			$mimes[ $ext ] = $type;
		}

		return $mimes;
	}

	public function mime_types_icons( $icon, $mime, $post_id ) {
		if ( in_array( $mime, array_values( $this->gltf_mime_types ) ) ) {
			return plugin_dir_url( dirname( __FILE__ ) ) . 'admin/images/model-icon.png';
		}
	}

	public function model_shortcode( $atts ) {
		$a = shortcode_atts( array(
			'url' => '',
			'scale' => '1.0'
		), $atts );

		return '<div class="gltf-model" style="height: 300px" data-scale="'.htmlspecialchars($a['scale']).'" data-model="'.htmlspecialchars($a['url']).'"></div>';
	}

	public function register_scene_post_type() {
		register_post_type( 'gltf_scene',
			array(
				'labels' => array(
					'name'          => __( 'Scenes' ),
					'singular_name' => __( 'Scene' )
				),
				'public'             => true,
				'has_archive'        => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'rewrite'            => array('slug' => 'scenes'),
				'menu_icon'          => 'dashicons-star-filled',
				'rest_base'          => 'scene',
				'rest_controller_class' => 'WP_REST_Posts_Controller'
			)
		);
	}

	function add_scene_metaboxes() {
		add_meta_box( 'gltf_select_scene_model', __( 'Select Scene Model', 'gltf-media-type' ), array( $this, 'select_scene_model_callback' ), 'gltf_scene' );
	}

	function select_scene_model_callback( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'gltf_nonce' );

		// Get WordPress' media upload URL
		$upload_link = esc_url( get_upload_iframe_src( 'image', $post->ID ) );

		// See if there's a media id already saved as post meta
		$main_model_id = get_post_meta( $post->ID, '_gltf_main_model', true );
		$main_model_scale = get_post_meta( $post->ID, '_gltf_main_model_scale', true );
		$main_model_scale = $main_model_scale ? $main_model_scale : 1.0;

		// Get the image src
		$main_model_url = wp_get_attachment_url( $main_model_id );

		// For convenience, see if the array is valid
		$main_model_is_set = !! $main_model_url;
		?>

		<!-- Model container -->
		<div class="gltf-main-model-container">
			<?php if ( $main_model_is_set ) : ?>
				<div class="gltf-model" data-model="<?php echo $main_model_url; ?>" data-scale="<?php echo $main_model_scale; ?>" style="width: 300px; height: 300px;"></div>
			<?php endif; ?>
		</div>

		<!-- Add & remove mode links -->
		<p class="hide-if-no-js">
			<a class="upload-main-model <?php if ( $main_model_is_set  ) { echo 'hidden'; } ?>" 
			   href="<?php echo $upload_link ?>">
				<?php _e('Set main 3D model') ?>
			</a>
			<a class="delete-main-model <?php if ( ! $main_model_is_set  ) { echo 'hidden'; } ?>" 
			  href="#">
				<?php _e('Remove this 3D model') ?>
			</a>
		</p>

		<!-- A hidden input to set and post the chosen model id -->
		<input class="main-model-id" name="main-model-id" type="hidden" value="<?php echo esc_attr( $main_model_id ); ?>" />

		<p>
			<label for="main-model-scale">
				<?php _e( 'Model Scale', 'gltf-media-type '); ?>
				<input class="main-model-scale" name="main-model-scale" value="<?php echo esc_attr( $main_model_scale ); ?>" />
			</label>
		</p>
		<?php
	}

	public function save_scene_metaboxes( $post_id, $post, $update ) {
		$is_autosave = wp_is_post_autosave( $post_id );
		$is_revision = wp_is_post_revision( $post_id );
		$is_valid_nonce = ( isset( $_POST[ 'gltf_nonce' ] ) && wp_verify_nonce( $_POST[ 'gltf_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
	 
		// Exits script depending on save status
		if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
			return;
		}

		if( isset( $_POST[ 'main-model-id' ] ) ) {
			update_post_meta( $post_id, '_gltf_main_model', $_POST[ 'main-model-id' ] );
		}
		if( isset( $_POST[ 'main-model-scale' ] ) ) {
			update_post_meta( $post_id, '_gltf_main_model_scale', $_POST[ 'main-model-scale' ] );
		}
	}

	public function register_scene_template( $single ) {
		global $wp_query, $post;

		/* Checks for single template by post type */
		if ($post->post_type == 'gltf_scene'){
			return dirname( dirname( __FILE__ ) ) . '/public/templates/single-gltf_scene.php';
		}

		return $single;
	}

	public function enqueue_model_render_script() {
		global $post;
		if( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'gltf_model') ) {
			wp_enqueue_script( 'gltf-model', plugin_dir_url( dirname( __FILE__ ) ) . 'js/public.js', array( 'jquery', 'wp-api' ), $this->version, false );
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Gltf_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Gltf_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Gltf_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Gltf_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Gltf_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
