<?php

function html_import_get_options() {
	$defaults = array(
		'get_path' => '',
		'follow_mode' => 2,		
		'preserve_slugs' => 1,
		'status' => 'publish',
		'root_parent' => 0,
		'type' => 'page',
		'timestamp' => 'filemtime',
		'content_selector' => 'body',
		'clean_content' => 1,
		'import_images' => 1,
		'import_documents' => 0,
		'fix_links' => 1,
		'title_selector' => 'title',		
		'remove_from_title' => '',
		'title_inside' => 0,
		'meta_desc' => 1,
		'user' => 0,
		'page_template' => 0,
		'firstrun' => 1,
		'date_selector' => '__filemtime',
		'thumbnail_selector' => '',
		'customfield_name' => array(),
		'customfield_striptags' => array(),
		'customfield_selector' => array()
	 );
	$options = get_option( 'html_import' );
	return array_merge( $defaults, (array) $options );
}

function html_import_options_page() { ?>
	<div class="wrap">
	<h2><?php _e( 'HTML Import Settings', 'import-html-pages' ); ?></h2>
		<form method="post" id="html_import" action="options.php">
			<?php 
			settings_fields( 'html_import' );
			get_settings_errors( 'html_import' );	
			$options = html_import_get_options();
			$msg .= '<pre>'. print_r( $options, true ) .'</pre>';
			echo $msg;
			$active_tab = 'html-import-settings-files';
			if ( isset( $_REQUEST['tab'] ) ) {
			    $active_tab = $_REQUEST[ 'tab' ];
			}
			?>

			<h2 class="nav-tab-wrapper">
	            <a href="<?php echo add_query_arg( 'tab', 'html-import-settings-files' ); ?>" class="nav-tab  <?php echo $active_tab == 'html-import-settings-files' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Plugin Settings' ); ?></a>
				<a href="<?php echo add_query_arg( 'tab', 'html-import-settings-redirects' ); ?>" class="nav-tab  <?php echo $active_tab == 'html-import-settings-redirects' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Generate Redirects' ); ?></a>
	            <a href="<?php echo add_query_arg( 'tab', 'html-import-settings-export' ); ?>" class="nav-tab   <?php echo $active_tab == 'html-import-settings-export' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Import/Export Plugin Settings' ); ?></a>
	        </h2>
			
		<?php
		if ( $options['firstrun'] === true ) {
		echo '<p>'.sprintf( __( 'Welcome to HTML Import! This is a complicated importer with many options. Please look through all the sections on this page before running your import.', 'import-html-pages' ), 'options-general.php?page=html-import.php' ).'</p>'; 
		}
		?>		


		<!-- FILES -->
		<?php if ( $active_tab == 'html-import-settings-files' ) : ?>
		<fieldset id="html-import-settings-files">
		<h3><?php _e( "Files", 'import-html-pages' ); ?></h3>				
			<table class="form-table ui-tabs-panel" id="files">
		        <tr valign="top">
			        <th scope="row"><?php _e( "Path to get", 'import-html-pages' ); ?></th>
			        <td><p><label><input type="text" name="html_import[get_path]" id="get_path" placeholder="http://example.com"
							 	value="<?php echo esc_attr( $options['get_path'] ); ?>" class="widefloat"  />
							</label>
						</p>
					</td>
		        </tr>	
		
				<tr valign="top">
			        <th scope="row"><?php _e( "Domains to crawl", 'import-html-pages' ); ?></th>
			        <td><p>
						<label><input name="html_import[follow_mode]" id="follow_mode" type="radio" value="1" 
							<?php checked( $options['follow_mode'], '1' ); ?> />
							<?php _e( 'This top-level domain, including subdomains (www.example.com and foo.example.com)', 'import-html-pages' ); ?> </label>
						<br />
						<label><input name="html_import[follow_mode]" id="follow_mode" type="radio" value="2" 
							<?php checked( $options['follow_mode'], '1' ); ?> />
							<?php _e( 'This top-level domain, not including subdomains (www.example.com but not foo.example.com)', 'import-html-pages' ); ?> </label>
						<label><input name="html_import[follow_mode]" id="follow_mode" type="radio" value="3" 
							<?php checked( $options['follow_mode'], '3' ); ?> />
							<?php _e( 'This path and its subdirectories (www.example.com/foo and www.example.com/foo/bar but not www.example.com)', 'import-html-pages' ); ?> </label>
						</p>
					</td>
		        </tr>
		    </table>
		</fieldset>
		
		<!-- CONTENT -->	
		<fieldset id="html-import-settings-content">
		<h3><?php _e( "Content", 'import-html-pages' ); ?></h3>				
			<table class="form-table ui-tabs-panel" id="content">
				<tr valign="top">
			        <th scope="row">
						<label for="html_import[content_selector]"><?php _e( "Content selector", 'import-html-pages' ); ?></label>
					</th>
			        <td>
						<input type="text" name="html_import[content_selector]" value="<?php echo esc_attr( $options['content_selector'] ); ?>" class="widefloat" placeholder="div#main_content" />
  					</td>
		        </tr>
		
		<?php
		/*
		Dreamweaver template reference:
		<!-- InstanceBeginEditable name="foo" -->
		<!-- InstanceEndEditable -->	
		/**/
		?>

				<tr>
				<th><?php _e( "More content options", 'import-html-pages' ); ?></th>
				<td>
					<label><input name="html_import[import_images]" id="import_images"  type="checkbox" value="1" 
						<?php checked( $options['import_images'], '1' ); ?> /> <?php _e( "Import linked images", 'import-html-pages' ); ?></label>
				</td>
				</tr>
				<tr>
				<th></th>
				<td>
					<label><input name="html_import[import_documents]" id="import_documents" value="1" type="checkbox" <?php checked( $options['import_documents'] ); ?> class="toggle" /> 
						 <?php _e( "Import linked documents", 'import-html-pages' ); ?></label>
				</td>
				</tr>
				
				<tr>
				<th></th>
				<td>
					<label><input name="html_import[fix_links]" id="fix_links" value="1" type="checkbox" <?php checked( $options['fix_links'] ); ?> /> 
						 <?php _e( "Update internal links", 'import-html-pages' ); ?></label>
				</td>
				</tr>
				<th></th>
				<td>
					<label><input name="html_import[meta_desc]" id="meta_desc" value="1" type="checkbox" <?php checked( $options['meta_desc'] ); ?> /> 
						 <?php _e( "Use meta description as excerpt", 'import-html-pages' ); ?></label>
				</td>
				</tr>
				
				<tr>
				<th></th>
				<td>
					<label><input name="html_import[clean_content]" id="clean_content" type="radio" value="1" 
						<?php checked( $options['clean_content'], '1' ); ?> />
						<?php _e( sprintf( 'Clean HTML with <a href="%s">wp_kses_post()</a>', 'https://developer.wordpress.org/reference/functions/wp_kses_post/' ), 'import-html-pages' ); ?> </label>
					<br />
					<label><input name="html_import[clean_content]" id="clean_content" type="radio" value="0" 
						<?php checked( $options['clean_content'], '0' ); ?> />
						<?php _e( sprintf( 'Clean HTML with <a href="%s">wp_kses()</a>', 'https://developer.wordpress.org/reference/functions/wp_kses/' ), 'import-html-pages' ); ?> </label>
				</td>
				</tr>
			
			</table>
		</fieldset>
	
		<!-- TITLE AND META -->
		<fieldset id="html-import-settings-meta">
		<h3><?php _e( "Title &amp; Metadata", 'import-html-pages' ); ?></h3>				
		<table class="form-table ui-tabs-panel" id="title">
			<tr valign="top" id="titleselect">
		        <th scope="row"><label for="html_import[title_selector]"><?php _e( "Title selector", 'import-html-pages' ); ?></label></th>
		        <td>
			
		            
		            <input type="text" name="html_import[title_selector]" id="title_selector" value="<?php echo esc_attr( $options['title_selector'] ); ?>" class="widefloat" placeholder="title" />

				</td>
	        </tr>
	
			<tr valign="top">
				<th><?php _e( "Phrase to remove from page title: ", 'import-html-pages' ); ?></th>
				<td>
					<label><input type="text" name="html_import[remove_from_title]" id="remove_from_title" value="<?php echo esc_attr( $options['remove_from_title'] ); ?>" class="widefloat" />  </label><br />
					<span class="description"><?php _e( "Any common title phrase ( such as the site name, which most themes will print automatically )", 'import-html-pages' ); ?></span>
				</td>
			</tr>
			
			<tr>
			<th><?php _e( "Title position", 'import-html-pages' ); ?></th>
			<td>
				<label><input name="html_import[title_inside]" id="title_inside"  type="checkbox" value="1" 
					<?php checked( $options['title_inside'], '1' ); ?> /> <?php _e( "The title is inside the content area and should be removed from the post body", 'import-html-pages' ); ?></label>
			</td>
			</tr>
			<tr>
			
				<tr valign="top">
			        <th scope="row"><?php _e( "Import files as", 'import-html-pages' ); ?></th>
			        <td>
						<?php
						// support all public post types
						$typeselect = '';
						$post_types = get_post_types( array( 'public' => true ), 'objects' );
						foreach ( $post_types as $post_type ) {
							if ( $post_type->name != 'attachment' ) {
								$typeselect .= '<label><input name="html_import[type]" type="radio" value="' . esc_attr( $post_type->name ) . '" '.checked( $options['type'], $post_type->name, false );
								$typeselect .= '> '.esc_html( $post_type->labels->name ).'</label> &nbsp;&nbsp;';
							}
						}
						echo $typeselect; 
						?>
					</td>
		        </tr>
				<tr>
				<th><?php _e( "Set status to", 'import-html-pages' ); ?></th>
				<td>
					<select name="html_import[status]" id="status">
				    	<option value="publish" <?php selected( 'publish', $options['status'] ); ?>><?php _e( "publish", 'import-html-pages' ); ?></option>
				        <option value="draft" <?php selected( 'draft', $options['status'] ); ?>><?php _e( "draft", 'import-html-pages' ); ?></option>
				        <option value="private" <?php selected( 'private', $options['status'] ); ?>><?php _e( "private", 'import-html-pages' ); ?></option>
				        <option value="pending" <?php selected( 'pending', $options['status'] ); ?>><?php _e( "pending", 'import-html-pages' ); ?></option>
				    </select>
				</td>
				</tr>
				<tr>
				<th><?php _e('Date selector', 'import-html-pages'); ?></th>
				<td>
					<input type="text" name="html_import[date_selector]" id="date_selector" value="<?php echo esc_attr( $options['date_selector'] ); ?>" placeholder="span.date" />
				<p class="description"><?php _e( 'Enter __now, __filemtime, DC.date, or a CSS/jQuery selector.', 'import-html-pages' ); ?>
				</td>
				</tr>
				
				<tr>
				<th><?php _e('Featured image selector', 'import-html-pages'); ?></th>
				<td>
					<input type="text" name="html_import[thumbnail_selector]" id="thumbnail_selector" value="<?php echo esc_attr( $options['thumbnail_selector'] ); ?>" placeholder="div#content img:firstchild" />
				</td>
				</tr>
				<tr>
				<th><?php _e( "Set author to", 'import-html-pages' ); ?></th>
				<td>
					<?php wp_dropdown_users( array( 'selected' => $options['user'], 'name' => 'html_import[user]', 'who' => 'authors' ) ); ?>
				</td>
				</tr>

				<tr id="hierarchy" <?php if ( !is_post_type_hierarchical( $options['type'] ) ) echo "style=display:none;"; ?>>
				<th><?php _e( "Import pages as children of: ", 'import-html-pages' ); ?></th>
				<td>
			        <?php 
			            $pages = wp_dropdown_pages( array( 'echo' => 0, 'selected' => $options['root_parent'], 'name' => 'html_import[root_parent]', 'show_option_none' => __( 'None ( top level )', 'import-html-pages' ), 'sort_column'=> 'menu_order, post_title' ) );
			            if ( empty( $pages ) ) $pages = "<select name=\"root_parent\"><option value=\"0\">".__( 'None ( top level )', 'import-html-pages' )."</option></select>";
			            echo $pages;
			        ?>
				</td>
				</tr>

				<tr id="page-template">
				<th><?php _e( "Page template for imported posts: ", 'import-html-pages' ); ?></th>
				<td>
			        <select name="html_import[page_template]" id="page_template">
					<option value='0'><?php _e( 'Default Template' ); ?></option>
					<?php page_template_dropdown( $options['page_template'] ); ?>
					</select>
				</td>
				</tr>
		</table>
		</fieldset>
		
		<!-- CUSTOM FIELDS -->
		<fieldset id="html-import-settings-postmeta">
		<h3><?php _e( "Custom Fields", 'import-html-pages' ); ?></h3>				
		<table class="form-table ui-tabs-panel striped" id="customfields">
			<tbody>
			
	<?php if ( !empty( $options['customfield_name'] ) && is_array( $options['customfield_name'] ) ) {
		foreach ( $options['customfield_name'] as $index => $fieldname ) : ?>
		<tr valign="top" class="clone" id="customfield<?php echo $index; ?>">
			<th><a class="button-secondary delRow" title="Remove field">&times;</a></th>
			<td>
				<label><?php _e( 'Custom field name', 'import-html-pages' ); ?><br />
					<input type="text" name="html_import[customfield_name][<?php echo $index; ?>]" 
						value="<?php echo esc_attr( $options['customfield_name'][$index] ); ?>" />
					</label><br />
					<label>
						<input type="checkbox" name="html_import[customfield_striptags][<?php echo $index; ?>]" value="1" <?php checked( '', $options['customfield_striptags'][$index] ) ?>>
						<?php _e( 'Strip HTML tags', 'import-html-pages' ); ?>
					</label>
			</td>
	        <td>

		            <label><?php _e( "Selector", 'import-html-pages' ); ?><br />
		            <input type="text" name="html_import[customfield_selector][<?php echo $index; ?>]" 
						value="<?php  echo esc_attr( $options['customfield_selector'][$index] ); ?>" />
		            </label>

				</td>
	    </tr>
	<?php endforeach;
	} else { ?>
		<tr valign="top" class="clone" id="customfield0">
			<th>
				<a class="button-secondary delRow" title="Remove field">&times;</a></th>
			<td>
				<label><?php _e( 'Custom field name', 'import-html-pages' ); ?><br />
					<input type="text" name="html_import[customfield_name][]" value="" />
					</label><br />
					<label>
						<input type="checkbox" name="html_import[customfield_striptags][]" value="1" checked>
						<?php _e( 'Strip HTML tags', 'import-html-pages' ); ?>
					</label>
			</td>
	        <td>

		            <label><?php _e( "Selector", 'import-html-pages' ); ?><br />
		            <input type="text" name="html_import[customfield_selector][]" value="" />
		            </label>

				</td>

			</td>
	    </tr>
	<?php } // else no custom fields ?>

</tbody>
<tfoot>
<tr><td colspan="2"><a class="button-secondary cloneTableRows" href="#"><?php _e( "Add a custom field", 'import-html-pages' ); ?></a></td>
	</tr>
	</tfoot>
		</table>
		</div>
		</fieldset>
		
		<!-- TAXONOMIES -->
		<fieldset id="html-import-settings-taxonomies">
		<h3><?php _e( "Taxonomies", 'import-html-pages' ); ?></h3>				
			<?php
			// support all public taxonomies
			$nonhierarchical = '';
			$taxonomies = get_taxonomies( array( 'public' => true ), 'objects', 'and' );
			?>
			<?php if ( is_array( $taxonomies ) ) : ?>
			<p><?php _e( 'Here, you may assign categories, tags, and custom taxonomy terms to all your imported posts.', 'import-html-pages' ); ?></p>
			<p><?php _e( 'To import tags from a region in each file, use a custom field with the name <kbd>post_tag</kbd>.', 'import-html-pages' ); ?></p>
					<?php foreach ( $taxonomies as $tax ) :
						if ( isset( $options[$tax->name] ) )
							$value = esc_attr( $options[$tax->name] );
						else
							$value = '';
						if ( !is_taxonomy_hierarchical( $tax->name ) ) :
						// non-hierarchical
							$nonhierarchical .= '<p class="taxoinput"><label>'.esc_html( $tax->label ).'<br />';
							$nonhierarchical .= '<input type="text" name="html_import['.esc_attr( $tax->name ).']" 
							 	value="'.$value.'" /></label></p>';
						else:
						// hierarchical 
						?>
						 	<div class="taxochecklistbox">
								<?php echo esc_html( $tax->label ); ?><br />
					        <ul class="taxochecklist">
					     	<?php
							if ( !isset( $options[$tax->name] ) ) $selected = '';
							else $selected = $options[$tax->name];
							wp_terms_checklist( 0, array( 
								           'descendants_and_self' => 0,
								           'selected_cats' => $selected,
								           'popular_cats' => false,
								           'walker' => new HTML_Import_Walker_Category_Checklist,
								           'taxonomy' => $tax->name,
								           'checked_ontop' => false,
								       )
								 ); 
						?>
						</ul>  </div>
					<?php
					endif;
					endforeach; 
					echo '<br class="clear" />'.$nonhierarchical;
					?>
	
			<?php endif; ?>
					
			<p class="submit clear">
				<input type="submit" class="button-primary" value="<?php _e( 'Save settings', 'import-html-pages' ); ?>" />
				<?php if ( !$options['firstrun'] ) { ?>
				<a href="<?php echo add_query_arg( 'import', 'html', 'admin.php' ) ?>" class="button-secondary"><?php _e( 'Import files', 'import-html-pages' ); ?></a>
				<?php } ?>
			</p>
		</form>
		</fieldset>
		<?php endif; ?>
		
		<?php if ( $active_tab == 'html-import-settings-redirects' ) : ?>
		<fieldset id="html-import-settings-redirects">
		 <h3><?php _e( "Regenerate <kbd>.htaccess</kbd> redirects", 'import-html-pages' ); ?></h3>
	     <p><?php printf( __( 'If you <a href="%s">changed your permalink structure</a> after you imported files, you can <a href="%s">regenerate the redirects</a>.', 'import-html-pages' ), 'wp-admin/options-permalink.php', wp_nonce_url( 'admin.php?import=html&step=2', 'html_import_regenerate' ) ) ?></p>
		</fieldset>
		<?php endif;
		
	if ( $active_tab == 'html-import-settings-export' ) :
		html_import_options_io_page();
	endif; 
	?>
	
	</div> <!-- .wrap -->
	<?php 
}

function html_import_validate_options( $input ) {
	// Validation/sanitization. Add errors to $msg[].
	$options = $msg = array();
	$linkmsg = '';
	$msgtype = 'error';
	
	$options['get_path'] = sanitize_text_field( $input['get_path'] );
	
	$options['content_selector']		= sanitize_text_field( $input['content_selector'] );
	$options['title_selector']			= sanitize_text_field( $input['title_selector'] );
	$options['remove_from_title']		= sanitize_text_field( $input['remove_from_title'] );
	$options['page_template']			= sanitize_text_field( $input['page_template'] );
	$options['thumbnail_selector']		= sanitize_text_field( $input['thumbnail_selector'] );
	$options['customfield_name']		= array_map( 'sanitize_text_field', (array) $input['customfield_name'] );
	$options['customfield_striptags']	= array_map( 'absint', (array) $input['customfield_striptags'] );
	$options['customfield_selector']	= array_map( 'sanitize_text_field', (array) $input['customfield_selector'] );
	
		
	if ( !in_array( $input['status'], get_post_stati() ) ) 
		$options['status'] = 'publish';
	else
		$options['status'] = $input['status'];
		
	$post_types = get_post_types( array( 'public' => true ),'names' );
	if ( !in_array( $input['type'], $post_types ) )
		$options['type'] = 'page';
	else
		$options['type'] = $input['type'];

	$options['follow_mode'] = 		absint( $input['follow_mode'] );
	$options['meta_desc'] = 		absint( $input['meta_desc'] );
	$options['title_inside'] = 		absint( $input['title_inside'] );
	$options['clean_content'] = 	absint( $input['clean_content'] );
	$options['preserve_slugs'] = 	absint( $input['preserve_slugs'] );
	$options['import_images'] = 	absint( $input['import_images'] );
	$options['import_documents'] = 	absint( $input['import_documents'] );
	$options['fix_links'] = 		absint( $input['fix_links'] );
	$options['meta_desc'] = 		absint( $input['meta_desc'] );
	
	$options['document_mimes']   = strtolower( sanitize_text_field( $input['document_mimes'] ) );
	$options['document_mimes']   = str_replace( '.', '', $options['document_mimes'] );
	$options['document_mimes']   = str_replace( ' ', '', $options['document_mimes'] );
	
	// see if this is a real user
	$options['user'] = absint( $input['user'] );
	$user_info = get_userdata( $input['user'] );
	if ( $user_info === false ) {
		$msg[] = "The author you specified does not exist.";
		$currentuser = wp_get_current_user();
		$options['user'] = $currentuser->ID;
	}
		
	// If settings have been saved at least once, we can turn this off.
	$options['firstrun'] = false;
	
	// Send custom updated message
	$msg = implode( '<br />', $msg );
	
	if ( empty( $msg ) ) {
		
		$linkstructure = get_option( 'permalink_structure' );
		if ( empty( $linkstructure ) )
			$linkmsg = sprintf( __( 'If you intend to <a href="%s">set a permalink structure</a>, you should do it 
				before importing so the <kbd>.htaccess</kbd> redirects will be accurate.', 'import-html-pages' ), 'options-permalink.php' );
		
		$msg = sprintf( __( 'Settings saved. %s <a href="%s">Ready to import files?</a>', 'import-html-pages' ), 
				$linkmsg, 'admin.php?import=html' );
		// $msg .= '<pre>'. print_r( $input, false ) .'</pre>';
		$msgtype = 'updated';
	}
	
	add_settings_error( 'html_import', 'html_import', $msg, $msgtype );
	return $options;
}

// custom walker so we can change the name attribute of the category checkboxes ( until #16437 is fixed )
// mostly a duplicate of Walker_Category_Checklist
class HTML_Import_Walker_Category_Checklist extends Walker {
     var $tree_type = 'category';
     var $db_fields = array ( 'parent' => 'parent', 'id' => 'term_id' ); 

 	function start_lvl( &$output, $depth = 0, $args = array() ) {
         $indent = str_repeat( "\t", $depth );
         $output .= "$indent<ul class='children'>\n";
     }
 
 	function end_lvl( &$output, $depth = 0, $args = array() ) {
         $indent = str_repeat( "\t", $depth );
         $output .= "$indent</ul>\n";
     }
 
 	function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
         extract( $args );
         if ( empty( $taxonomy ) )
             $taxonomy = 'category';
 
		// This is the part we changed
         $name = 'html_import['.$taxonomy.']';
 
         $class = in_array( $object->term_id, $popular_cats ) ? ' class="popular-category"' : '';
         $output .= "\n<li id='{$taxonomy}-{$object->term_id}'$class>" . '<label class="selectit"><input value="' . $object->term_id . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $object->term_id . '"' . checked( in_array( $object->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters( 'the_category', $object->name ) ) . '</label>';
     }
 
 	function end_el( &$output, $category, $depth = 0, $args = array() ) {
         $output .= "</li>\n";
     }
}


// options import/export


function html_import_options_io_page() {

	$options = get_option( 'html_import' ); ?>
	<div class="wrap" id="html-import-settings-export">
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