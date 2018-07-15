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
	var $url_queue = array();
	var $file;
	var $options = array();

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
		$imported = get_posts( array( 'meta_key' => 'URL_before_HTML_Import', 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => '-1' ) );
		foreach( $imported as $post ) { 
			$old = get_post_meta( $post->ID, 'URL_before_HTML_Import', true );
			$newredirects .= "Redirect\t".$old."\t".get_permalink( $post->ID )."\t[R=301,NC,L]\n";
		}
		if ( !empty( $newredirects ) ) { ?>
		<h3><?php _e( '.htaccess Redirects', 'import-html-pages' ); ?></h3>
		<p><?php _e( 'Copy these lines into your <kbd>.htaccess</kbd> <em>above</em> the WordPress section.', 'import-html-pages' ); ?></p>
		<textarea id="import-result"><?php echo $newredirects; ?></textarea>
		<h3><?php printf( __( 'All done! You can <a href="%s">change your permalink structure</a> and <a href="%s">regenerate the redirects again</a>, or <a href="%s">start over</a>.', 'import-html-pages' ), 'options-permalink.php', wp_nonce_url( 'admin.php?import=html&step=2', 'html_import_regenerate' ), 'admin.php?import=html' ) ?></h3>
		<?php }
		else _e( 'No posts were found with the URL_before_HTML_Import custom field. Could not generate rewrite rules.', 'import-html-pages' );
	}

	function fix_hierarchy( $postid, $path ) {
		$options = get_option( 'html_import' );
		$parentdir = rtrim( $this->parent_directory( $path ), '/' );
		
		// create array of parent directories, starting with the index file's parent and moving up to the root directory
		while ( $parentdir != $options['root_directory'] ) {
			$parentarr[] = $parentdir;
			$parentdir = rtrim( $this->parent_directory( $parentdir ), '/' );
		}
		// reverse the array so we start at the root -- this way the parents can be found when we search in $this->get_post
		$parentarr = array_reverse( $parentarr );
		
//		echo '<pre>'.print_r( $parentarr, true ).'</pre>';
		
		foreach ( $parentarr as $parentdir ) {
			$parentID = array_search( $parentdir, $this->filearr );
			if ( $parentID === false )
				$this->get_post( $parentdir, true );
		}
		
		// now fix the parent ID of the original index file ( in $postid )
		// it's the next to last element in the array we want. ( The last one is the index file. ) If this doesn't exist, we don't need to fix the parent.
		$grandparent = count( $parentarr )-2;
		if ( isset( $parentarr[$grandparent] ) ) {
			$parentdir = $parentarr[$grandparent];
			$my_post['ID'] = $postid;
			$my_post['post_parent'] = array_search( $parentdir, $this->filearr );
		
			//echo "\n<pre>The parent of $postid should be ".$my_post['post_parent']."</pre>"; 
		
			if ( !empty( $my_post['post_parent'] ) )
				wp_update_post( $my_post );
		}
	}

	function parent_directory( $path ) {
		$win = false;
		if ( strpos( $path, '\\' ) !== FALSE ) {
			$win = true;
	    	$path = str_replace( '\\', '/', $path );
		}
	    if ( substr( $path, strlen( $path ) - 1 ) != '/' ) $path .= '/'; 
	    $path = substr( $path, 0, strlen( $path ) - 1 );
	    $path = substr( $path, 0, strrpos( $path, '/' ) ) . '/';
	    if ( $win ) $path = str_replace( '/', '\\', $path );
	    return $path;
	}
	
	function fix_internal_links( $content, $id ) {		
		// find all href attributes
		preg_match_all( '/<a[^>]* href=[\'"]?([^>\'" ]+ )/i', $content, $matches );
		for ( $i=0; $i<count( $matches[0] ); $i++ ) {
			$hrefs[] = $matches[1][$i];
		}
		if ( !empty( $hrefs ) ) {
			//echo '<p>Looking in '.get_permalink( $id ).'</p>';
			$options = get_option( 'html_import' );
			$site = $options['old_url'];
			$rootdir = $options['root_directory'];
			foreach ( $hrefs as $href ) {
				if ( '#' != substr( $href, 0, 1 ) && 'mailto:' != substr( $href, 0, 7 ) ) { // skip anchors and mailtos
					if ( preg_match( '/^http:\/\//', $href ) || preg_match( '/^https:\/\//', $href ) ) {
						// if it's an internal link, let's get a local file path
						$linkpath = str_replace( $site, $rootdir, $href );		
					}
					// href="/images/foo"
					elseif ( '/' == substr( $href, 0, 1 ) ) { 
						$linkpath = $rootdir . $href;
						$linkpath = $this->remove_dot_segments( $linkpath );
					}
					// href="../../images/foo" or href="images/foo"
					else {
						// we need to know where we are in the hierarchy 
						$oldpath = get_post_meta( $id, 'URL_before_HTML_Import', true );
						$oldpath = str_replace( $site, $rootdir, $oldpath );
						//echo '<p>Old path: '.$oldpath;
						$oldfile = strrchr( $oldpath, '/' );
						$linkpath = str_replace( $oldfile, '/'.$href, $oldpath );
						$linkpath = $this->remove_dot_segments( $linkpath );
						//echo ' Link path: '.$linkpath . '</p>';
					}
			
					$linkpath = rtrim( $linkpath, '/' );
					//echo '<p>Old link: '.$href.' Full path: '.$linkpath;
					// now replace the old URL with the new permalink
					$postkey = array_search( $linkpath, $this->filearr );
					//echo ' Post ID:'.$postkey.'.</p>';
					if ( !empty( $postkey ) ) {
						//echo '<p>I think '.$linkpath.' has moved to '.get_permalink( $postkey ).'.</p>';
						$content = str_replace( $href, get_permalink( $postkey ), $content );
					}
				} // if #/mailto
			} // foreach
		} // if empty
		return $content;
	}

	function get_single_file( $txt = false ) {
		set_magic_quotes_runtime( 0 );
		$importfile = file( $this->file ); // Read the file into an array
		$importfile = implode( '', $importfile ); // squish it
		// this strips whitespace out of <pre>. Need to find a better way to handle that. For now, leave it alone.
		//$this->file = str_replace( array ( "\r\n", "\r" ), "\n", $importfile );
		$this->file = $importfile;
		$this->get_post( '', false );
	}
	
	function handle_import_media_file( $DocInfo ) {
		$mimes = apply_filters( 'html_import_allowed_mime_types', get_allowed_mime_types() );
		if ( in_array( strtolower( $DocInfo->content_type ), $mimes ) ) {
			$post_id = $this->get_parent_id_by_referer( $DocInfo->referer_url );
			$file_id = $this->sideload_file( $DocInfo->url, $post_id, urldecode( $DocInfo->file ) );
			if ( is_wp_error( $file_id ) ) {
				printf( __( 'Error: %s', 'import-html-pages' ), esc_html( $file_id->get_error_message() ) );
				flush();
			}
			else {
				printf( __( 'Imported as <a href="%s">file %d</a><br />', 'import-html-pages' ), wp_get_attachment_url( $file_id ), $file_id );
				flush();
			}
		}
		else {
			printf( __( 'Error: File type not allowed: %s', 'import-html-pages' ), $DocInfo->file );
			flush();
		}
		return $file_id;
	}
	
	function sideload_file( $url, $post_id, $desc ) {
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
		return $file_id;
	}
	
	function find_internal_links() {
		echo '<h2>'.__( 'Fixing relative links...', 'import-html-pages' ).'</h2>';
		echo '<p>'.__( 'The importer is searching your imported posts for links. This might take a few minutes.', 'import-html-pages' ).'</p>';
		
		$fixedlinks = array(); 
		foreach ( $this->filearr as $id => $path ) {
			$new_post = array();
			$post = get_post( $id );
			$new_post['ID'] = $post->ID;
			$new_post['post_content'] = $this->fix_internal_links( $post->post_content, $post->ID );
		
			if ( !empty( $new_post['post_content'] ) )
				wp_update_post( $new_post );
			$fixedlinks[] .= $post->ID;
		}
		if ( !empty( $fixedlinks ) ) { ?>
		<h3><?php _e( 'All done!', 'import-html-pages' ); ?></h3>
		<?php }
		else _e( 'No posts were found with the URL_before_HTML_Import custom field. Could not search for links.', 'import-html-pages' );
		//echo '<pre>'.print_r( $this->filearr, true ).'</pre>';
	}
	
	function handle_post_content( $url, $html_raw, $date_modified ) {
		$options = $this->options;
		$html = str_get_html( $html_raw );
		
		$title = $html->find( $options['title_selector'], 0 );
		$title_raw = $title->outertext;
		$title = $title->plaintext;
		if ( !empty( $options['remove_from_title'] ) )
			$title = str_replace( $options['remove_from_title'], '', $title );
		// put it back if the title is now empty
		if ( empty( $title ) )
			$title = $options['remove_from_title'];
			
		$content = $html->find( $options['content_selector'], 0 );
		if ( !empty( $content ) )
			$content = $content->innertext;
		// remove inner titles before cleaning up HTML
		if ( $options['title_inside'] )
			$content = str_replace( $title_raw, '', $content );
		$content = wp_kses( $content, wp_kses_allowed_html( 'post' ) );
		
		if ( $options['preserve_slugs'] )
			$slug = stripslashes( parse_url( $url, PHP_URL_PATH ) );
		else
			$slug = sanitize_title( $title );
		
		$excerpt = '';
		if ( $options['meta_desc'] ) {
			$excerpt = $html->find("meta[name='description']", 0);
			if ( !empty( $excerpt ) )
				$excerpt = $excerpt->content;
		}
		
		$date = '';
		if ( isset($options['date_selector']) ) {
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
					$date = $html->find( $options['date_selector'], 0 )->plaintext;
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
		
		$args = apply_filters( 'html_import_insert_post_args', array( 
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => (string) $excerpt,
			'post_type' => $options['type'],
			'post_status' => $options['status'],
			'post_name' => $slug,
			'post_author' => $author,
			'post_date' => $date,
			'meta_input' => $meta
		) );
		
		// simplehtmldom memory cleanup
		$html->clear(); 
		unset($html);
		
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
			$link = get_permalink( $post_id );
			echo "Imported $title as <a href='$link'>{$options['type']} $post_id</a>.<br />";
			flush();
			return $post_id;
		}
	}
	
	function set_thumbnail( $post_id, $html_raw ) {
		$options = get_option( 'html_import' );
		if ( empty( $options['thumbnail_selector'] ) )
			return false;
		
		$html = str_get_html( $html_raw );
		$img = $html->find( $options['thumbnail_selector'], 0 );
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
		if ( empty( $posts ) )
			return false;
		update_post_meta( $post_id, '_thumbnail_id', $posts[0]->ID ); 
		
		$html->clear(); 
		unset($html);
	}
	
	function import_single_url( $url ) {
		//$response = wp_remote_get( 'http://www.example.com' );
		$response = wp_remote_get( $url );
		if ( wp_remote_retrieve_response_code( $response ) == 200 && ! is_wp_error( $response ) ) {
			
			$options = get_option( 'html_import' );
			$body = wp_remote_retrieve_body( $response );
			$date = wp_remote_retrieve_header( $response, 'last-modified' );
			// pass the contents to SimpleHTMLDom
			$post_id = $this->handle_post_content( $url, $body, $date );
		} 
		else
			echo esc_html( $response->get_error_message() ) . '<br />';
	}
	
	function get_parent_id_by_referer( $url_before_import ) {
		global $wpdb;
		$parent_id = $wpdb->get_var( $wpdb->prepare( "select post_id from {$wpdb->postmeta} where meta_key = %s and meta_value = %s", 'URL_before_HTML_Import', esc_url_raw( $url_before_import ) ) );
		
		return $parent_id;
	}
	
	function crawl_sitemap() {
		if ( !is_array( $this->url_queue ) )
			return;
		
		$total = count( $this->url_queue );

		foreach ( $this->url_queue as $index => $url_info ) {
			$id = $this->import_single_url( $url_info['url'] );
			$percentage = round( ( $index + 1 ) / $total * 100 );
			do_action( 'html_import_display_progress', $percentage );
			sleep(.1); // be polite to other people's servers
		}
		echo __( '<br />Done importing.', 'import-html-pages' );
	}
	
	function display_progress_bar() {
		echo '<div class="progress"">
		  <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"> 
			<span id="valuenow">0%</span> 
		  </div></div>';
		flush();
	}
	
	// from $report = $crawler->getProcessReport(); 
	// $percentage = round( count( $this->url_queue ) / $report->links_followed * 100 );
	function display_progress( $percentage ) { 
		/*
		$report = $crawler->getProcessReport(); 
		$lb = "<br />";
		echo "Summary:" . $lb;
		echo "Links in sitemap: " . count( $this->url_queue );
		echo "Links followed: " . $report->links_followed . $lb;
		echo "Documents received: " . $report->files_received . $lb;
		echo "Data received: ". $this->filesize_format( $report->bytes_received ) . $lb;
		echo "Process runtime: " . $report->process_runtime . " sec" . $lb;
		/**/
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
	
	function get_sitemap( $path = NULL ) {
		
		if ( false === ( $this->url_queue = get_transient( 'html_import_sitemap_urls' ) ) ) {
			
			if ( !isset( $path ) ) {
				if ( filter_var( $this->options['get_path'], FILTER_VALIDATE_URL ) === FALSE ) {
				    echo __( 'The URL given is not valid.', 'import-html-pages' );
					return;
				}
				// request gzip first; if not found, request .xml
				// wp_remote_get() decompresses the response by default, so it should handle gzips just fine
				$sitemap = trailingslashit( $this->options['get_path'] ) . 'sitemap.xml.gz';
				$response = wp_remote_get( $sitemap );
				$response_code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $response_code || is_wp_error( $response ) ) {
					$sitemap = trailingslashit( $this->options['get_path'] ) . 'sitemap.xml';
					$response = wp_remote_get( $sitemap );
				}
			}
			else {
				$response = wp_remote_get( $path );
			}	
		
			if ( wp_remote_retrieve_response_code( $response ) == 200 && ! is_wp_error( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
				$element = new SimpleXMLElement( $body );
				if ( $element->getName() == 'sitemapindex' ) {
					foreach ( $element->sitemap as $map ) {
						$this->get_sitemap( $map->loc );
					}
				}
				elseif ( $element->getName() == 'urlset' ) {
					get_sitemap_urls( $element );
				}
				
			} 
			else
				echo esc_html( $response->get_error_message() );
		}
		
		if ( ! empty( $this->url_queue ) ) {
			set_transient( 'html_import_sitemap_urls', $this->url_queue, HOUR_IN_SECONDS );
		}
		
	}
	
	function get_sitemap_urls( $urlset ) {
		$ns = $urlset->getNamespaces(true);
		foreach ( $urlset->url as $uri ) {
			$images = $uri->children($ns['image']);
			if ( !empty( $images->image ) ) {
				$img_arr = (array) $images->image; // array keys: loc, title, caption
				$img_arr['title'] = (string) $img_arr['title'] ;
				$img_arr['caption'] = (string) $img_arr['caption'] ;
			}
			$this->url_queue[] = array(
				'url' => (string) $uri->loc,
				'lastmod' => (string) $uri->lastmod,
				'images' => $img_arr,
			);

		}
	}
	
	function start_phpcrawl() {
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
			$crawler->addContentTypeReceiveRule( '#' . $mime . '# i' );
		}
		
		// PDF is buggy for some reason; add the extension explicitly as well as the mime type
		$crawler->addContentTypeReceiveRule("#application/pdf# i");
		$crawler->addContentTypeReceiveRule("#\.(pdf)$# i");

		$crawler->setUserAgentString('WordPress-HTMLImport3(alpha)');

		// Store and send cookie-data like a browser does
		$crawler->enableCookieHandling(true);

		// Set the traffic-limit to 50 MB (in bytes); allow plugins to override this value
		$crawler->setTrafficLimit( apply_filters( 'html_import_crawler_traffic_limit', 50 * 1000 * 1024 ) );
		
		// testing
		//$crawler->setCrawlingDepthLimit(1);
		
		$crawler->go();
		
		return $crawler;
	}
	
	function receive_content( $DocInfo ) {
		// handed off from PHPCrawl's handleDocumentInfo() method using 'html_import_receive_file' action
		if ( 'text/html' !== $DocInfo->content_type ) {
			$post_id = $this->handle_import_media_file( $DocInfo );
		}
		else {
			$date = wp_remote_retrieve_header( $DocInfo->header, 'last-modified' );
			$post_id = $this->handle_post_content( $DocInfo->url, $DocInfo->source, $date );
		}
		$report = $crawler->getProcessReport( $this->crawler_id );
		if ( $report ) {
			$percentage = round( count( $this->url_queue ) / $report->links_followed * 100 );
			$this->display_progress( $percentage );
		}
	}
	
	function filesize_format( $size ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $size > 0 ? floor( log( $size, 1024 ) ) : 0;
		return number_format( $size / pow( 1024, $power ), 2, '.', ',' ) . ' ' . $units[$power];
	}
	
	function finish_phpcrawl( $crawler ) {
		
		// post-processing
		$posts = get_posts( array(
			'fields' => 'ids',
			'post_type' => $this->options['type'],
			'post_status' => $this->options['status'],
			'meta_key' => 'URL_before_HTML_Import'
		) );
		foreach ( $posts as $post_id ) {
			if ( !empty( $this->options['thumbnail_selector'] ) )
				$this->set_thumbnail( $post_id );
			// TODO: fix parent hierarchy
			// TODO: fix internal links
		}
		
		// TODO: translate these strings
		$report = $crawler->getProcessReport(); 
		$lb = "<br />";
		echo "Summary:" . $lb;
		echo "Links in sitemap: " . count( $this->url_queue );
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
				$this->get_sitemap();
				$this->display_progress_bar();
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
		add_action( 'html_import_display_progress', array( &$this, 'display_progress' ) );
	}
}

} // class_exists( 'WP_Importer' )

$html_import = new HTML_Import();

register_importer( 'html', __( 'HTML', 'import-html-pages' ), sprintf( __( 'Import the contents of HTML files as posts, pages, or any custom post type. Visit <a href="%s">the options page</a> first to select which portions of your documents should be imported.', 'import-html-pages' ), 'options-general.php?page=html-import.php' ), array ( $html_import, 'dispatch' ) );


// in case this server doesn't have php_mbstring enabled in php.ini...
if ( !function_exists( 'mb_strlen' ) ) {
	function mb_strlen( $string ) {
		return strlen( utf8_decode( $string ) );
	}
}
if ( !function_exists( 'mb_strrpos' ) ) {
	function mb_strrpos( $haystack, $needle, $offset = 0 ) {
		return strrpos( utf8_decode( $haystack ), $needle, $offset );
	}
}