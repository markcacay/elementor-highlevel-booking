<?php
/**
 * Plugin Name: Mark Cacay — Elementor HighLevel Booking
 * Description: Adds an AJAX appointment step and a HighLevel booking action to Elementor Pro Forms.
 * Version: 0.1.0
 * Author: Mark Cacay
 * Requires Plugins: elementor, elementor-pro
 * Text Domain: mc-ghl-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MC_GHL_BOOKING_VERSION', '0.1.0' );
define( 'MC_GHL_BOOKING_FILE', __FILE__ );

add_action( 'admin_menu', 'mc_ghl_booking_add_settings_page' );
add_action( 'admin_init', 'mc_ghl_booking_register_settings' );
add_action( 'elementor_pro/forms/actions/register', 'mc_ghl_booking_register_action' );
add_action( 'elementor/frontend/widget/before_render', 'mc_ghl_booking_add_widget_config' );
add_action( 'elementor/frontend/after_enqueue_scripts', 'mc_ghl_booking_enqueue_assets' );
add_action( 'wp_ajax_mc_ghl_booking_slots', 'mc_ghl_booking_slots' );
add_action( 'wp_ajax_nopriv_mc_ghl_booking_slots', 'mc_ghl_booking_slots' );

function mc_ghl_booking_add_settings_page() {
	add_options_page( 'HighLevel Booking', 'HighLevel Booking', 'manage_options', 'mc-ghl-booking', 'mc_ghl_booking_settings_page' );
}

function mc_ghl_booking_register_settings() {
	register_setting( 'mc_ghl_booking', 'mc_ghl_booking_token', array( 'sanitize_callback' => 'mc_ghl_booking_sanitize_token' ) );
}

function mc_ghl_booking_sanitize_token( $value ) {
	$value = trim( (string) $value );
	return '' === $value ? (string) get_option( 'mc_ghl_booking_token', '' ) : sanitize_text_field( $value );
}

function mc_ghl_booking_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'HighLevel Booking', 'mc-ghl-booking' ); ?></h1>
		<p><?php esc_html_e( 'Store the HighLevel Private Integration token here. It is used only by WordPress and is never sent to the browser.', 'mc-ghl-booking' ); ?></p>
		<form action="options.php" method="post">
			<?php settings_fields( 'mc_ghl_booking' ); ?>
			<table class="form-table"><tr>
				<th scope="row"><label for="mc_ghl_booking_token"><?php esc_html_e( 'Private Integration token', 'mc-ghl-booking' ); ?></label></th>
				<td><input type="password" id="mc_ghl_booking_token" name="mc_ghl_booking_token" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( get_option( 'mc_ghl_booking_token' ) ? 'Token saved — leave blank to keep it' : 'Paste token' ); ?>" />
				<p class="description"><?php esc_html_e( 'Use a sub-account Private Integration with calendars.readonly, calendars.events.write, and contacts.write access.', 'mc-ghl-booking' ); ?></p></td>
			</tr></table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function mc_ghl_booking_register_action( $registrar ) {
	if ( ! class_exists( '\ElementorPro\Modules\Forms\Classes\Action_Base' ) ) {
		return;
	}
	require_once __DIR__ . '/includes/class-mc-ghl-booking-action.php';
	$registrar->register( new MC_GHL_Booking_Action() );
}

function mc_ghl_booking_add_widget_config( $widget ) {
	if ( 'form' !== $widget->get_name() ) {
		return;
	}
	$settings = $widget->get_settings_for_display();
	$actions  = isset( $settings['submit_actions'] ) ? (array) $settings['submit_actions'] : array();
	if ( ! in_array( 'mc_ghl_booking', $actions, true ) ) {
		return;
	}
	$location = isset( $settings['mc_ghl_location_id'] ) ? sanitize_text_field( $settings['mc_ghl_location_id'] ) : '';
	$calendar = isset( $settings['mc_ghl_calendar_id'] ) ? sanitize_text_field( $settings['mc_ghl_calendar_id'] ) : '';
	$zone     = isset( $settings['mc_ghl_timezone'] ) ? sanitize_text_field( $settings['mc_ghl_timezone'] ) : 'site';
	$slot_id  = isset( $settings['mc_ghl_slot_field_id'] ) ? sanitize_key( $settings['mc_ghl_slot_field_id'] ) : 'ghl_slot';
	$nonce    = wp_create_nonce( 'mc_ghl_booking_slots' );
	$signature = hash_hmac( 'sha256', $location . '|' . $calendar . '|' . $zone, wp_salt( 'auth' ) );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-location', $location );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-calendar', $calendar );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-timezone', $zone );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-slot-field', $slot_id );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-signature', $signature );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-nonce', $nonce );
}

function mc_ghl_booking_enqueue_assets() {
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	wp_enqueue_style( 'mc-ghl-booking', plugins_url( 'assets/booking.css', __FILE__ ), array(), MC_GHL_BOOKING_VERSION );
	wp_enqueue_script( 'mc-ghl-booking', plugins_url( 'assets/booking.js', __FILE__ ), array(), MC_GHL_BOOKING_VERSION, true );
	wp_localize_script( 'mc-ghl-booking', 'mcGhlBooking', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
}

function mc_ghl_booking_slots() {
	check_ajax_referer( 'mc_ghl_booking_slots', 'nonce' );
	$location = isset( $_POST['locationId'] ) ? sanitize_text_field( wp_unslash( $_POST['locationId'] ) ) : '';
	$calendar = isset( $_POST['calendarId'] ) ? sanitize_text_field( wp_unslash( $_POST['calendarId'] ) ) : '';
	$zone     = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : '';
	$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
	$expected = hash_hmac( 'sha256', $location . '|' . $calendar . '|' . $zone, wp_salt( 'auth' ) );
	if ( ! $location || ! $calendar || ! hash_equals( $expected, $signature ) || ! in_array( $zone, array( 'site', 'visitor' ), true ) && ! in_array( $zone, timezone_identifiers_list(), true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid booking configuration.' ), 400 );
	}
	$token = get_option( 'mc_ghl_booking_token', '' );
	if ( ! $token ) {
		wp_send_json_error( array( 'message' => 'Booking is not configured yet.' ), 503 );
	}
	$timezone = 'site' === $zone ? wp_timezone_string() : ( 'visitor' === $zone ? sanitize_text_field( wp_unslash( $_POST['visitorTimezone'] ?? '' ) ) : $zone );
	if ( ! in_array( $timezone, timezone_identifiers_list(), true ) && ! preg_match( '/^[+-](?:0\d|1[0-4]):[0-5]\d$/', $timezone ) ) {
		$timezone = 'UTC';
	}
	$from = time();
	$to   = $from + ( 30 * DAY_IN_SECONDS );
	$url  = add_query_arg( array( 'startDate' => $from * 1000, 'endDate' => $to * 1000, 'timezone' => $timezone ), 'https://services.leadconnectorhq.com/calendars/' . rawurlencode( $calendar ) . '/free-slots' );
	$response = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Version' => 'v3' ) ) );
	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) {
		wp_send_json_error( array( 'message' => 'Could not load available times. Please try again.' ), 502 );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	wp_send_json_success( is_array( $data ) ? $data : array() );
}

function mc_ghl_booking_api_request( $method, $path, $body = null ) {
	$token = get_option( 'mc_ghl_booking_token', '' );
	if ( ! $token ) {
		return new WP_Error( 'mc_ghl_no_token', 'HighLevel token is not configured.' );
	}
	$args = array( 'method' => $method, 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json', 'Version' => 'v3' ) );
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$response = wp_remote_request( 'https://services.leadconnectorhq.com' . $path, $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'mc_ghl_api_error', 'HighLevel rejected the booking request.', array( 'status' => $code ) );
	}
	return is_array( $data ) ? $data : array();
}
