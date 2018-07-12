<?php
/*
Plugin Name: HTML Import 3
Plugin URI: http://stephanieleary.com/code/wordpress/html-import/
Description: Imports well-formed static HTML files into WordPress posts or pages. Supports Dreamweaver templates and Word HTML cleanup. Visit the settings page to get started. See the <a href="http://stephanieleary.com/code/wordpress/html-import-2/user-guide/">User Guide</a> for details.
Version: 3.0a
Author: Stephanie Leary
Author URI: http://stephanieleary.com/
License: GPL 2
Text Domain: import-html-pages
*/

require_once ( 'html-importer.php' );
require_once ( 'html-import-options.php' );

// i18n
load_plugin_textdomain( 'html_import', false, plugin_dir_path( __FILE__ ) . '/languages' );

// Option page styles
function html_import_css() {
    wp_register_style( 'html-import-css', plugins_url( 'html-import-styles.css', __FILE__ ) );

}
function add_html_import_styles() {
    wp_enqueue_style( 'html-import-css' );
}
add_action( 'admin_init', 'html_import_css' );

// Option page scripts
function html_import_scripts() {
	wp_enqueue_script( 'jquery-ui-tabs' );
	wp_enqueue_script( 'html-import-tabs', plugins_url( 'js/tabs.js', __FILE__ ), array( 'jquery', 'jquery-ui-tabs' ) );
}

// set default options 
function html_import_set_defaults() {
	$options = html_import_get_options();
	add_option( 'html_import', $options, '', 'no' );
}
register_activation_hook( __FILE__, 'html_import_set_defaults' );

//register our settings
function register_html_import_settings() {
	register_setting( 'html_import', 'html_import', 'html_import_validate_options' );
}

// when uninstalled, remove option
function html_import_remove_options() {
	delete_option( 'html_import' );
}
register_uninstall_hook( __FILE__, 'html_import_remove_options' );
// for testing only
// register_deactivation_hook( __FILE__, 'html_import_remove_options' );

function html_import_add_pages() {
// Add option page to admin menu
	$pg = add_options_page( __( 'HTML Import', 'import-html-pages' ), __( 'HTML Import', 'import-html-pages' ), 'manage_options', basename( __FILE__ ), 'html_import_options_page' );
	
// Add styles and scripts
	add_action( 'admin_print_styles-'.$pg, 'add_html_import_styles' );
	add_action( 'admin_print_scripts-'.$pg, 'html_import_scripts' );

// register setting
	add_action( 'admin_init', 'register_html_import_settings' );
		
// Help screen 
	$text = '<p>'.sprintf( __( 'This is a complicated importer with lots of options. If you have never used this importer before, you should take a look at the <a href="%s">User Guide</a>.', 'import-html-pages' ), 'http://sillybean.net/downloads/html-import/user-guide.html' ).'</p>';
	$text .= '<p>'.__( "You need to look through the first five tabs and save your settings before you run the importer. The sixth ( Tools ) contains links to some tools that are helpful after you've imported.", 'import-html-pages' ).'</p>';
	
	$text .= '<h3>'.__( 'Tips', 'html-import-pages' )."</h3>
    <ol>
		<li>" . __( "If there is already some content in this site, you should back up your database before you import.", 'import-html-pages' )."</li>        
		<li>" . __( "Before you import, deactivate any crosspost or notification plugins.", 'import-html-pages' )."</li>
		<li>" . __( "Try uploading a single file before you run the importer on the whole directory. Check the imported page and see whether you need to adjust your content and/or title settings.", 'import-html-pages' )."</li>
		<li>" . __( "Need to import both posts and pages? Run the importer on a subdirectory ( e.g. 'news' ), then add the subdirectory name to the list of skipped directories and run the importer again on the parent directory.", 'import-html-pages' )."</li>
    </ol>";
	$text .= '<h3>' . __( 'More Help', 'import-html-pages' ) . '</h3>';

	$text .= '<ul>';
	$text .= '<li><a href="http://sillybean.net/downloads/html-import/user-guide.html">' . __( 'User Guide', 'import-html-pages' ) . '</a></li>';
	$text .= '<li><a href="http://sillybean.net/code/wordpress/html-import-2/">' . __( 'Plugin Home Page', 'import-html-pages' ) . '</a></li>';
	$text .= '<li><a href="http://forum.sillybean.net/forums/forum/html-import-2/">' . __( 'Support Forum', 'import-html-pages' ) . '</a></li>';
	$text .= '</ul>';
	
//	add_contextual_help( $pg, $text );
}
add_action( 'admin_menu', 'html_import_add_pages' );

// Add link to options page from plugin list
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'html_import_plugin_actions' );
function html_import_plugin_actions( $links ) {
	$new_links = array();
	$new_links[] = sprintf( '<a href="options-general.php?page=html-import.php">%s</a>', __( 'Settings', 'html-import' ) );
	return array_merge( $new_links, $links );
}


function html_import_options_io_page() {

	$options = get_option( 'html_import' ); ?>
	<div class="wrap">
		<h2><?php _e('HTML Import Settings Import/Export'); ?></h2>

		<div class="metabox-holder">
			<div class="postbox one-half first">
				<h3><span><?php _e( 'Export Settings' ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Save your HTML Import options as a .json file.' ); ?></p>
					<form method="post">
						<p><input type="hidden" name="html_import_settings_io_action" value="export_settings" /></p>
						<p>
							<?php wp_nonce_field( 'html_import_settings_io', 'html_import_settings_io' ); ?>
							<?php submit_button( __( 'Save' ), 'secondary', 'submit', false ); ?>
						</p>
					</form>
				</div><!-- .inside -->
			</div><!-- .postbox -->

			<div class="postbox one-half">
				<h3><span><?php _e( 'Import Settings' ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Restore HTML Import options from a .json file.' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<p>
							<input type="file" name="import_file"/>
						</p>
						<p>
							<input type="hidden" name="html_import_settings_io_action" value="import_settings" />
							<?php wp_nonce_field( 'html_import_settings_io_nonce', 'html_import_settings_io_nonce' ); ?>
							<?php submit_button( __( 'Restore' ), 'secondary', 'submit', false ); ?>
						</p>
					</form>
				</div><!-- .inside -->
			</div><!-- .postbox -->
		</div><!-- .metabox-holder -->

	</div><!--end .wrap-->
	<?php
}

/**
 * Process a settings export that generates a .json file of the shop settings
 */
function html_import_do_settings_export() {

	if( empty( $_POST['html_import_settings_io_action'] ) || 'export_settings' !== $_POST['html_import_settings_io_action'] )
		return;

	if( ! wp_verify_nonce( $_POST['html_import_settings_io'], 'html_import_settings_io' ) )
		return;

	if( ! current_user_can( 'manage_options' ) )
		return;

	$settings = get_option( 'html_import' );

	ignore_user_abort( true );

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=html-import-settings-' . date( 'd-m-Y' ) . '.json' );
	header( "Expires: 0" );

	echo json_encode( $settings );
	exit;
}
add_action( 'admin_init', 'html_import_do_settings_export' );

/**
 * Process a settings import from a json file
 */
function html_import_do_settings_import() {

	if( empty( $_POST['html_import_settings_io_action'] ) || 'import_settings' !== $_POST['html_import_settings_io_action'] )
		return;

	if( ! wp_verify_nonce( $_POST['html_import_settings_io_nonce'], 'html_import_settings_io_nonce' ) )
		return;

	if( ! current_user_can( 'manage_options' ) )
		return;

	$extension = end( explode( '.', $_FILES['import_file']['name'] ) );

	if( $extension != 'json' ) {
		wp_die( __( 'Please upload a valid .json file' ) );
	}

	$import_file = $_FILES['import_file']['tmp_name'];

	if( empty( $import_file ) ) {
		wp_die( __( 'Please upload a file to import' ) );
	}

	// Retrieve the settings from the file and convert the json object to an array.
	$settings = (array) json_decode( file_get_contents( $import_file ) );

	update_option( 'html_import', $settings );

	wp_safe_redirect( admin_url( 'options-general.php?page=html-import' ) ); exit;

}
add_action( 'admin_init', 'html_import_do_settings_import' );