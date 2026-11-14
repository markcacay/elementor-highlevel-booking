# Elementor HighLevel Booking

A WordPress plugin that adds an in-page appointment booking step to Elementor Pro Forms and connects the form to a GoHighLevel (HighLevel) calendar.

Created by [Mark Cacay](https://www.linkedin.com/in/mark-cacay-858a1896/).

## What it does

- Keeps the visitor on the current page while they submit their details, choose a date, and select an available time.
- Retrieves availability from the configured HighLevel calendar and defaults to the visitor's local timezone, with a timezone selector.
- Creates or updates the HighLevel contact, applies configured contact tags and custom fields, and books the appointment.
- Displays the appointment duration, schedule, meeting location, and timezone after a successful booking.
- Supports reusable Elementor form templates and shortcode-driven mapping targets.

HighLevel credentials are stored in WordPress settings and used by the server. The availability request is limited to the configured calendar using a server-generated HMAC signature.

## Requirements

- WordPress
- Elementor and Elementor Pro
- A HighLevel Private Integration token with the required calendar, contact, location, and custom-field scopes

## Install

1. Open the [commit history](https://github.com/markcacay/elementor-highlevel-booking/commits/main) and select the `release: vX.Y.Z` commit for the version you want.
2. Upload the plugin folder under **Plugins → Add Plugin → Upload Plugin** and activate it.
3. Save the HighLevel Private Integration token under **Settings → HighLevel Booking**. Keep the token out of page content and browser code.
4. Add **Book with HighLevel** to the Elementor form's **Actions After Submit** and configure the location, calendar, and field mappings.

See the plugin's **Settings → HighLevel Booking** help text and the release notes for mapping details.

## Source layout

- `mark-cacay-ghl-booking.php` — plugin bootstrap, settings, availability endpoint, and Elementor configuration.
- `includes/class-mc-ghl-booking-action.php` — Elementor Pro Form action, contact mapping, and appointment creation.
- `assets/booking.js` — date/time selection flow and post-booking summary.
- `assets/booking.css` — scoped booking calendar and slot styles.

## Version history

Each archived release is represented by a version-specific commit in Git history. The detailed change notes are in [CHANGELOG.md](CHANGELOG.md). Historical snapshots are taken from the versioned plugin ZIP files; the 0.1.1 archive's plugin header still reports 0.1.0, which is called out in the changelog.

