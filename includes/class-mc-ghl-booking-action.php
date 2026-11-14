<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MC_GHL_Booking_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {
	public function get_name(): string { return 'mc_ghl_booking'; }
	public function get_label(): string { return esc_html__( 'Book with HighLevel', 'mc-ghl-booking' ); }

	public function register_settings_section( $widget ): void {
		$widget->start_controls_section( 'mc_ghl_booking_section', array( 'label' => esc_html__( 'HighLevel Booking', 'mc-ghl-booking' ), 'condition' => array( 'submit_actions' => $this->get_name() ) ) );
		$widget->add_control( 'mc_ghl_location_id', array( 'label' => esc_html__( 'HighLevel Location ID', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true ) );
		$widget->add_control( 'mc_ghl_calendar_id', array( 'label' => esc_html__( 'Free Consultation Calendar ID', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true ) );
		$widget->add_control( 'mc_ghl_mapping_help', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => esc_html__( 'Add one row for every form field to send. Use the Elementor Field ID as the source. For custom fields, enter either the HighLevel custom field ID or field key (for example contact.type_of_virtual_assistant). You may also enter a shortcode as the target; it runs during submission and can read submitted values. Field keys require locations/customFields.readonly token access.', 'mc-ghl-booking' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'source_id', array( 'label' => esc_html__( 'Elementor Field ID', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true ) );
		$repeater->add_control( 'target_type', array( 'label' => esc_html__( 'HighLevel field type', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'standard', 'options' => array( 'standard' => esc_html__( 'Standard contact field', 'mc-ghl-booking' ), 'custom' => esc_html__( 'Custom field', 'mc-ghl-booking' ), 'tag' => esc_html__( 'Contact tag', 'mc-ghl-booking' ) ) ) );
		$repeater->add_control( 'target_field', array( 'label' => esc_html__( 'HighLevel field name, custom field ID, or key', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true, 'description' => 'Examples: email, phone, firstName, a custom field ID/key. Leave blank for Contact tag; the source value is added as the tag.' ) );
		$widget->add_control( 'mc_ghl_field_mappings', array( 'label' => esc_html__( 'Field mappings', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ source_id }}} → {{{ target_field }}}', 'default' => array() ) );
		$widget->add_control( 'mc_ghl_slot_field_id', array( 'label' => esc_html__( 'Appointment slot field ID', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'ghl_slot', 'description' => esc_html__( 'The plugin supplies this hidden value automatically. Add an Elementor Hidden field with this ID only if you also want it in saved form submissions.', 'mc-ghl-booking' ) ) );
		$widget->add_control( 'mc_ghl_appointment_title', array( 'label' => esc_html__( 'Appointment title', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Free Consultation' ) );
		$widget->add_control( 'mc_ghl_meeting_duration', array( 'label' => esc_html__( 'Meeting duration (minutes)', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 30, 'min' => 1, 'max' => 1440 ) );
		$widget->add_control( 'mc_ghl_meeting_location', array( 'label' => esc_html__( 'Meeting location shown after booking', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '{{location.phone}}', 'label_block' => true, 'description' => 'Use a phone number or text, or {{location.phone}} to show the HighLevel location phone. The latter needs locations.readonly token access.' ) );
		$widget->end_controls_section();
	}

	public function run( $record, $ajax_handler ): void {
		$settings = $record->get( 'form_settings' );
		$location = sanitize_text_field( $settings['mc_ghl_location_id'] ?? '' );
		$calendar = sanitize_text_field( $settings['mc_ghl_calendar_id'] ?? '' );
		$slot_id  = sanitize_key( $settings['mc_ghl_slot_field_id'] ?? 'ghl_slot' );
		$slot     = isset( $_POST['form_fields'][ $slot_id ] ) ? sanitize_text_field( wp_unslash( $_POST['form_fields'][ $slot_id ] ) ) : '';
		$booking_timezone = isset( $_POST['form_fields']['mc_ghl_booking_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['form_fields']['mc_ghl_booking_timezone'] ) ) : '';
		if ( ! $location || ! $calendar || ! $slot || ! in_array( $booking_timezone, timezone_identifiers_list(), true ) ) {
			$ajax_handler->add_error_message( esc_html__( 'Please select an available appointment time.', 'mc-ghl-booking' ) );
			return;
		}
		$start = strtotime( $slot );
		$tomorrow_start = ( new DateTimeImmutable( 'tomorrow', new DateTimeZone( $booking_timezone ) ) )->getTimestamp();
		if ( ! $start || $start < $tomorrow_start ) {
			$ajax_handler->add_error_message( esc_html__( 'That appointment time is no longer available. Please select another time.', 'mc-ghl-booking' ) );
			return;
		}
		$raw_fields = $record->get( 'fields' );
		$values = array();
		foreach ( (array) $raw_fields as $id => $field ) {
			$values[ sanitize_key( $id ) ] = is_array( $field['value'] ?? null ) ? implode( ', ', $field['value'] ) : sanitize_text_field( (string) ( $field['value'] ?? '' ) );
		}
		$custom_fields = array();
		$tags          = array();
		$contact = array( 'locationId' => $location, 'source' => 'Elementor HighLevel Booking' );
		$standard_allowed = array( 'firstName', 'lastName', 'name', 'email', 'phone', 'address1', 'city', 'state', 'postalCode', 'website', 'timezone', 'country', 'companyName' );
		$previous_submission_fields = $GLOBALS['mc_ghl_booking_submission_fields'] ?? null;
		$GLOBALS['mc_ghl_booking_submission_fields'] = $values;
		foreach ( (array) ( $settings['mc_ghl_field_mappings'] ?? array() ) as $mapping ) {
			$source = sanitize_key( $mapping['source_id'] ?? '' );
			$target = (string) ( $mapping['target_field'] ?? '' );
			if ( false !== strpos( $target, '[' ) ) {
				$target = do_shortcode( $target );
			}
			$target = sanitize_text_field( $target );
			$value  = $values[ $source ] ?? '';
			if ( '' === $source || '' === $value ) {
				continue;
			}
			if ( 'tag' === ( $mapping['target_type'] ?? '' ) ) {
				foreach ( preg_split( '/[,;\r\n]+/', $value ) as $tag ) {
					$tag = sanitize_text_field( trim( $tag ) );
					if ( '' !== $tag ) {
						$tags[] = $tag;
					}
				}
			} elseif ( '' === $target ) {
				continue;
			} elseif ( 'custom' === ( $mapping['target_type'] ?? '' ) ) {
				if ( preg_match( '/^[A-Za-z0-9]{20,}$/', $target ) ) {
					$custom_fields[] = array( 'id' => $target, 'fieldValue' => $value );
				} else {
					$field_cache_key = 'mc_ghl_field_' . md5( $location . '|' . $target );
					$field_details = get_transient( $field_cache_key );
					if ( false === $field_details ) {
						$field_details = mc_ghl_booking_api_request( 'GET', '/locations/' . rawurlencode( $location ) . '/customFields/' . rawurlencode( $target ) );
						if ( ! is_wp_error( $field_details ) ) {
							set_transient( $field_cache_key, $field_details, DAY_IN_SECONDS );
						}
					}
					$field = is_array( $field_details ) ? ( $field_details['customField'] ?? array() ) : array();
					if ( empty( $field['id'] ) ) {
						$ajax_handler->add_error_message( esc_html__( 'A mapped HighLevel custom field key could not be found. Check the key and ensure the token has locations/customFields.readonly access.', 'mc-ghl-booking' ) );
						return;
					}
					$custom_fields[] = array( 'id' => $field['id'], 'key' => $field['fieldKey'] ?? $target, 'fieldValue' => $value );
				}
			} elseif ( in_array( $target, $standard_allowed, true ) ) {
				$contact[ $target ] = $value;
			}
		}
		if ( null === $previous_submission_fields ) {
			unset( $GLOBALS['mc_ghl_booking_submission_fields'] );
		} else {
			$GLOBALS['mc_ghl_booking_submission_fields'] = $previous_submission_fields;
		}
		if ( empty( $contact['email'] ) && empty( $contact['phone'] ) ) {
			$ajax_handler->add_error_message( esc_html__( 'Map at least an email or phone field to HighLevel before booking.', 'mc-ghl-booking' ) );
			return;
		}
		if ( $custom_fields ) {
			$contact['customFields'] = $custom_fields;
		}
		$upsert = mc_ghl_booking_api_request( 'POST', '/contacts/upsert', $contact );
		$contact_id = is_wp_error( $upsert ) ? '' : ( $upsert['contact']['id'] ?? '' );
		if ( ! $contact_id ) {
			$ajax_handler->add_error_message( esc_html__( 'We could not save your contact details. Please try again.', 'mc-ghl-booking' ) );
			return;
		}
		if ( $tags ) {
			$tags      = array_values( array_unique( $tags ) );
			$tag_result = mc_ghl_booking_api_request( 'POST', '/contacts/' . rawurlencode( $contact_id ) . '/tags', array( 'tags' => $tags ) );
			if ( is_wp_error( $tag_result ) ) {
				$ajax_handler->add_error_message( esc_html__( 'Your contact was saved, but its HighLevel tag could not be added. Please try again or contact us.', 'mc-ghl-booking' ) );
				return;
			}
		}
		$title = sanitize_text_field( $settings['mc_ghl_appointment_title'] ?? 'Free Consultation' );
		$booking = mc_ghl_booking_api_request( 'POST', '/calendars/events/appointments', array( 'calendarId' => $calendar, 'locationId' => $location, 'contactId' => $contact_id, 'startTime' => gmdate( 'Y-m-d\TH:i:s\Z', $start ), 'title' => $title, 'appointmentStatus' => 'confirmed', 'toNotify' => true ) );
		if ( is_wp_error( $booking ) ) {
			$ajax_handler->add_error_message( esc_html__( 'Your details were saved, but that time could not be booked. Please select another time and submit again.', 'mc-ghl-booking' ) );
		}
	}

	public function on_export( $element ): array { return $element; }
}
