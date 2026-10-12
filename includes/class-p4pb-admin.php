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

	public static function defaults() {
		return [
			'enable_preconnect'   => 1,
			'enable_lcp'          => 1,
			'enable_script_delay' => 0,
			'delay_fallback_ms'   => 3000,
			'preconnect_origins'  => "https://fonts.googleapis.com\nhttps://fonts.gstatic.com\nhttps://www.googletagmanager.com\nhttps://connect.facebook.net",
			'delay_scripts'       => "hotjar\ntiktok",
			'allow_scripts'       => "googletagmanager\noptimk\noptimonk",
		];
	}

	/** Saved options merged over the defaults (same values the settings page shows). */
	public static function get_options() {
		$saved = get_option( 'p4pb_options', [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		// 1.0.x stored the timeout as gtm_delay_ms.
		if ( ! isset( $saved['delay_fallback_ms'] ) && isset( $saved['gtm_delay_ms'] ) ) {
			$saved['delay_fallback_ms'] = $saved['gtm_delay_ms'];
		}
		return wp_parse_args( $saved, self::defaults() );
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
		];
		foreach ( $checkboxes as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		$output['delay_fallback_ms']  = isset( $input['delay_fallback_ms'] ) ? min( 10000, absint( $input['delay_fallback_ms'] ) ) : 3000;
		$output['delay_scripts']      = sanitize_textarea_field( $input['delay_scripts'] ?? '' );
		$output['allow_scripts']      = sanitize_textarea_field( $input['allow_scripts'] ?? '' );
		$output['preconnect_origins'] = sanitize_textarea_field( $input['preconnect_origins'] ?? '' );

		return $output;
	}

	public function render_page() {
		$options = self::get_options();
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
								Add <code>fetchpriority="high"</code> to the first content image (LCP hero)
							</label>
							<p class="description">Tells the browser to load the hero image first. The first non-icon image on the page is used; the page HTML is never re-parsed.</p>
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
								Delay non-critical 3rd-party scripts until user interaction (Google Tag Manager is never touched)
							</label>
							<p class="description">Applies to scripts that are added by JavaScript (Hotjar, TikTok, Facebook and similar). Scripts matching the patterns below load on first user interaction or after the fallback timeout. Add <code>?p4pb=off</code> to a URL to bypass the plugin when debugging.</p>
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
								<strong>GTM, Google Analytics and OptiMonk are always whitelisted (hardcoded).</strong>
							</p>
						</td>
					</tr>

					<tr>
						<th>Fallback timeout (ms)</th>
						<td>
							<input type="number" name="p4pb_options[delay_fallback_ms]" value="<?php echo esc_attr( $options['delay_fallback_ms'] ); ?>" min="0" max="10000" step="500" style="width:100px;">
							<p class="description">If the visitor does not interact, delayed scripts load after this many milliseconds. Default: 3000ms.</p>
						</td>
					</tr>

				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}
}
