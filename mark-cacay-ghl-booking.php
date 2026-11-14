<?php
/**
 * Plugin Name: Elementor HighLevel Booking
 * Description: Adds an AJAX appointment step and a HighLevel booking action to Elementor Pro Forms.
 * Version: 0.4.5
 * Author: Mark Cacay
 * Author URI: https://www.linkedin.com/in/mark-cacay-858a1896/
 * Requires Plugins: elementor, elementor-pro
 * Text Domain: mc-ghl-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MC_GHL_BOOKING_VERSION', '0.4.5' );
define( 'MC_GHL_BOOKING_FILE', __FILE__ );

add_action( 'admin_menu', 'mc_ghl_booking_add_settings_page' );
add_action( 'admin_init', 'mc_ghl_booking_register_settings' );
add_action( 'elementor_pro/forms/actions/register', 'mc_ghl_booking_register_action' );
add_action( 'elementor/frontend/widget/before_render', 'mc_ghl_booking_add_widget_config' );
add_filter( 'elementor/widget/render_content', 'mc_ghl_booking_add_form_content_config', 10, 2 );
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
				<p class="description"><?php esc_html_e( 'Use a sub-account Private Integration with calendars.readonly, calendars.events.write, contacts.write, locations.readonly, and locations/customFields.readonly access. The last two read-only scopes are used for the meeting location phone and resolving custom field keys.', 'mc-ghl-booking' ); ?></p></td>
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
	$settings = $widget->get_settings_for_display();
	if ( 'form' === $widget->get_name() ) {
		$config = mc_ghl_booking_config_from_form_settings( $settings );
	} elseif ( 'global' === $widget->get_name() ) {
		$config = mc_ghl_booking_config_from_form_settings( $settings );
		if ( ! $config ) {
			$template_id = absint( $widget->get_data( 'templateID' ) );
			$config      = $template_id ? mc_ghl_booking_config_from_global_template( $template_id ) : false;
		}
	} else {
		return;
	}
	if ( ! $config ) {
		return;
	}
	$location = $config['location'];
	$calendar = $config['calendar'];
	$zone     = 'visitor';
	$slot_id  = $config['slot_id'];
	$duration = $config['duration'];
	$meeting_location = $config['meeting_location'];
	$nonce     = wp_create_nonce( 'mc_ghl_booking_slots' );
	$signature = hash_hmac( 'sha256', $location . '|' . $calendar . '|' . $zone, wp_salt( 'auth' ) );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-calendar', $calendar );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-location', $location );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-timezone', $zone );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-site-timezone', wp_timezone_string() );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-slot-field', $slot_id );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-duration', max( 1, $duration ) );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-meeting-location', $meeting_location );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-signature', $signature );
	$widget->add_render_attribute( '_wrapper', 'data-mc-ghl-nonce', $nonce );
}

function mc_ghl_booking_config_from_form_settings( $settings ) {
	$actions = isset( $settings['submit_actions'] ) ? (array) $settings['submit_actions'] : array();
	if ( ! in_array( 'mc_ghl_booking', $actions, true ) ) {
		return false;
	}
	$location = isset( $settings['mc_ghl_location_id'] ) ? sanitize_text_field( $settings['mc_ghl_location_id'] ) : '';
	$calendar = isset( $settings['mc_ghl_calendar_id'] ) ? sanitize_text_field( $settings['mc_ghl_calendar_id'] ) : '';
	$slot_id  = isset( $settings['mc_ghl_slot_field_id'] ) ? sanitize_key( $settings['mc_ghl_slot_field_id'] ) : 'ghl_slot';
	$duration = isset( $settings['mc_ghl_meeting_duration'] ) ? absint( $settings['mc_ghl_meeting_duration'] ) : 30;
	$meeting_location = isset( $settings['mc_ghl_meeting_location'] ) ? sanitize_text_field( $settings['mc_ghl_meeting_location'] ) : '{{location.phone}}';
	if ( '' === $location || '' === $calendar ) {
		return false;
	}
	return array(
		'location'         => $location,
		'calendar'         => $calendar,
		'slot_id'          => $slot_id,
		'duration'         => $duration,
		'meeting_location' => $meeting_location,
	);
}

function mc_ghl_booking_config_from_global_template( $template_id ) {
	$raw_data = get_post_meta( $template_id, '_elementor_data', true );
	if ( is_string( $raw_data ) ) {
		$elements = json_decode( $raw_data, true );
	} else {
		$elements = $raw_data;
	}
	if ( ! is_array( $elements ) ) {
		return false;
	}
	$find_form = function ( $nodes ) use ( &$find_form ) {
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( 'widget' === ( $node['elType'] ?? '' ) && 'form' === ( $node['widgetType'] ?? '' ) ) {
				$config = mc_ghl_booking_config_from_form_settings( (array) ( $node['settings'] ?? array() ) );
				if ( $config ) {
					return $config;
				}
			}
			if ( ! empty( $node['elements'] ) ) {
				$config = $find_form( $node['elements'] );
				if ( $config ) {
					return $config;
				}
			}
		}
		return false;
	};
	return $find_form( $elements );
}

function mc_ghl_booking_add_form_content_config( $content, $widget ) {
	if ( ! is_object( $widget ) || 'form' !== $widget->get_name() || false !== strpos( $content, 'data-mc-ghl-calendar=' ) ) {
		return $content;
	}
	$config = mc_ghl_booking_config_from_form_settings( $widget->get_settings_for_display() );
	if ( ! $config ) {
		return $content;
	}
	$zone      = 'visitor';
	$signature = hash_hmac( 'sha256', $config['location'] . '|' . $config['calendar'] . '|' . $zone, wp_salt( 'auth' ) );
	$attributes = array(
		'data-mc-ghl-calendar'         => $config['calendar'],
		'data-mc-ghl-location'         => $config['location'],
		'data-mc-ghl-timezone'         => $zone,
		'data-mc-ghl-site-timezone'    => wp_timezone_string(),
		'data-mc-ghl-slot-field'       => $config['slot_id'],
		'data-mc-ghl-duration'         => max( 1, $config['duration'] ),
		'data-mc-ghl-meeting-location' => $config['meeting_location'],
		'data-mc-ghl-signature'        => $signature,
		'data-mc-ghl-nonce'            => wp_create_nonce( 'mc_ghl_booking_slots' ),
	);
	$markup = '';
	foreach ( $attributes as $name => $value ) {
		$markup .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}
	return preg_replace( '/<form\\b/i', '<form' . $markup, $content, 1 );
}

function mc_ghl_booking_enqueue_assets() {
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	wp_enqueue_style( 'mc-ghl-booking', plugins_url( 'assets/booking.css', __FILE__ ), array(), MC_GHL_BOOKING_VERSION );
	wp_enqueue_script( 'mc-ghl-booking', plugins_url( 'assets/booking.js', __FILE__ ), array(), MC_GHL_BOOKING_VERSION, true );
	wp_localize_script( 'mc-ghl-booking', 'mcGhlBooking', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'timezones' => timezone_identifiers_list() ) );
}

function mc_ghl_booking_slots() {
	check_ajax_referer( 'mc_ghl_booking_slots', 'nonce' );
	$location = isset( $_POST['locationId'] ) ? sanitize_text_field( wp_unslash( $_POST['locationId'] ) ) : '';
	$calendar = isset( $_POST['calendarId'] ) ? sanitize_text_field( wp_unslash( $_POST['calendarId'] ) ) : '';
	$zone     = isset( $_POST['zone'] ) ? sanitize_text_field( wp_unslash( $_POST['zone'] ) ) : '';
	$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
	$expected = hash_hmac( 'sha256', $location . '|' . $calendar . '|' . $zone, wp_salt( 'auth' ) );
	if ( ! $location || ! $calendar || ! hash_equals( $expected, $signature ) || 'visitor' !== $zone ) {
		wp_send_json_error( array( 'message' => 'Invalid booking configuration.' ), 400 );
	}
	$token = get_option( 'mc_ghl_booking_token', '' );
	if ( ! $token ) {
		wp_send_json_error( array( 'message' => 'Booking is not configured yet.' ), 503 );
	}
	$timezone = sanitize_text_field( wp_unslash( $_POST['requestedTimezone'] ?? '' ) );
	if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid timezone selection.' ), 400 );
	}
	$timezone_object = new DateTimeZone( $timezone );
	$tomorrow_start  = new DateTimeImmutable( 'tomorrow', $timezone_object );
	$from = $tomorrow_start->getTimestamp();
	$to   = $from + ( 30 * DAY_IN_SECONDS );
	$url  = add_query_arg( array( 'startDate' => $from * 1000, 'endDate' => $to * 1000, 'timezone' => $timezone ), 'https://services.leadconnectorhq.com/calendars/' . rawurlencode( $calendar ) . '/free-slots' );
	$response = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Version' => 'v3' ) ) );
	if ( is_wp_error( $response ) ) {
		$error = array( 'message' => 'Could not load available times. Please try again.' );
		if ( current_user_can( 'manage_options' ) ) {
			$error['debug'] = 'WordPress request error: ' . sanitize_text_field( $response->get_error_message() );
		}
		wp_send_json_error( $error, 502 );
	}
	$http_code = wp_remote_retrieve_response_code( $response );
	if ( $http_code < 200 || $http_code >= 300 ) {
		$error = array( 'message' => 'Could not load available times. Please try again.' );
		if ( current_user_can( 'manage_options' ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$detail = is_array( $body ) ? ( $body['message'] ?? $body['error_description'] ?? $body['error'] ?? '' ) : '';
			$error['debug'] = 'HighLevel HTTP ' . absint( $http_code ) . ( $detail ? ': ' . sanitize_text_field( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' );
		}
		wp_send_json_error( $error, 502 );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$fallback_duration = isset( $_POST['fallbackDuration'] ) ? min( 1440, max( 1, absint( $_POST['fallbackDuration'] ) ) ) : 30;
	$details = array( 'duration' => $fallback_duration, 'location' => '' );
	$calendar_data = get_transient( 'mc_ghl_calendar_' . md5( $calendar ) );
	if ( false === $calendar_data ) {
		$calendar_data = mc_ghl_booking_api_request( 'GET', '/calendars/' . rawurlencode( $calendar ) );
		if ( ! is_wp_error( $calendar_data ) ) {
			set_transient( 'mc_ghl_calendar_' . md5( $calendar ), $calendar_data, HOUR_IN_SECONDS );
		}
	}
	$calendar_info = is_array( $calendar_data ) ? ( $calendar_data['calendar'] ?? $calendar_data ) : array();
	if ( ! empty( $calendar_info['slotDuration'] ) ) {
		$details['duration'] = absint( $calendar_info['slotDuration'] ) * ( 'hours' === ( $calendar_info['slotDurationUnit'] ?? 'mins' ) ? 60 : 1 );
	} elseif ( ! empty( $calendar_info['durationOptions'] ) && is_array( $calendar_info['durationOptions'] ) ) {
		foreach ( $calendar_info['durationOptions'] as $option ) {
			if ( ! empty( $option['default'] ) ) {
				$details['duration'] = absint( $option['duration'] ?? $fallback_duration ) * ( 'hours' === ( $option['unit'] ?? 'mins' ) ? 60 : 1 );
				break;
			}
		}
	}
	$requested_location = sanitize_text_field( wp_unslash( $_POST['meetingLocation'] ?? '' ) );
	if ( '{{location.phone}}' === strtolower( $requested_location ) ) {
		$location_data = get_transient( 'mc_ghl_location_' . md5( $location ) );
		if ( false === $location_data ) {
			$location_data = mc_ghl_booking_api_request( 'GET', '/locations/' . rawurlencode( $location ) );
			if ( ! is_wp_error( $location_data ) ) {
				set_transient( 'mc_ghl_location_' . md5( $location ), $location_data, HOUR_IN_SECONDS );
			}
		}
		$location_info = is_array( $location_data ) ? ( $location_data['location'] ?? $location_data ) : array();
		$details['location'] = sanitize_text_field( $location_info['phone'] ?? '' );
	} else {
		$details['location'] = $requested_location;
	}
	wp_send_json_success( array( 'slots' => is_array( $data ) ? $data : array(), 'timezone' => $timezone, 'meeting' => $details ) );
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
