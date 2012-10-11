=== Quickpay Payment Gateway for WP e-Commerce ===
Contributors: wpkonsulent
Tags: quickpay, merchant, payment gateway, wpec, wp e-commerce, e-commerce
Requires at least: 3.0.1
Tested up to: 3.4.2
Stable tag: 1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds Quickpay.net payment gateway to WP e-Commerce

== Description ==

This is the official Quickpay.net payment gateway plugin for WP e-Commerce.

= Features: =
* Automatic capture on/off.
* Test mode on/off.
* Specify language of payment window.
* Specify currency of payment window.
* Lock payment window to certain options (card types and/or other payment methods).

= How to use it: =
Having installed and activated the plugin, go to the WP e-Commerce settings page and select the Payment tab. Here you'll be able to select and configure the payment gateway.

Once that's done, it will tie in with WP e-Commerce automatically.

= Compatability =
The plugin has been tested with WP e-Commerece 3.8.8.5. I can't guarantee that it will work with older versions of WP e-Commerce.

= Upgrading from the old non-plugin version of the payment gateway? =
Then do this:

1. Navigate to `wp-content/plugins/wp-e-commerce/wpsc-merchants` folder
1. Delete the `quickpay.php` file

== Installation ==

1. Upload the `quickpay-payment-gateway-for-wp-e-commerce` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

If you need help, please [contact Quickpay.net directly](http://quickpay.net/contact/). They will provide the support you need.

== Changelog ==

= Upgrading from the old non-plugin version of the payment gateway? =
Then do this:

1. Navigate to `wp-content/plugins/wp-e-commerce/wpsc-merchants` folder
1. Delete the `quickpay.php` file

= 1.3 =
* Changed to a 'real' plugin.
* Added trailing slash for callback url if WordPress site address is trailing slash-less.
* Added new config field for specifying protocol version.
* Updated to protocol version 6 (new fields in response MD5).

= 1.2 =
* Added rounding for odd ørebeløb, since WPEC doesn't do it.

= 1.1 =
* Added cardtypelock = 'creditcard' which supposedly should have a positive effect on conversions.
* Clean-up of code for better readability and more correct HTML.
* Minor optimization of control panel.
* Fixed bug with accepted payments showing up as "Order received".
* Added test mode.
* Changed the entire flow regarding cancel/continue/callback URLs.
* Cancelled payments no longer triggers a purchase report to be sent by email.

= 1.0 =
* Initial release, originally by Lars Koudal.