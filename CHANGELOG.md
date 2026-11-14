# Changelog

Release history reconstructed from the archived plugin packages. Each version is a separate commit in Git history, with its changes described in that commit message. Notes below summarize the differences visible in each package compared with the previous archived package.

## 0.4.7

- Removed the page-generated WordPress nonce from the public, read-only availability request. Cached pages could otherwise serve an expired nonce and receive WordPress's `-1` / 403 response.
- Kept server-side HMAC verification of the configured location and calendar before looking up slots.

## 0.4.6

- Allowed a shortcode in a field mapping target and evaluated it during form submission.
- Made the submitted Elementor field values available to the shortcode while resolving the target. This supports selecting a HighLevel custom field key from a submitted value.

## 0.4.5

- Strengthened calendar and slot CSS against theme and global button/grid rules that distorted the booking interface.

## 0.4.4

- Added configuration attributes directly to rendered Elementor form markup when widget-level configuration was missing. This improved configuration discovery for forms rendered through reusable Elementor content.

## 0.4.3

- Added contact-tag mappings, including multiple comma-, semicolon-, or line-separated tags, while preserving existing contact tags.
- Added support for reading booking configuration from Elementor Global widgets.

## 0.4.2

- Made the Continue button label resilient to repeated front-end initialization so it stays correct after Elementor updates the form markup.

## 0.4.1

- Applied the next-day minimum scheduling notice using the selected timezone.
- Submitted the selected timezone with the final form so the server validates the appointment against the same local-day boundary.
- Moved duplicate-initialization tracking to the form element to avoid wrapper re-render issues.

## 0.4.0

- Added custom-field key support in addition to HighLevel custom-field IDs, with server-side lookup and caching.
- Added configurable meeting duration and meeting location, plus an appointment summary after booking.
- Added location and custom-field read scopes to the token setup guidance.

## 0.3.1

- Prevented same-day booking by using the next day in the visitor's selected timezone as the earliest allowed appointment time.
- Validated and carried the timezone through the booking submission.

## 0.3.0

- Made the visitor's local timezone the default and added a timezone selector.
- Standardized the availability request around IANA timezone names and signed calendar configuration.
- Added date-first browsing and inline confirmation controls to the booking flow.

## 0.2.0

- Replaced the single list of times with a calendar date-selection step followed by available time slots.
- Added previous/next month navigation, back buttons, a Continue form step, and an automatically supplied hidden appointment value.
- Added timezone selection and IANA timezone validation.

## 0.1.3

- Separated the calendar timezone setting from the visitor's displayed timezone in the availability request.
- Added server-side timezone validation for the calendar lookup.

## 0.1.2

- Added detailed availability request diagnostics for logged-in administrators while keeping generic error messages for public visitors.

## 0.1.1

- Refined setup documentation for the plugin package.
- The archived 0.1.1 ZIP contains the 0.1.1 package name but retains `0.1.0` in the plugin header and version constant; the historical snapshot is preserved as packaged.

## 0.1.0

- Initial release: added the Elementor Pro form action, server-side HighLevel contact upsert and appointment booking, available-time lookup, and in-page form-to-slot flow.
- Kept the Private Integration token in WordPress settings rather than exposing it to browser JavaScript.
