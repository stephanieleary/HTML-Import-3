<?php

class HTMLImportCrawler extends PHPCrawler {
	function handleDocumentInfo( PHPCrawlerDocumentInfo $DocInfo ) {
		// Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
	    if (PHP_SAPI == "cli") $lb = "\n";
	    else $lb = "<br />";

	    // Print the URL and the HTTP-status-Code
	    echo "Requested: ".$DocInfo->url." (".$DocInfo->http_status_code.")... ";

	    // Print if the content of the document was be recieved or not
	    if ( $DocInfo->received == true ) {
			// Now you should do something with the content of the actual
		    // received page or file ($DocInfo->source) 

			//$percentage = $this->DocumentInfoQueue->getDocumentInfoCount() / $this->queue_max_size * 100;
			//do_action( 'html_import_display_progress', $percentage );
			_e( 'OK.'.$lb, 'import-html-pages' );
			do_action( 'html_import_receive_file', $DocInfo );
		}
	    else
	    	_e( 'Not received.'.$lb, 'import-html-pages' );

	    flush();
	}
	
	/**
   * Prepares a chunk of HTML before links get searched in it
   */
  function prepareHTMLChunk(&$html_source)
  { 
    // WARNING:
    // When modifying, test thhe following regexes on a huge page for preg_replace segfaults.
    // Be sure to set the preg-groups to "non-capture" (?:...)!

    // Replace <script>-sections from source, but only those without src in it.
    if ($this->ignore_document_sections & PHPCrawlerLinkSearchDocumentSections::SCRIPT_SECTIONS)     {
      $html_source = preg_replace("#<script(?:(?!src).)*>.*(?:<\/script>|$)# Uis", "", $html_source);
      $html_source = preg_replace("#^(?:(?!<script).)*<\/script># Uis", "", $html_source);
    }

    // Replace HTML-comments from source
    if ($this->ignore_document_sections & PHPCrawlerLinkSearchDocumentSections::HTML_COMMENT_SECTIONS)     {
      $html_source = preg_replace("#<\!--.*(?:-->|$)# Uis", "", $html_source);
      $html_source = preg_replace("#^(?:(?!<\!--).)*--># Uis", "", $html_source);
    }

    // Replace javascript-triggering attributes
    if ($this->ignore_document_sections & PHPCrawlerLinkSearchDocumentSections::JS_TRIGGERING_SECTIONS)     {
      $html_source = preg_replace("#on[a-z]+\s*=\s*(?|\"([^\"]+)\"|'([^']+)'|([^\s><'\"]+))# Uis", "", $html_source);
    }

	// custom HTML Import stuff
	// don't check $this->ignore_document_sections because we're not using any of the constants
	$html = str_get_html( $html_source );
	$head = $html->find('head', 0);
	if ( $head )
		$html_source = str_replace( $head->outertext, '', $html_source );
  }
}