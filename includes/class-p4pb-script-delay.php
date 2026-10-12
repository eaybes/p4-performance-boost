<?php
/**
 * Delays non-critical 3rd-party scripts and optionally GTM.
 *
 * How it works:
 * 1. window.dataLayer is initialized immediately (so manual pushes work).
 * 2. A list of "delayed" script patterns is sent to the browser.
 * 3. An inline JS intercepts document.createElement('script') for matching scripts
 *    and holds them until the first user interaction OR the fallback timeout.
 * 4. GTM delay (if enabled) re-implements GTM loading after interaction.
 *
 * This approach does NOT use output buffering — it's safe and predictable.
 */
class P4PB_Script_Delay {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Output the delay JS as early as possible in <head>.
		add_action( 'wp_head', [ $this, 'output_delay_script' ], 1 );
	}

	public function output_delay_script() {
		$options  = get_option( 'p4pb_options', [] );
		$raw      = $options['delay_scripts'] ?? '';
		$patterns = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );

		$raw_allow     = $options['allow_scripts'] ?? '';
		$allow_patterns = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_allow ) ) ) );
		// Always whitelist GTM and OptiMonk regardless of settings.
		$always_allow = [ 'googletagmanager', 'optimk', 'optimonk' ];
		$allow_patterns = array_unique( array_merge( $always_allow, $allow_patterns ) );

		$delay_gtm  = ! empty( $options['delay_gtm'] );
		$gtm_delay  = (int) ( $options['gtm_delay_ms'] ?? 3000 );

		// Detect GTM ID from the page source — look for GTM-XXXXX in existing scripts.
		// We read it via PHP from the site's known GTM ID (set in Planet4 settings or wp_head output).
		// As a fallback we let the JS find it itself.
		$gtm_id = $this->detect_gtm_id();

		$patterns_json      = wp_json_encode( $patterns );
		$allow_patterns_json = wp_json_encode( array_values( $allow_patterns ) );
		$gtm_id_json        = wp_json_encode( $gtm_id );
		?>
<script id="p4pb-delay">
(function() {
	'use strict';

	// ── 1. Always initialize dataLayer immediately ──────────────────────────
	window.dataLayer = window.dataLayer || [];

	// ── 2. Patterns to delay / never delay ─────────────────────────────────
	var DELAY_PATTERNS = <?php echo $patterns_json; ?>;
	var NEVER_DELAY    = <?php echo $allow_patterns_json; ?>; // whitelist — always load immediately
	var DELAY_GTM      = <?php echo $delay_gtm ? 'true' : 'false'; ?>;
	var GTM_ID         = <?php echo $gtm_id_json; ?>;
	var FALLBACK_MS    = <?php echo (int) $gtm_delay; ?>;

	var triggered = false;
	var pendingScripts = [];

	// ── 3. Intercept script creation for matching patterns ──────────────────
	var _origCreateElement = document.createElement.bind(document);
	document.createElement = function(tag) {
		var el = _origCreateElement(tag);
		if (tag.toLowerCase() !== 'script') return el;

		// Proxy the src setter to intercept matching scripts.
		var _src = '';
		Object.defineProperty(el, 'src', {
			get: function() { return _src; },
			set: function(val) {
				var shouldDelay = false;

				// Check whitelist first — these always load immediately.
				var isWhitelisted = false;
				for (var w = 0; w < NEVER_DELAY.length; w++) {
					if (val && val.indexOf(NEVER_DELAY[w]) !== -1) {
						isWhitelisted = true;
						break;
					}
				}

				if (!isWhitelisted && DELAY_PATTERNS.length > 0) {
					for (var i = 0; i < DELAY_PATTERNS.length; i++) {
						if (val && val.indexOf(DELAY_PATTERNS[i]) !== -1) {
							shouldDelay = true;
							break;
						}
					}
				}
				if (shouldDelay && !triggered) {
					_src = val;
					pendingScripts.push(el);
					return;
				}
				_src = val;
				el.setAttribute('src', val);
			},
			configurable: true,
		});
		return el;
	};

	// ── 4. GTM safe loader ──────────────────────────────────────────────────
	var gtmLoaded = false;
	function loadGTM() {
		if (gtmLoaded || !DELAY_GTM || !GTM_ID) return;
		gtmLoaded = true;
		// Standard GTM snippet
		window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
		var f = document.getElementsByTagName('script')[0];
		var j = _origCreateElement('script');
		j.async = true;
		j.src = 'https://www.googletagmanager.com/gtm.js?id=' + GTM_ID;
		f.parentNode.insertBefore(j, f);

		// Also inject the noscript iframe
		var noscript = document.createElement('noscript');
		var iframe = document.createElement('iframe');
		iframe.src = 'https://www.googletagmanager.com/ns.html?id=' + GTM_ID;
		iframe.height = '0';
		iframe.width = '0';
		iframe.style.display = 'none';
		iframe.style.visibility = 'hidden';
		noscript.appendChild(iframe);
		document.body && document.body.insertBefore(noscript, document.body.firstChild);
	}

	// ── 5. Release all pending scripts and load GTM ─────────────────────────
	function onUserInteraction() {
		if (triggered) return;
		triggered = true;

		// Restore createElement
		document.createElement = _origCreateElement;

		// Load GTM
		loadGTM();

		// Release delayed scripts
		pendingScripts.forEach(function(el) {
			el.setAttribute('src', el.src);
		});
		pendingScripts = [];
	}

	// ── 6. Interaction events ────────────────────────────────────────────────
	var events = ['mousemove', 'keydown', 'touchstart', 'wheel', 'scroll', 'click'];
	events.forEach(function(ev) {
		document.addEventListener(ev, onUserInteraction, { once: true, passive: true });
	});

	// ── 7. Fallback timeout ──────────────────────────────────────────────────
	setTimeout(onUserInteraction, FALLBACK_MS);

})();
</script>
		<?php
	}

	/**
	 * Try to detect the GTM ID from Planet4 / WordPress settings.
	 * Returns the ID string (e.g. "GTM-XXXXXX") or null.
	 */
	private function detect_gtm_id() {
		// Planet4 stores GTM ID in options.
		$gtm_id = get_option( 'google_tag_manager_identifier' )
			?? get_option( 'gtm_id' )
			?? null;

		if ( $gtm_id ) {
			return sanitize_text_field( $gtm_id );
		}

		// Try to find it in p4_options or p4_settings.
		$p4_options = get_option( 'planet4_options', [] );
		if ( ! empty( $p4_options['google_tag_manager_identifier'] ) ) {
			return sanitize_text_field( $p4_options['google_tag_manager_identifier'] );
		}

		return null;
	}
}
