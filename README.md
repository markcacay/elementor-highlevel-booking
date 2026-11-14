# Mark Cacay — Elementor HighLevel Booking

Version 0.1.3. Adds the **Book with HighLevel** action to Elementor Pro Forms. It presents available times in an in-page second step, upserts a HighLevel contact, and creates a calendar appointment after final submit. When a WordPress administrator is logged in, slot-loading errors include a diagnostic detail; public visitors continue to see a generic message.

## Install and configure

1. Zip the `mark-cacay-ghl-booking` folder and upload it under **Plugins → Add Plugin → Upload Plugin**. Activate Elementor and Elementor Pro first.
2. Go to **Settings → HighLevel Booking** and save a sub-account Private Integration token. The token must have `calendars.readonly`, `calendars/events.write`, and `contacts.write` scopes. Never put the token in Elementor or browser code.
3. In the Elementor Form widget, remove the old **Webhook** action and add **Book with HighLevel**.
4. Enter the Location ID and Calendar ID. Select the slot timezone.
5. Add one mapping row per submitted field. Use the Elementor field ID as the source. For a HighLevel custom field, select **Custom field** and enter its custom field ID.
6. Set `email` or `phone` as a standard-field mapping so HighLevel can identify the contact.

The plugin adds an appointment slot value under the configured slot field key during submission. You may add an Elementor Hidden field with that ID if you want the selected slot stored in Elementor submissions; the plugin can also submit its own hidden value.

## Notes

- No API token, OAuth credential, or secret is placed in front-end JavaScript.
- The slot endpoint returns up to 30 days of availability in the chosen timezone.
- Appointment creation uses the slot's explicit UTC timestamp and lets HighLevel apply its calendar duration.
- If contact upsert succeeds but booking fails, the contact remains saved and the form reports that the appointment could not be booked.
- Verify current scopes and API behavior in the HighLevel documentation for your account before production use.
