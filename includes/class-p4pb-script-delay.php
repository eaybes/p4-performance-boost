<?php
/**
 * Delays non-critical 3rd-party scripts until first user interaction.
 *
 * How it works (no output buffering, nothing is rewritten in the page):
 * 1. window.dataLayer is initialized immediately, so early pushes are never lost.
 * 2. A tiny inline script wraps document.createElement('script'). When a script
 *    created by JS (the way Hotjar, TikTok, Facebook, etc. load) gets a src that
 *    matches a "delay" pattern, it is held back.
 * 3. On first interaction (or after the fallback timeout) the held scripts are released.
 *
 * Anything matching the whitelist (GTM and OptiMonk are hardcoded) is NEVER delayed.
 * Google Tag Manager itself is intentionally not touched by this plugin.
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
		add_action( 'wp_head', [ $this, 'output_delay_script' ], 1 );
	}

	private static function lines( $raw ) {
		return array_values( array_unique( array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) ) ) );
	}

	public function output_delay_script() {
		if ( is_feed() || is_embed() || is_customize_preview() ) {
			return;
		}

		$options  = P4PB_Admin::get_options();
		$patterns = self::lines( $options['delay_scripts'] );

		// Always whitelisted, regardless of settings.
		$allow = array_values( array_unique( array_merge(
			[ 'googletagmanager', 'google-analytics', 'optimk', 'optimonk' ],
			self::lines( $options['allow_scripts'] )
		) ) );

		if ( empty( $patterns ) ) {
			return;
		}

		$fallback_ms = max( 0, min( 10000, (int) $options['delay_fallback_ms'] ) );
		?>
<script id="p4pb-delay">
(function() {
	'use strict';
	window.dataLayer = window.dataLayer || [];

	var DELAY_PATTERNS = <?php echo wp_json_encode( $patterns ); ?>;
	var NEVER_DELAY    = <?php echo wp_json_encode( $allow ); ?>;
	var FALLBACK_MS    = <?php echo (int) $fallback_ms; ?>;

	var triggered = false;
	var pending = [];

	function matches(list, url) {
		for (var i = 0; i < list.length; i++) {
			if (url.indexOf(list[i]) !== -1) return true;
		}
		return false;
	}

	var origCreate = document.createElement;
	document.createElement = function(tag) {
		var el = origCreate.apply(document, arguments);
		if (typeof tag !== 'string' || tag.toLowerCase() !== 'script') return el;

		var held = '';
		var proto = Object.getOwnPropertyDescriptor(HTMLScriptElement.prototype, 'src');
		if (!proto) return el;

		Object.defineProperty(el, 'src', {
			get: function() { return held || proto.get.call(el); },
			set: function(val) {
				val = String(val);
				if (!triggered && !matches(NEVER_DELAY, val) && matches(DELAY_PATTERNS, val)) {
					held = val;
					pending.push(el);
					return;
				}
				proto.set.call(el, val);
			},
			configurable: true
		});
		return el;
	};

	function release() {
		if (triggered) return;
		triggered = true;
		document.createElement = origCreate;
		var list = pending;
		pending = [];
		list.forEach(function(el) {
			var url = el.src;
			delete el.src; // back to the native property
			el.src = url;
		});
	}

	['mousemove', 'keydown', 'touchstart', 'wheel', 'scroll', 'click'].forEach(function(ev) {
		window.addEventListener(ev, release, { once: true, passive: true });
	});
	setTimeout(release, FALLBACK_MS);
})();
</script>
		<?php
	}
}
