<?php
/**
 * Adds fetchpriority="high" and loading="eager" to the LCP hero image.
 * Uses output buffering on the full page HTML so it works regardless of
 * how the theme outputs the image.
 */
class P4PB_LCP {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Start output buffering very early.
		add_action( 'template_redirect', [ $this, 'start_buffer' ], 1 );
	}

	public function start_buffer() {
		// Only on front-end, not admin/REST/AJAX.
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start( [ $this, 'process_output' ] );
	}

	/**
	 * Process the full page HTML to find and prioritize the LCP image.
	 */
	public function process_output( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// We work with a DOMDocument to safely modify the HTML.
		$options = get_option( 'p4pb_options', [] );

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );

		// Find all <img> tags — we'll tag the first "large" one or use a position heuristic.
		// Strategy: mark the FIRST img that is NOT a tiny icon (no width attr < 100, no class containing icon/logo/avatar).
		$images = $xpath->query( '//img' );

		$prioritized = false;
		foreach ( $images as $img ) {
			/** @var DOMElement $img */

			// Skip images that already have fetchpriority set.
			if ( $img->hasAttribute( 'fetchpriority' ) ) {
				$prioritized = true;
				break;
			}

			// Skip images that are loading=lazy (they are explicitly non-priority).
			if ( $img->getAttribute( 'loading' ) === 'lazy' ) {
				continue;
			}

			// Skip tiny icons: skip if width attr is set and < 100.
			$width = $img->getAttribute( 'width' );
			if ( $width && (int) $width < 100 ) {
				continue;
			}

			// Skip images with icon/logo/avatar/thumb in class or src.
			$class = strtolower( $img->getAttribute( 'class' ) );
			$src   = strtolower( $img->getAttribute( 'src' ) );
			$skip_patterns = [ 'icon', 'logo', 'avatar', 'thumb', 'sprite', 'flag', 'svg' ];
			$skip = false;
			foreach ( $skip_patterns as $pattern ) {
				if ( strpos( $class, $pattern ) !== false || strpos( $src, $pattern ) !== false ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}

			// This is our LCP candidate.
			$img->setAttribute( 'fetchpriority', 'high' );
			$img->setAttribute( 'loading', 'eager' );
			$prioritized = true;
			break;
		}

		// If we didn't prioritize anything, return the original HTML unchanged.
		if ( ! $prioritized ) {
			return $html;
		}

		// Serialize back to HTML.
		$new_html = $doc->saveHTML();

		// Clean up the xml declaration we added.
		$new_html = str_replace( '<?xml encoding="UTF-8">', '', $new_html );

		return $new_html ?: $html;
	}
}
