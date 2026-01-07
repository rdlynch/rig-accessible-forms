=== RIG Accessible Forms ===
Contributors: ruralimpactgroup
Tags: forms, accessibility, wcag, contact form, accessible
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 0.5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fully accessible contact forms plugin focused on both public and admin experiences. WCAG 2.2 AA compliant. Minimal, no-frills, keyboard-first.

== Description ==

RIG Accessible Forms is a WordPress plugin designed specifically for accessibility from the ground up. Unlike other form plugins that add accessibility as an afterthought, every feature in this plugin prioritizes keyboard navigation, screen reader support, and WCAG 2.2 Level AA compliance.

**Key Features:**

* **Gutenberg Block** - Modern block editor integration with live preview
* **Conditional Logic** - Show/hide fields based on user selections
* **Advanced Validation** - Custom regex patterns, min/max length, min/max value validation
* **Calculation Fields** - Auto-calculate totals based on other fields with mathematical formulas
* **Accessible Form Builder** - Keyboard-accessible admin interface
* **Multiple Field Types** - Text, email, tel, textarea, select, radio, checkbox groups, date, file upload, address, calculated
* **Client-Side Validation** - Progressive enhancement with accessible error handling
* **File Upload Security** - MIME type validation, size limits, extension checking
* **Anti-Spam Protection** - Honeypot, rate limiting, timing validation
* **Email Notifications** - Flexible routing with conditional rules and field tokens
* **CSV Export** - Download form submissions
* **Screen Reader Tested** - Verified with NVDA, JAWS, and VoiceOver

**Accessibility Highlights:**

* ARIA live regions for dynamic announcements
* Auto-focus error summary on validation failure
* Visible focus indicators throughout
* Proper label associations and fieldsets
* Keyboard-navigable error links
* File upload feedback for screen readers
* Progressive enhancement (works without JavaScript)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/rig-accessible-forms/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Forms > Add New to create your first form
4. Add the Accessible Form block to any page or post

== Usage ==

**Creating a Form:**

1. Navigate to Forms > Add New
2. Enter a form title
3. Click "Open Accessible Builder" to add fields using the keyboard-accessible interface
4. Configure email notifications in the sidebar
5. Publish the form

**Adding to a Page:**

1. Edit any page or post
2. Add a new block and search for "Accessible Form"
3. Select your form from the dropdown
4. Preview appears automatically in the editor
5. Publish your page

**Using Conditional Logic:**

1. In the Form Builder, find the "Conditional (JSON)" column
2. Enter a JSON object like: `{"field":"contact_method","operator":"==","value":"email"}`
3. The field will only show when the specified condition is met
4. Supports operators: ==, !=, >, >=, <, <=, contains, not_contains, empty, not_empty

**Using Advanced Validation:**

1. In the Form Builder, find the "Validation (JSON)" column
2. Enter a JSON object with validation rules:
   - `{"min_length":5,"max_length":100}` - Character length limits
   - `{"min_value":0,"max_value":999}` - Numeric value limits
   - `{"custom_pattern":"/^[A-Z]{2,5}$/","custom_pattern_message":"Must be 2-5 uppercase letters"}` - Custom regex validation

**Using Calculation Fields:**

1. In the Form Builder, set field type to "Calculated"
2. In the "Calculation" column, enter a formula using field names: `quantity * price`
3. Supports operators: +, -, *, /, ()
4. Example: `(subtotal + shipping) * 1.15` (adds 15% tax)
5. Field updates automatically as user enters values
6. Results are announced to screen readers

== Changelog ==

= 0.5.0 =
* **Added advanced validation rules** - Custom regex patterns, min/max length, min/max value validation
* **Added calculation fields** - Auto-calculate totals based on other field values
* New "calculated" field type with real-time updates
* Support for mathematical formulas (+, -, *, /, parentheses)
* Client-side and server-side validation for custom patterns
* HTML5 validation attributes (minlength, maxlength, min, max, pattern)
* Accessible announcements for calculated results
* Added Validation (JSON) column to Form Builder
* Added Calculation column to Form Builder

= 0.4.0 =
* **Added conditional logic** - Show/hide fields based on user selections
* Removed deprecated shortcode functionality (block editor only)
* Added 10+ operators for conditional rules (==, !=, >, <, contains, empty, etc.)
* Conditional fields automatically disabled and cleared when hidden
* Added data-conditional attribute support in form rendering
* Enhanced builder UI with Conditional (JSON) column
* Improved accessibility with aria-hidden on conditional fields
* Full screen reader support for dynamic field visibility

= 0.3.0 =
* Added Gutenberg block with live preview
* Enabled REST API for form post type
* Improved admin labels and UI
* Server-side block rendering
* Block editor integration with ServerSideRender
* Enhanced block placeholder experience
* Note: Shortcode still supported but deprecated

= 0.2.0 =
* Added comprehensive client-side validation
* Implemented auto-focus error management
* Added file upload accessibility feedback
* Duplicate submission prevention
* Enhanced CSS for error states
* ARIA live region announcements
* Added file upload security (MIME, size, extension validation)
* Implemented IP-based rate limiting
* Added submission timing validation
* Fixed address field concatenation bug
* Added referer validation for CSRF protection

= 0.1.0 =
* Initial release
* Field types: radios, selects, grouped checkboxes, file upload, date, tel, address
* CSV export
* Flexible notifications with tokens and conditional routing
* Accessible Builder screen (keyboard reordering)

== Frequently Asked Questions ==

= Is this plugin WCAG compliant? =

Yes, RIG Accessible Forms is designed to meet WCAG 2.2 Level AA standards. It has been tested with screen readers including NVDA, JAWS, and VoiceOver.

= Does it work without JavaScript? =

Yes! The plugin uses progressive enhancement. Forms work perfectly fine without JavaScript, and JavaScript enhances the experience with client-side validation and better error handling.

= Can I customize the form styling? =

Yes, you can override the default styles by adding CSS to your theme. All form elements use the `rigaf-` prefix for easy targeting.

= Does it support file uploads? =

Yes, with comprehensive security validation including MIME type checking, file size limits, and extension validation.

= How do I prevent spam? =

The plugin includes multiple anti-spam measures: honeypot fields, rate limiting, submission timing validation, and optional CAPTCHA support (coming soon).

= Can I export form submissions? =

Yes, go to Forms > Export CSV to download all submissions for a specific form.

== Upgrade Notice ==

= 0.3.0 =
This version adds Gutenberg block support. Shortcodes are still supported but deprecated. Please migrate to blocks when possible.
