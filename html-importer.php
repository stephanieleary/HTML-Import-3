<?php

if ( !defined( 'WP_LOAD_IMPORTERS' ) )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

// Load SimpleHTMLDOM
if ( !function_exists( 'file_get_html' ) ) {
	require_once( __DIR__.'/lib/simplehtmldom/simple_html_dom.php');
}

// Load PHPCrawl
require_once( __DIR__.'/lib/PHPCrawl/libs/PHPCrawler.class.php');
require_once( __DIR__.'/lib/class.HTMLImportCrawler.php');

if ( class_exists( 'WP_Importer' ) ) {
class HTML_Import extends WP_Importer {

	var $posts = array();
	var $sitemap = array();
	var $file;
	var $options = array();
	var $crawler_id = NULL;
	var $logging = 0;
	var $file_counter = 0;
	var $attachment_counter = 0;
	var $restrict_to_sitemap = false;

	function header() {
		echo '<div class="wrap">';
		echo '<h2>'.__( 'HTML Importer', 'import-html-pages' ).'</h2>';
	}

	function footer() {
		echo '</div>';
	}
	
	function greet() {
		$options = get_option( 'html_import' );
		?>
		<div class="narrow">
		<?php 
		if ( $options['firstrun'] === true ) {
		echo '<p>'.sprintf( __( 'It looks like you have not yet visited the <a href="%s">HTML Import options page</a>. Please do so now! You need to specify which portions of your HTML files should be imported before you proceed.', 'import-html-pages' ), 'options-general.php?page=html-import.php' ).'</p>'; 
		} 
		else { ?>
		<h4><?php _e( 'What are you importing today?' ); ?></h4>
		<form enctype="multipart/form-data" method="post" action="admin.php?import=html&amp;step=1">
		<p>
		<label><input name="import_files" id="import_files" type="radio" value="directory" checked="checked"
		onclick="javascript: jQuery( '#single' ).hide( 'fast' ); jQuery( '#directory' ).show( 'fast' );"  />	
			<?php _e( 'a URL', 'import-html-pages' ); ?></label> &nbsp; &nbsp;	
		<label><input name="import_files" id="import_files" type="radio" value="file" 
		onclick="javascript: jQuery( '#directory' ).hide( 'fast' ); jQuery( '#single' ).show( 'fast' );" />
			<?php _e( 'a single file', 'import-html-pages' ); ?></label>
		</p>
		
		<p id="single">
		<label for="import"><?php _e( 'Choose an HTML file from your computer:', 'import-html-pages' ); ?></label>
		<input type="file" id="import" name="import" size="25" />
		</p>
		
		<p id="directory">
			<?php
			printf( __( 'Your files will be imported from <kbd>%s</kbd>. <a href="%s">Change directories</a>.', 'import-html-pages' ),
			esc_html( $options['get_path'] ), 'options-general.php?page=html-import.php' ); ?>
		</p>
		
		<input type="hidden" name="action" value="save" />
		
		<p class="submit">
			<input type="submit" name="submit" class="button" value="<?php echo esc_attr( __( 'Submit', 'import-html-pages' ) ); ?>" />
		</p>
		<?php wp_nonce_field( 'html-import' ); ?>
		</form>
		</div>
	<?php } // else
	}
	
	function regenerate_redirects() {
		$newredirects = ''; 
		$post_ids = $this->get_imported_posts();
		foreach( $post_ids as $post_id ) { 
			$old = get_post_meta( $post_id, 'URL_before_HTML_Import', true );
			$newredirects .= "Redirect\t".$old."\t".get_permalink( $post_id )."\t[R=301,NC,L]\n";
		}
		if ( !empty( $newredirects ) ) { ?>
		<h3><?php _e( '.htaccess Redirects', 'import-html-pages' ); ?></h3>
		<p><?php _e( 'Copy these lines into your <kbd>.htaccess</kbd> <em>above</em> the WordPress section.', 'import-html-pages' ); ?></p>
		<textarea id="import-result"><?php echo $newredirects; ?></textarea>
		<h3><?php printf( __( 'All done! You can <a href="%s">change your permalink structure</a> and <a href="%s">regenerate the redirects again</a>, or <a href="%s">start over</a>.', 'import-html-pages' ), 'options-permalink.php', wp_nonce_url( 'admin.php?import=html&step=2', 'html_import_regenerate' ), 'admin.php?import=html' ) ?></h3>
		<?php }
		else _e( 'No posts were found with the URL_before_HTML_Import custom field. Could not generate rewrite rules.', 'import-html-pages' );
	}

	function set_parent_id( $post_id ) {
		if ( 'attachment' == get_post_type( $post_id ) ) {
			$path = get_post_meta( $post_id, 'HTML_Import_referer', true );
			$parent_id = $this->get_post_id_by_original_url( $path );
		}
		else {
			$path = get_post_meta( $post_id, 'URL_before_HTML_Import', true );
			$parent_dir = dirname( $path );

			if ( $parent_dir == $this->options['get_path'] )
				return;

			$parent_id = $this->get_post_id_by_original_url( $parent_dir );
		}
		
		if ( $parent_id && ! is_wp_error( $parent_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_parent' => $parent_id ) );
		}
	}

	function fix_internal_links( $post_id ) {	
		$content = get_post_field( 'post_content', $post_id );
		$html = str_get_html( $content );
		if ( empty( $html ) )
			return $content;
			
		foreach ( $html->find('a') as $link ) {
			$linked_post_id = $this->get_post_id_by_original_url( $link->href );
			if ( $linked_post_id && ! is_wp_error( $linked_post_id ) )
				$link->href = get_permalink( $linked_post_id );
		}
		
		return $html->save();
	}

	function get_single_file( $txt = false ) {
		set_magic_quotes_runtime( 0 );
		$importfile = file( $this->file ); // Read the file into an array
		$importfile = implode( '', $importfile ); // squish it
		$this->file = $importfile;
		$this->handle_post_content( NULL, '', $this->file, '' );
	}
	
	function handle_import_media_file( $DocInfo ) {
		$mimes = apply_filters( 'html_import_allowed_mime_types', get_allowed_mime_types() );
		if ( !in_array( strtolower( $DocInfo->content_type ), $mimes ) && $this->logging > 0 ) {
			printf( __( 'Error: File type not allowed: %s', 'import-html-pages' ), $DocInfo->file );
			flush();
		}
		
		$parent_id = $this->get_post_id_by_original_url( $DocInfo->referer_url );
		$file_id = $this->sideload_file( $DocInfo->url, $parent_id, urldecode( $DocInfo->file ), $DocInfo->referer_url );
		if ( is_wp_error( $file_id ) && $this->logging > 0 ) {
			printf( __( 'Error: %s', 'import-html-pages' ), esc_html( $file_id->get_error_message() ) );
			flush();
		}
		else {
			if ( $this->logging > 0 ) {
				printf( __( 'Imported as <a href="%s">file %d</a><br />', 'import-html-pages' ), wp_get_attachment_url( $file_id ), $file_id );
				flush();
			}
		}

		return $file_id;
	}
	
	function sideload_file( $url, $post_id, $desc, $referer ) {
		$tmp = download_url( $url );	
		if ( is_wp_error( $tmp ) )
		    return $tmp;
		$file_array = array(
		    'name' => basename( $url ),
		    'tmp_name' => $tmp
		);
		$file_id = media_handle_sideload( $file_array, $post_id, $desc );
		if ( is_wp_error( $file_id ) )
		    @unlink( $file_array['tmp_name'] );
		update_post_meta( $file_id, 'URL_before_HTML_Import', esc_url_raw( $url ) );
		update_post_meta( $file_id, 'HTML_Import_referer', esc_url_raw( $referer ) );
		return $file_id;
	}
	
	function find_internal_links( $post_ids ) {
		echo '<h2>'.__( 'Fixing relative links...', 'import-html-pages' ).'</h2>';
		echo '<p>'.__( 'The importer is searching your imported posts for links. This might take a few minutes.', 'import-html-pages' ).'</p>';
		
		if ( empty( $post_ids ) ) {
			_e( 'No posts were found with the URL_before_HTML_Import custom field. Could not search for links.', 'import-html-pages' );
			return;
		}
		
		$fixedlinks = array();
		foreach ( $post_ids as $post_id ) {
			$new_post = array();
			$new_post['ID'] = $post_id;
			$new_post['post_content'] = $this->fix_internal_links( $post_id );
		
			if ( !empty( $new_post['post_content'] ) ) {
				wp_update_post( $new_post );
				$fixedlinks[] .= $post_id;
			}
			
		}
		if ( !empty( $fixedlinks ) ) { ?>
			<h3><?php _e( 'All done!', 'import-html-pages' ); ?></h3>
		<?php }
			else _e( 'No posts were updated.', 'import-html-pages' );
	}
	
	function handle_post_content( $post_id = NULL, $url, $html_raw, $date_modified ) {
		$options = $this->options;
		$html = str_get_html( $html_raw );
		if ( empty( $html ) )
			return false;
				
		if ( '<!--' == substr( $options['title_selector'], 0, 4 ) ) {
			$titlematch = '/<!-- InstanceBeginEditable name="'.$options['title_selector'].'" -->( .* )<!-- InstanceEndEditable -->/isU';
			preg_match( $titlematch, $html_raw, $titlematches );
			$title = strip_tags( trim( $titlematches[1] ) );
		}
		else {
			$title = $html->find( $options['title_selector'], 0 );
			if ( !empty( $title ) ) {
				$title_html = $title->outertext;
				$title = $title->plaintext;
			}
		}
		
		// remove phrases from title
		if ( !empty( $options['remove_from_title'] ) )
			$title = str_replace( $options['remove_from_title'], '', $title );
		// put it back if the title is now empty
		if ( empty( $title ) )
			$title = $options['remove_from_title'];
			
		if ( '<!--' == substr( $options['content_selector'], 0, 4 ) ) {
			$contentmatch = '/<!-- InstanceBeginEditable name="'.$options['content_selector'].'" -->( .* )<!-- InstanceEndEditable -->/isU';
			preg_match( $contentmatch, $html_raw, $contentmatches );
			$content = $contentmatches[1];
		}
		else {
			$content = $html->find( $options['content_selector'], 0 );
			if ( !empty( $content ) ) {
				$content = $content->innertext;
			}
		}
		
		// remove inner titles before cleaning up HTML
		if ( $options['title_inside'] )
			$content = str_replace( $title_html, '', $content );
			
		// clean up HTML
		$allowed = apply_filters( 'html_import_allowed_tags_content', wp_kses_allowed_html( 'post' ) );
		$content = wp_kses( $content, $allowed );
		
		if ( $options['preserve_slugs'] ) {
			$slug = stripslashes( parse_url( $url, PHP_URL_PATH ) );
			$parts = pathinfo( $slug );
			$slug = basename( $parts['filename'], '.' . $parts['extension'] );
		}
		else
			$slug = sanitize_title( $title );
		
		$excerpt = '';
		if ( $options['meta_desc'] ) {
			$excerpt = $html->find( "meta[name='description']", 0 );
			if ( !empty( $excerpt ) )
				$excerpt = sanitize_text_field( $excerpt->content );
		}
		
		$date = '';
		if ( isset( $options['date_selector'] ) ) {
			switch ( $options['date_selector'] ) {
				case '__now':
					break;
				case '__filemtime':
					$date = $date_modified;
					break;
				case 'DC.date':
					$date = $html->find( 'meta[name="DC.date"]', 0 );
					if ( !empty( $date ) )
						$date = $date->content;
					break;
				default:
					if ( '<!--' == substr( $options['date_selector'], 0, 4 ) ) {
						$datematch = '/<!-- InstanceBeginEditable name="'.$options['date_selector'].'" -->( .* )<!-- InstanceEndEditable -->/isU';
						preg_match( $datematch, $html_raw, $datematches );
						$date = $datematches[1];
					}
					else {
						$date = $html->find( $options['date_selector'], 0 )->plaintext;
					}
					
					if ( !empty( $date ) )
						$date = date( "Y-m-d H:i:s", strtotime( $date ) );
					break;
			}
			
		}
		if ( empty( $date ) )
			$date = date( "Y-m-d H:i:s", time() );

		$author = $options['user'];
		
		$meta = array(
			'URL_before_HTML_Import' => esc_url_raw( $url )
		);
		
		if ( isset( $options['page_template'] ) && !empty( $options['page_template'] ) )
			$meta['_wp_page_template'] = $options['page_template'];
		
		global $allowedtags;
		$allowed_in_postmeta = apply_filters( 'html_import_allowed_tags_postmeta', $allowedtags );
		
		// select & set custom fields
		foreach ( $options['customfield_name'] as $index => $fieldname ) {
			if ( !empty( $fieldname ) ) {
				if ( '<!--' == substr( $options['customfield_selector'][$index], 0, 4 ) ) {
					$custommatch = '/<!-- InstanceBeginEditable name="'.$options['customfield_selector'][$index].'" -->( .* )<!-- InstanceEndEditable -->/isU';
					preg_match( $custommatch, $html_raw, $custommatches );
					if ( isset( $custommatches[1] ) )
						$meta[$fieldname] = $custommatches[1];
				}
				else {
					$customfield = $html->find( $options['customfield_selector'][$index], 0 );
					if ( $options['customfield_striptags'][$index] ) {
						$meta[$fieldname] = $customfield->plaintext;
					}
					else {
						$meta[$fieldname] = wp_kses( $customfield->innertext, $allowed_in_postmeta );
					}
					
					
				}
			}
		}
		
		$args = array(
			'post_title' => (string) $title,
			'post_content' => (string) $content,
			'post_excerpt' => (string) $excerpt,
			'post_type' => (string) $options['type'],
			'post_status' => (string) $options['status'],
			'post_name' => (string) $slug,
			'post_author' => (int) $author,
			'post_date' => (string) $date,
			'meta_input' => $meta
		);
		if ( $options['update_existing'] && isset( $post_id ) && ! empty( $post_id ) )
			$args['ID'] = $post_id;
		$args = apply_filters( 'html_import_insert_post_args', $args );
		
		// simplehtmldom memory cleanup
		$html->clear(); 
		unset( $html );
		
		$post_id = wp_insert_post( $args );
		//$post_id = 0;
		//echo "Inserting: ";
		//var_dump( $args );
	
		// handle errors
		if ( is_wp_error( $post_id ) ) {
			printf( __( 'Error importing %s: %s', 'import-html-pages' ), $url, esc_html( $post_id->get_error_message() ) );
			flush();
		}
		elseif ( empty( $post_id ) ) {
			global $wpdb;
			$wpdb->print_error();	
			flush();
		}
		else {
			if ( $this->logging > 0 ) {
				$link = get_permalink( $post_id );
				echo "Imported $title as <a href='$link'>{$options['type']} $post_id</a>.<br />";
				flush();
			}
			return $post_id;
		}
	}
	
	function set_thumbnail( $post_id ) {
		if ( empty( $this->options['thumbnail_selector'] ) )
			return false;
		
		$content = get_post_field( 'post_content', $post_id );
		$html = str_get_html( $content );
		if ( !$html )
			return false;
			
		$img = $html->find( $this->options['thumbnail_selector'], 0 );
		if ( !$img )
			return false;
		
		if ( 'img' == $img->tag ) {
			$src = $img->src;
		}
		else {
			$img_within = $img->find( 'img', 0 );
			$src = $img_within->src;
		}
		$posts = get_posts( array(
			'fields' => 'ids',
			'post_type' => 'attachment',
			'post_status' => 'any',
			'meta_key' => 'URL_before_HTML_Import',
			'meta_value' => esc_url_raw( $src )
		) );
		if ( empty( $posts ) || is_wp_error( $posts ) )
			return false;
		update_post_meta( $post_id, '_thumbnail_id', $posts[0]->ID ); 
		
		$html->clear(); 
		unset( $html );
	}
	
	function import_single_url( $url ) {
		$response = wp_remote_get( $url );
		if ( wp_remote_retrieve_response_code( $response ) == 200 && ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			$date = wp_remote_retrieve_header( $response, 'last-modified' );
			// pass the contents to SimpleHTMLDom
			$post_id = $this->handle_post_content( NULL, $url, $body, $date );
		} 
		else
			echo esc_html( $response->get_error_message() ) . '<br />';
	}
	
	function get_post_id_by_original_url( $url_before_import ) {
		global $wpdb;
		$parent_id = $wpdb->get_var( $wpdb->prepare( "select post_id from {$wpdb->postmeta} where meta_key = %s and meta_value = %s", 'URL_before_HTML_Import', esc_url_raw( $url_before_import ) ) );
		
		return $parent_id;
	}
	
	function get_imported_posts() {
		$args = array(
			'fields' => 'ids',
			'post_type' => 'any',
			'post_status' => 'any',
			'meta_key' => 'URL_before_HTML_Import',
			'posts_per_page' => -1
		);
		return get_posts( $args );
	}
	
	function display_progress_bar() {
		echo '<div class="progress">
		  <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"> 
			<span id="valuenow">0%</span> 
		  </div></div>';
		flush();
	}
	
	function display_progress_area() {
		if ( !empty( $this->sitemap ) )
			$this->display_progress_bar();
		echo '<p class="progress-report">'._e(' Files: ', 'import-html-pages' ).'<span class="progress-file"> </span></p>';
		echo '<p class="progress-report">'._e(' Attachments: ', 'import-html-pages' ).'<span class="progress-attachment"> </span></p>';
		echo '<p><textarea id="request_log"></textarea></p>';
		flush();
	}
	
	function display_progress( $percentage ) { 
		?>
		<script>
			var percentage = <?php echo $percentage; ?>;
			jQuery( ".progress-bar #valuenow" ).html( percentage + '%' );
			jQuery( ".progress-bar" ).css( 'width', percentage + '%' );
			jQuery( ".progress-bar" ).attr( 'aria-valuenow', percentage );
		</script>
		<?php
		flush();
	}
	
	function display_counter( $count, $type = 'file' ) {
		?>
		<script>
			var count = <?php echo $count; ?>;
			var type = <?php echo $type; ?>;
			jQuery( ".progress-report .progress-" + type ).html( count );
		</script>
		<?php
		flush();
	}
	
	function get_sitemap( $path = NULL ) {
					
		if ( !isset( $path ) ) {
			// user might have entered the sitemap URL directly
			$filename = basename( $this->options['get_path'] );
			if ( $filename == 'sitemap.xml' || $filename == 'sitemap.xml.gz' ) {
				$this->restrict_to_sitemap = true;
				$response = wp_remote_get( $this->options['get_path'] );
			}
			else {
				// trim any filename that might have been given in the path
				$path_parts = parse_url( $this->options['get_path'] );
				$sitemap_path = str_replace( $path_parts['path'], '', $this->options['get_path'] );
				// request gzip first; if not found, request .xml
				// wp_remote_get() decompresses the response by default, so it should handle gzips just fine
				$path = trailingslashit( $sitemap_path ) . 'sitemap.xml.gz';
				$response = wp_remote_get( $path );
				$response_code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $response_code || is_wp_error( $response ) ) {
					$sitemap = trailingslashit( $sitemap_path ) . 'sitemap.xml';
					$response = wp_remote_get( $path );
				}
			}
			
		}
		else {
			$response = wp_remote_get( $path );
		}	
	
		if ( wp_remote_retrieve_response_code( $response ) == 200 && ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			$element = new SimpleXMLElement( $body );
			// handle nested sitemaps
			if ( $element->getName() == 'sitemapindex' ) {
				foreach ( $element->sitemap as $map ) {
					$this->get_sitemap( $map->loc );
				}
			}
			elseif ( $element->getName() == 'urlset' ) {
				get_sitemap_urls( $element );
			}
			
		}
 
		if ( is_wp_error( $response ) && $this->logging > 0 ) {
			$error_message = $response->get_error_message();
			echo $error_message;
		}
		
	}
	
	function get_sitemap_urls( $urlset ) {
		$ns = $urlset->getNamespaces(true);
		foreach ( $urlset->url as $uri ) {
			$images = $uri->children($ns['image']);
			$img_arr = array();
			if ( !empty( $images->image ) ) {
				$img_arr = (array) $images->image; // array keys: loc, title, caption
				$img_arr['title'] = (string) $img_arr['title'] ;
				$img_arr['caption'] = (string) $img_arr['caption'] ;
			}
			$new_urls = array(
				'url' => (string) $uri->loc,
				'lastmod' => (string) $uri->lastmod,
				'images' => $img_arr,
			);
			array_merge( $this->sitemap, $new_urls );
		}
	}
	
	function start_phpcrawl() {
		set_time_limit( 0 );
		ini_set( 'memory_limit', '2048M' );
		$this->options['update_existing'] = apply_filters( 'html_import_update_existing', $this->options['update_existing'] );
		$crawler = new HTMLImportCrawler();
		$this->crawler_id = $crawler->getCrawlerId();
		$crawler->setURL( $this->options['get_path'] );
		
		// search for links in these file types
		$crawler->addLinkSearchContentType( "#text/html# i" );
		
		// Don't request these file types
		$crawler->addURLFilterRule( "#\.(js|css|json|xml)$# i" );
		
		// download these file types
		$crawler->addContentTypeReceiveRule( "#text/html# i" );	
		$mimes = apply_filters( 'html_import_allowed_mime_types', get_allowed_mime_types() ); 
		
		foreach ( $mimes as $mime ) {
			if ( 'application/pdf' !== $mime )
				$crawler->addContentTypeReceiveRule( '#' . (string) $mime . '# i' );
		}
		
		// PDF is buggy for some reason; add the extension explicitly as well as the mime type
		//$crawler->addContentTypeReceiveRule( "#application/pdf# i" );
		//$crawler->addContentTypeReceiveRule( "#\.(pdf)$# i" );

		$crawler->setUserAgentString( apply_filters( 'html_import_user_agent', 'WordPress-HTMLImport3(alpha)' ) );

		// Store and send cookie-data like a browser does
		$crawler->enableCookieHandling( true );
		
		// Obey robots.txt, but allow plugins to override
		$crawler->obeyRobotsTxt( apply_filters( 'html_import_crawler_obey_robots_file', false ) );

		// allow plugins to set a traffic limit
		$limit = apply_filters( 'html_import_crawler_traffic_limit', 0 );
		if ( $limit ) {
			$crawler->setTrafficLimit( absint( $limit ) );
		}
		
		// Allow plugins to set crawler depth
		$depth = apply_filters( 'html_import_crawler_depth', NULL );
		if ( $depth ) {
			$crawler->setCrawlingDepthLimit( absint( $depth ) );
		}
		
		// Limit the crawler to 100 requests per minute; allow plugins to override
		$crawler->setRequestDelay( apply_filters( 'html_import_crawler_request_delay', 60/100 ) );

		$crawler->setFollowMode( absint( $this->options['follow_mode'] ) );
		
		// Allow plugins to add settings to the crawler
		// see http://phpcrawl.cuab.de/classreferences/index.html
		$crawler = apply_filters( 'html_import_phpcrawl_methods', $crawler );
		
		$crawler->go();
		
		return $crawler;
	}
	
	function receive_content( $DocInfo ) {
		// handed off from PHPCrawl's handleDocumentInfo() method using 'html_import_receive_file' action
		// see lib/class.HTMLImportCrawler.php
		
		if ( $this->restrict_to_sitemap && ! in_array( $DocInfo->url, $this->sitemap ) )
			return;
		
		$post_exists = $this->get_post_id_by_original_url( $DocInfo->url );
		
		if ( 'text/html' !== $DocInfo->content_type ) {
			$this->attachment_counter++;
			if ( ! $post_exists ) {
				$post_id = $this->handle_import_media_file( $DocInfo );
				$this->display_counter( $this->attachment_counter, 'attachment' );
			}
		}
		elseif ( ! $post_exists || $this->options['update_existing'] ) {
			// rebuild URLs so they're all fully qualified
			foreach ( $DocInfo->links_found as $link ) {
				if ( 0 != strcmp( $link['link_raw'], $link['url_rebuild'] ) ) {
					str_replace( $link['link_raw'], esc_url( $link['url_rebuild'] ), $DocInfo->source );
					if ( $this->logging > 1 )
						printf( __( "Corrected %s to %s<br>", 'import-html-pages' ), esc_url( $link['link_raw'] ), esc_url( $link['url_rebuild'] ) );
				}
            }
			$date = wp_remote_retrieve_header( $DocInfo->header, 'last-modified' );
			$post_id = $this->handle_post_content( $post_exists, $DocInfo->url, $DocInfo->source, $date );
			if ( ! is_wp_error( $post_id ) ) {
				$this->file_counter++;
				if ( !empty( $this->sitemap ) ) {
					$percentage = round( $this->file_counter / count( $this->sitemap ) * 100 );
					$this->display_progress( $percentage );
				}
				$this->display_counter( $this->file_counter, 'file' );
				
			}
		}
	}
	
	function filesize_format( $size ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $size > 0 ? floor( log( $size, 1024 ) ) : 0;
		return number_format( $size / pow( 1024, $power ), 2, '.', ',' ) . ' ' . $units[$power];
	}
	
	function finish_phpcrawl( $crawler ) {
		//$this->display_progress( 100 );
		// post-processing
		$post_ids = $this->get_imported_posts();
		if ( !empty( $post_ids ) && !is_wp_error( $post_ids ) ) {
			if ( $this->options['fix_links'] ) {
				$this->find_internal_links( $post_ids );
			}
			foreach ( $post_ids as $post_id ) {
				if ( !empty( $this->options['thumbnail_selector'] ) ) {
					$this->set_thumbnail( $post_id );
				}
				$this->set_parent_id( $post_id );
			}
		}
		
		
		// TODO: translate these strings
		$report = $crawler->getProcessReport(); 
		$lb = "<br />";
		echo $lb . "Summary:" . $lb;
		if ( !empty( $this->sitemap ) )
			echo "Links in sitemap: " . count( $this->sitemap ) . $lb;
		echo "Links followed: " . $report->links_followed . $lb;
		echo "Documents received: " . $report->files_received . $lb;
		echo "Data received: ". $this->filesize_format( $report->bytes_received ) . $lb;
		echo "Process runtime: " . $report->process_runtime . " sec" . $lb;
		echo "Done." . $lb;
	}

	function dispatch() {
		
		$step = absint( $_REQUEST['step'] );

		$this->header();

		switch ( $step ) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer( 'html-import' );
				$this->options = get_option( 'html_import' );
				$this->logging = apply_filters( 'html_import_logging', 0 );
				$this->get_sitemap();
				$this->display_progress_area();
				$crawler = $this->start_phpcrawl();
				$this->finish_phpcrawl( $crawler );
				break;
			case 2 :
				$this->regenerate_redirects();
				break;
		}

		$this->footer();
	}
	
	function mime_types( $mimes = array() ) {
		foreach ( array( 'js', 'swf', 'class', 'exe' ) as $key ) {
			if ( isset( $mimes[$key] ) )
				unset( $mimes[$key] );
		}
		return $mimes;
	}
	
	function importer_scripts_and_styles() {
		if ( isset( $_REQUEST['import'] ) && $_REQUEST['import'] == 'html' ) {
			wp_enqueue_script( 'html-import-ajax-handler', plugins_url( 'js/ajax-handler.js', __FILE__ ), 'jquery' );
			wp_enqueue_style( 'html-import-admin-style', plugins_url( 'css/html-importer.css', __FILE__ ), false, false, 'screen' );
		}
	}

	function __construct() {
		add_action( 'admin_print_styles-admin.php', array( &$this, 'importer_scripts_and_styles' ) );
		add_filter( 'html_import_allowed_mime_types', array( &$this, 'mime_types' ), 1 );
		add_action( 'html_import_receive_file',  array( &$this, 'receive_content' ) );
	}
}

} // class_exists( 'WP_Importer' )

function html_importer_init() {
	load_plugin_textdomain( 'html-import', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	global $html_import;
	$html_import = new HTML_Import();
	register_importer( 'html', __( 'HTML', 'import-html-pages' ), sprintf( __( 'Import the contents of HTML files as posts, pages, or any custom post type. Visit <a href="%s">the options page</a> first to select which portions of your documents should be imported.', 'import-html-pages' ), 'options-general.php?page=html-import.php' ), array ( $html_import, 'dispatch' ) );
}

add_action( 'init', 'html_importer_init' );