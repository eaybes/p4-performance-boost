<?php
/**
 * Adds fetchpriority="high" and loading="eager" to the LCP hero image.
 *
 * The page is buffered and ONE <img> tag is edited with a plain string
 * replacement. The HTML is never parsed and re-serialized (DOMDocument), so
 * scripts, GTM snippets, JSON-LD, entities and Hebrew text pass through
 * byte-for-byte untouched.
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
		add_action( 'template_redirect', [ $this, 'start_buffer' ], 1 );
	}

	public function start_buffer() {
		if (
			is_admin() || is_feed() || is_embed() || is_customize_preview() || is_robots() ||
			wp_doing_ajax() || wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			( function_exists( 'wp_is_xml_request' ) && wp_is_xml_request() )
		) {
			return;
		}
		ob_start( [ $this, 'process_output' ] );
	}

	/**
	 * Find the first suitable <img> and add the priority attributes.
	 */
	public function process_output( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<img' ) ) {
			return $html;
		}
		return self::prioritize_first_image( $html );
	}

	/**
	 * Pure function (testable without WordPress).
	 */
	public static function prioritize_first_image( $html ) {
		// Only look inside <body>.
		$body_pos = stripos( $html, '<body' );
		$offset   = false === $body_pos ? 0 : $body_pos;

		// Ranges where an <img> must be ignored (not real page content).
		$skip_ranges = [];
		if ( preg_match_all( '#<(script|noscript|style|template)\b[^>]*>.*?</\1\s*>|<!--.*?-->#is', $html, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			foreach ( $m[0] as $hit ) {
				$skip_ranges[] = [ $hit[1], $hit[1] + strlen( $hit[0] ) ];
			}
		}

		if ( ! preg_match_all( '#<img\b[^>]*>#i', $html, $imgs, PREG_OFFSET_CAPTURE, $offset ) ) {
			return $html;
		}

		$skip_patterns = [ 'icon', 'logo', 'avatar', 'thumb', 'sprite', 'flag', '.svg', 'pixel', 'tracking' ];
		$checked       = 0;

		foreach ( $imgs[0] as $img ) {
			list( $tag, $pos ) = $img;

			foreach ( $skip_ranges as $r ) {
				if ( $pos >= $r[0] && $pos < $r[1] ) {
					continue 2;
				}
			}

			// Only consider the first few real images: the LCP image is near the top.
			if ( ++$checked > 6 ) {
				return $html;
			}

			// Someone (WordPress core, the theme) already prioritized an image: done.
			if ( preg_match( '#\sfetchpriority\s*=#i', $tag ) ) {
				return $html;
			}

			// Tiny images (declared width < 100).
			if ( preg_match( '#\swidth\s*=\s*["\']?(\d+)#i', $tag, $w ) && (int) $w[1] < 100 ) {
				continue;
			}

			// Icons, logos, avatars, tracking pixels...
			$haystack = '';
			if ( preg_match( '#\sclass\s*=\s*(["\'])(.*?)\1#is', $tag, $c ) ) {
				$haystack .= strtolower( $c[2] );
			}
			if ( preg_match( '#\ssrc\s*=\s*(["\'])(.*?)\1#is', $tag, $s ) ) {
				$haystack .= ' ' . strtolower( $s[2] );
				if ( 0 === strpos( $s[2], 'data:' ) ) {
					continue;
				}
			}
			foreach ( $skip_patterns as $p ) {
				if ( false !== strpos( $haystack, $p ) ) {
					continue 2;
				}
			}

			// Candidate found: drop an existing loading attribute, add ours.
			$new = preg_replace( '#\sloading\s*=\s*(["\']?)[a-z]*\1#i', '', $tag );
			$new = preg_replace( '#\s*/?>$#', ' fetchpriority="high" loading="eager"$0', $new, 1 );
			if ( null === $new ) {
				return $html;
			}
			return substr_replace( $html, $new, $pos, strlen( $tag ) );
		}

		return $html;
	}
}
