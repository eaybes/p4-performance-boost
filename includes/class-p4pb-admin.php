<?php
/**
 * Admin settings page.
 */
class P4PB_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu() {
		add_options_page(
			'Performance Boost',
			'Performance Boost',
			'manage_options',
			'p4-performance-boost',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'p4pb_settings', 'p4pb_options', [
			'sanitize_callback' => [ $this, 'sanitize_options' ],
		] );
	}

	public function sanitize_options( $input ) {
		$output = [];

		$checkboxes = [
			'enable_preconnect',
			'enable_lcp',
			'enable_script_delay',
			'delay_gtm',
		];
		foreach ( $checkboxes as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		$output['gtm_delay_ms']       = isset( $input['gtm_delay_ms'] ) ? absint( $input['gtm_delay_ms'] ) : 3000;
		$output['delay_scripts']      = sanitize_textarea_field( $input['delay_scripts'] ?? '' );
		$output['allow_scripts']      = sanitize_textarea_field( $input['allow_scripts'] ?? '' );
		$output['preconnect_origins'] = sanitize_textarea_field( $input['preconnect_origins'] ?? '' );
		$output['lcp_selector']       = sanitize_text_field( $input['lcp_selector'] ?? '' );

		return $output;
	}

	public function render_page() {
		$options = get_option( 'p4pb_options', [] );
		$defaults = [
			'enable_preconnect'  => 1,
			'enable_lcp'         => 1,
			'enable_script_delay' => 0,
			'delay_gtm'          => 0,
			'gtm_delay_ms'       => 3000,
			'lcp_selector'       => '.page-header-image img, .carousel-item.active img, article img:first-of-type',
			'preconnect_origins' => "https://fonts.googleapis.com\nhttps://fonts.gstatic.com\nhttps://www.googletagmanager.com\nhttps://connect.facebook.net",
			'delay_scripts'      => "hotjar\noptimizely\nhs-scripts\ntiktok",
			'allow_scripts'      => "googletagmanager\noptimk\noptimonk",
		];
		$options = wp_parse_args( $options, $defaults );
		?>
		<div class="wrap">
			<h1>⚡ Planet4 Performance Boost</h1>
			<p style="color:#666;">All features are independent — enable/disable each safely.</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'p4pb_settings' ); ?>

				<table class="form-table" role="presentation">

					<!-- PRECONNECT -->
					<tr>
						<th scope="row" colspan="2">
							<h2 style="border-bottom:1px solid #eee;padding-bottom:8px;">🔗 Preconnect Hints</h2>
						</th>
					</tr>
					<tr>
						<th>Enable</th>
						<td>
							<label>
								<input type="checkbox" name="p4pb_options[enable_preconnect]" value="1" <?php checked( $options['enable_preconnect'] ); ?>>
								Add <code>preconnect</code> and <code>dns-prefetch</code> hints for external origins
							</label>
							<p class="description">Tells the browser to open connections early to external domains — safe, no side effects.</p>
						</td>
					</tr>
					<tr>
						<th>Origins (one per line)</th>
						<td>
							<textarea name="p4pb_options[preconnect_origins]" rows="6" class="large-text"><?php echo esc_textarea( $options['preconnect_origins'] ); ?></textarea>
							<p class="description">Add any external domain you want the browser to preconnect to.</p>
						</td>
					</tr>

					<!-- LCP -->
					<tr>
						<th scope="row" colspan="2">
							<h2 style="border-bottom:1px solid #eee;padding-bottom:8px;">🖼️ LCP Image Priority</h2>
						</th>
					</tr>
					<tr>
						<th>Enable</th>
						<td>
							<label>
								<input type="checkbox" name="p4pb_options[enable_lcp]" value="1" <?php checked( $options['enable_lcp'] ); ?>>
								Add <code>fetchpriority="high"</code> to the LCP hero image
							</label>
							<p class="description">PageSpeed identified this as missing. Tells the browser to prioritize loading the hero image.</p>
						</td>
					</tr>
					<tr>
						<th>CSS Selector</th>
						<td>
							<input type="text" name="p4pb_options[lcp_selector]" value="<?php echo esc_attr( $options['lcp_selector'] ); ?>" class="large-text">
							<p class="description">CSS selector for the hero/LCP image. The first match gets <code>fetchpriority="high"</code> and <code>loading="eager"</code>.</p>
						</td>
					</tr>

					<!-- SCRIPT DELAY -->
					<tr>
						<th scope="row" colspan="2">
							<h2 style="border-bottom:1px solid #eee;padding-bottom:8px;">⏱️ Script Delay</h2>
						</th>
					</tr>
					<tr>
						<th>Enable Script Delay</th>
						<td>
							<label>
								<input type="checkbox" name="p4pb_options[enable_script_delay]" value="1" <?php checked( $options['enable_script_delay'] ); ?>>
								Delay non-critical 3rd-party scripts until user interaction
							</label>
							<p class="description">Scripts matching the patterns below will load on first user interaction (scroll, click, mousemove) or after the fallback timeout.</p>
						</td>
					</tr>
					<tr>
						<th>Scripts to delay (URL patterns)</th>
						<td>
							<textarea name="p4pb_options[delay_scripts]" rows="6" class="large-text"><?php echo esc_textarea( $options['delay_scripts'] ); ?></textarea>
							<p class="description">One pattern per line. Any script whose URL contains this string will be delayed. Example: <code>hotjar</code>, <code>tiktok</code>.</p>
						</td>
					</tr>
					<tr>
						<th>⚡ Never delay (whitelist)</th>
						<td>
							<textarea name="p4pb_options[allow_scripts]" rows="6" class="large-text"><?php echo esc_textarea( $options['allow_scripts'] ); ?></textarea>
							<p class="description">
								One pattern per line. Scripts matching these patterns will <strong>always load immediately</strong>, even if they also match a delay pattern.<br>
								<strong>GTM and OptiMonk are whitelisted by default.</strong>
							</p>
						</td>
					</tr>

					<!-- GTM DELAY -->
					<tr>
						<th>Delay Google Tag Manager</th>
						<td>
							<label>
								<input type="checkbox" name="p4pb_options[delay_gtm]" value="1" <?php checked( $options['delay_gtm'] ); ?>>
								Load GTM after user interaction (saves ~1,461ms main thread time)
							</label>
							<p class="description">
								<strong>⚠️ Test carefully before enabling on production.</strong><br>
								<code>window.dataLayer</code> is initialized immediately, so manual dataLayer pushes still work.
								GTM loads on first user interaction or after the timeout below.
							</p>
						</td>
					</tr>
					<tr>
						<th>GTM fallback timeout (ms)</th>
						<td>
							<input type="number" name="p4pb_options[gtm_delay_ms]" value="<?php echo esc_attr( $options['gtm_delay_ms'] ); ?>" min="0" max="10000" step="500" style="width:100px;">
							<p class="description">If no user interaction occurs, GTM loads after this many milliseconds. Default: 3000ms.</p>
						</td>
					</tr>

				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}
}
