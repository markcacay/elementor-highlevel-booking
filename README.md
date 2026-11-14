# Elementor HighLevel Booking

Version 0.4.0. Adds the **Book with HighLevel** action to Elementor Pro Forms. It presents a separate calendar date view, time selection, and confirmation action without reloading the page. After a successful booking, Elementor displays its configured success message followed by an appointment summary with duration, schedule, meeting location, and timezone. When a WordPress administrator is logged in, slot-loading errors include a diagnostic detail; public visitors continue to see a generic message.

## Install and configure

1. Zip the `mark-cacay-ghl-booking` folder and upload it under **Plugins → Add Plugin → Upload Plugin**. Activate Elementor and Elementor Pro first.
2. Go to **Settings → HighLevel Booking** and save a sub-account Private Integration token. The token should have `calendars.readonly`, `calendars/events.write`, `contacts.write`, `locations.readonly`, and `locations/customFields.readonly` scopes. `locations.readonly` lets the plugin display the HighLevel location phone for `{{location.phone}}`; `locations/customFields.readonly` lets it resolve custom field keys to IDs. Never put the token in Elementor or browser code.
3. In the Elementor Form widget, remove the old **Webhook** action and add **Book with HighLevel**.
4. Enter the Location ID and Calendar ID. Visitors see availability in their detected local timezone by default and can switch timezone in the booking calendar.
5. Add one mapping row per submitted field. Use the Elementor field ID as the source. For a HighLevel custom field, select **Custom field** and enter either its custom field ID or key (for example `contact.type_of_virtual_assistant`).
6. Set `email` or `phone` as a standard-field mapping so HighLevel can identify the contact.
7. Set the meeting duration in minutes and the meeting location shown on the confirmation. Enter `{{location.phone}}` to fetch the location's phone from HighLevel, or enter a fixed label/number such as `Phone call` or `(866) 596-9041`.

The plugin adds an appointment slot value under the configured slot field key during submission. You may add an Elementor Hidden field with that ID if you want the selected slot stored in Elementor submissions; the plugin supplies its own hidden value automatically.

## Notes

- No API token, OAuth credential, or secret is placed in front-end JavaScript.
- The slot endpoint returns availability starting at midnight tomorrow in the visitor-selected timezone, through the next 30 days. Same-day appointments are excluded in the interface and rejected by the server. Set the HighLevel calendar minimum scheduling notice to 0 if you want tomorrow’s slots to appear; its normal working hours, booked slots, and calendar rules still apply.
- Appointment creation uses the slot's explicit UTC timestamp and lets HighLevel apply its calendar duration.
- The confirmation duration uses the HighLevel calendar duration when returned by its API; otherwise, it uses the Elementor action's **Meeting duration (minutes)** value. Keep that value aligned with the actual calendar duration.
- If contact upsert succeeds but booking fails, the contact remains saved and the form reports that the appointment could not be booked.
- Verify current scopes and API behavior in the HighLevel documentation for your account before production use.
