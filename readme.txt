=== Simple Login for DocCheck ===
Contributors: dotsunited
Tags: doccheck, access control, authentication, healthcare, oauth
Requires at least: 6.3
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect selected WordPress pages with a DocCheck OAuth 2.0 login for verified medical professionals.

== Description ==

Simple Login for DocCheck protects selected pages behind a DocCheck login without creating WordPress user accounts or storing DocCheck profile data.

The plugin provides:

* A DocCheck Login block for the WordPress block editor.
* A `[simple_login_for_doccheck]` shortcode alternative.
* Protection for selected WordPress pages.
* A configurable local login lifetime.
* Support for DocCheck Basic, Economy, and Business Login Clients.
* German and German (formal) translations.

WordPress administrators and editors retain access to protected pages. Other visitors are redirected to the configured login page and can continue to their original destination after successful authentication.

= External services =

This plugin relies on DocCheck as an external authentication service.

* **DocCheck CDN (`https://dccdn.de`)** — When a page containing the DocCheck Login block or `[simple_login_for_doccheck]` shortcode is rendered, the visitor's browser loads the DocCheck login-button web component from the CDN. This request may transmit technical data such as the visitor's IP address, user agent, and referrer to DocCheck before the button is clicked.
* **DocCheck OAuth service (`https://auth.doccheck.com`)** — When a visitor starts the login flow, the visitor's browser connects to DocCheck for authentication. On the callback, the WordPress server sends the authorization code, client ID, client secret, and configured redirect URL to DocCheck's token endpoint to verify the login.

The plugin discards the returned access token and does not request or store DocCheck profile data. It stores only the local login expiry and return URL in the visitor's PHP session.

Before enabling the integration, review:

* [DocCheck Login documentation](https://docs.doccheck.com/login-access/)
* [DocCheck Privacy Policy](https://more.doccheck.com/en/privacy)
* [DocCheck Login Usage and Licence Agreement](https://more.doccheck.com/en/terms-of-use-license/)

= Plan support and security =

DocCheck Basic, Economy, and Business Login Clients are supported. DocCheck Basic does not return OAuth `state`, so the plugin neither sends nor verifies it. The server-to-server authorization-code exchange verifies the login.

Without `state`, another site can cause a visitor's browser to complete a login it did not initiate. This only gives that visitor access to protected content. Use a different integration if your threat model requires a state-bound flow.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/simple-login-for-doccheck`, or install its release archive through the WordPress plugin screen.
2. Activate **Simple Login for DocCheck**.
3. Create a WordPress page, add the **DocCheck Login** block, and publish it. The `[simple_login_for_doccheck]` shortcode remains available as an alternative.
4. Open **Settings > Simple Login for DocCheck**.
5. Enter the DocCheck Login-Client ID, client secret, and same-origin redirect URL.
6. Select the login page and the pages to protect.
7. Exclude the protected pages and login page from full-page caching.

== Frequently Asked Questions ==

= Does the plugin create WordPress users? =

No. A successful DocCheck login creates only a temporary PHP session for access to protected content.

= Does the plugin store DocCheck access tokens or profile data? =

No. The access token is discarded immediately after the authorization-code exchange, and no DocCheck profile endpoint is called.

= Why must the redirect URL use the WordPress site's origin? =

The callback is handled by this WordPress installation. Requiring the same scheme, hostname, and port prevents a configuration that sends the authorization response somewhere the plugin cannot process it. Production redirect URLs must use HTTPS.

= Can WordPress administrators and editors access protected pages? =

Yes. Users with the `edit_pages` capability retain access without a DocCheck session.

= What should be excluded from caching? =

Exclude protected pages and the login page from full-page caches. Do not cache responses that carry a PHP session cookie.

== Changelog ==

= 1.0.0 =

* Initial release.

== Trademark ==

DocCheck is a trademark of DocCheck Community GmbH. This plugin is not affiliated with or endorsed by DocCheck.
