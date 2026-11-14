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
		$widget->add_control( 'mc_ghl_mapping_help', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => esc_html__( 'Add one row for every form field to send. Use the Elementor Field ID as the source. For custom fields, enter the HighLevel custom field ID.', 'mc-ghl-booking' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'source_id', array( 'label' => esc_html__( 'Elementor Field ID', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true ) );
		$repeater->add_control( 'target_type', array( 'label' => esc_html__( 'HighLevel field type', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'standard', 'options' => array( 'standard' => esc_html__( 'Standard contact field', 'mc-ghl-booking' ), 'custom' => esc_html__( 'Custom field', 'mc-ghl-booking' ) ) ) );
		$repeater->add_control( 'target_field', array( 'label' => esc_html__( 'HighLevel field name or custom field ID', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true, 'description' => 'Examples: email, phone, firstName, or a custom field ID.' ) );
		$widget->add_control( 'mc_ghl_field_mappings', array( 'label' => esc_html__( 'Field mappings', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ source_id }}} → {{{ target_field }}}', 'default' => array() ) );
		$widget->add_control( 'mc_ghl_slot_field_id', array( 'label' => esc_html__( 'Appointment slot field ID', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'ghl_slot', 'description' => esc_html__( 'The plugin supplies this hidden value automatically. Add an Elementor Hidden field with this ID only if you also want it in saved form submissions.', 'mc-ghl-booking' ) ) );
		$widget->add_control( 'mc_ghl_appointment_title', array( 'label' => esc_html__( 'Appointment title', 'mc-ghl-booking' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Free Consultation' ) );
		$widget->end_controls_section();
	}

	public function run( $record, $ajax_handler ): void {
		$settings = $record->get( 'form_settings' );
		$location = sanitize_text_field( $settings['mc_ghl_location_id'] ?? '' );
		$calendar = sanitize_text_field( $settings['mc_ghl_calendar_id'] ?? '' );
		$slot_id  = sanitize_key( $settings['mc_ghl_slot_field_id'] ?? 'ghl_slot' );
		$slot     = isset( $_POST['form_fields'][ $slot_id ] ) ? sanitize_text_field( wp_unslash( $_POST['form_fields'][ $slot_id ] ) ) : '';
		if ( ! $location || ! $calendar || ! $slot ) {
			$ajax_handler->add_error_message( esc_html__( 'Please select an available appointment time.', 'mc-ghl-booking' ) );
			return;
		}
		$start = strtotime( $slot );
		if ( ! $start || $start < time() ) {
			$ajax_handler->add_error_message( esc_html__( 'That appointment time is no longer available. Please select another time.', 'mc-ghl-booking' ) );
			return;
		}
		$raw_fields = $record->get( 'fields' );
		$values = array();
		foreach ( (array) $raw_fields as $id => $field ) {
			$values[ sanitize_key( $id ) ] = is_array( $field['value'] ?? null ) ? implode( ', ', $field['value'] ) : sanitize_text_field( (string) ( $field['value'] ?? '' ) );
		}
		$custom_fields = array();
		$contact = array( 'locationId' => $location, 'source' => 'Elementor HighLevel Booking' );
		$standard_allowed = array( 'firstName', 'lastName', 'name', 'email', 'phone', 'address1', 'city', 'state', 'postalCode', 'website', 'timezone', 'country', 'companyName' );
		foreach ( (array) ( $settings['mc_ghl_field_mappings'] ?? array() ) as $mapping ) {
			$source = sanitize_key( $mapping['source_id'] ?? '' );
			$target = sanitize_text_field( $mapping['target_field'] ?? '' );
			$value  = $values[ $source ] ?? '';
			if ( '' === $source || '' === $target || '' === $value ) {
				continue;
			}
			if ( 'custom' === ( $mapping['target_type'] ?? '' ) ) {
				$custom_fields[] = array( 'id' => $target, 'fieldValue' => $value );
			} elseif ( in_array( $target, $standard_allowed, true ) ) {
				$contact[ $target ] = $value;
			}
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
		$title = sanitize_text_field( $settings['mc_ghl_appointment_title'] ?? 'Free Consultation' );
		$booking = mc_ghl_booking_api_request( 'POST', '/calendars/events/appointments', array( 'calendarId' => $calendar, 'locationId' => $location, 'contactId' => $contact_id, 'startTime' => gmdate( 'Y-m-d\TH:i:s\Z', $start ), 'title' => $title, 'appointmentStatus' => 'confirmed', 'toNotify' => true ) );
		if ( is_wp_error( $booking ) ) {
			$ajax_handler->add_error_message( esc_html__( 'Your details were saved, but that time could not be booked. Please select another time and submit again.', 'mc-ghl-booking' ) );
		}
	}

	public function on_export( $element ): array { return $element; }
}
