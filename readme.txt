=== Paypal Target Meter ===
Contributors: lokkju
Donate link: http://lokkju.com/blog/
Tags: donations,paypal,widget,donation,goals,goal,meter
Requires at least: 3.0.0
Tested up to: 3.0.4
Stable tag: 1.2.4

display a progress meter of donations towards a monthly or yearly goal

== Description ==

Paypal target meter uses the Paypal NVP API (which means you must have a premium or better account) to display a progress meter of donations towards a monthly or yearly goal. Setup is a breeze, requiring only your Paypal API credentials, and it supports filtering received payments by the email address they were sent to.

You can see a demo of the plugin on the BrainSilo web page at http://brainsilo.org/

== Installation ==

1. Upload the `paypal-target-meter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the Widget to your sidebar (or where ever else you want) through the 'Widgets' menu in Wordpress
4. Configure your instance of the Widget through the 'Widgets' menu in Wordpress

== Frequently Asked Questions ==

= Where do I get my Paypal API Credentials? =
To use the Paypal API, you must have a business or premier level Paypal account.

1. Log into PayPal, then click Profile under My Account.
2. Click API Access.
3. Click Request API Credentials.
4. Check Request API signature and click Agree and Submit.
5. Click Done to complete the process.

== Screenshots ==

1. Admin Configuration
2. On site, with post-test filled with custom text (the paypal button and disclaimer)

== Changelog ==

= 1.2.4 =
fixed a limit of 10 transactions - now it is 100 (per the paypal api spec)
fixed a bug with the display bar and more then 100% funding.
improved the API transaction search to return less (uneccissary) transactions

= 1.2.3 =
added admin debug page under the Tools menu.

= 1.2 =
trying to fix versions

= 1.1 =
it doesn't seem to like versions less then 1

= 0.9 =
* Initial creation and tag
