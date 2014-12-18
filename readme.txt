=== WooCommerce EU VAT Compliance ===
Contributors: DavidAnderson
Requires at least: 3.1
Tested up to: 4.1
Stable tag: 1.3.0
Tags: woocommerce, eu vat, vat compliance, iva, moss, vat rates, eu tax, hmrc, digital vat, tax, woocommerce taxes
License: GPLv3
Donate link: http://david.dw-perspective.org.uk/donate

Assists with EU VAT compliance for WooCommerce, for the new VAT regime beginning 1st January 2015, including for with the MOSS system.

== Description ==

= The New EU VAT (IVA) law =

From January 1st 2015, all digital goods (including electronic, telecommunications, software, ebook and broadcast services) sold across EU borders are liable under EU law to EU VAT (a.k.a. IVA) charged in the country of *purchase*, at the VAT rate in that country (background information: http://www2.deloitte.com/global/en/pages/tax/articles/eu-2015-place-of-supply-changes-mini-one-stop-shop.html). This applies even if the seller is not based in the EU, and there is no minimum threshold.

= How this plugin can take away the pain =

This WooCommerce plugin provides features to assist with EU VAT law compliance from January 1st 2015. Currently, those features include:

- <strong>Identify your customers' location:</strong> this plugin will record evidence of your customer's location, using their billing or shipping address, and their IP address (via a GeoIP lookup).

- <strong>Forbid EU sales (feature not yet released)</strong> - for shop owners for whom EU VAT compliance is too burdensome, this feature will allow you to forbid EU customers to check-out.

- <strong>Evidence is recorded, ready for audit:</strong> full information that was used to calculate VAT is displayed in the WooCommerce order screen in the back-end.

- <strong>Entering and maintaining each country's VAT rates:</strong> this plugin assists with entering EU VAT rates accurately by supplying a single button to press in your WooCommerce tax rates settings, to add or update rates for all countries (standard or reduced) with one click.

- <strong>Distinguish VAT from other taxes:</strong> if you are in a jurisdiction where you have to apply other taxes also, then this plugin can handle that: it knows which taxes are VAT, and which are not.

<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">A Premium version is on sale at this link</a>, and currently has these *additional* features ready:

- <strong>VAT-registered buyers can be exempted, and their numbers validated:</strong> a VAT number can be entered at the check-out, and it will be validated (via VIES). Qualifying customers can then be exempted from VAT on their purchase, and their information recorded. This feature is backwards-compatible with the old official WooCommerce "EU VAT Number" extension, so you will no longer need that plugin, and its data will be maintained.

- <strong>Forbid EU sales (feature not yet released)</strong> - for shop owners for whom EU VAT compliance is too burdensome, this feature will allow you to forbid EU customers who would be liable to VAT (i.e. those without a VAT number) to purchase.

- <strong>Non-contradictory evidences:</strong> require two non-contradictory evidences of location (if the customer address and GeoIP lookup contradict, then the customer will be asked to self-certify his location, by choosing between them).

- <strong>Multi-currency compatible:</strong> if you are using the <a href="http://aelia.co/shop/currency-switcher-woocommerce/">"WooCommerce currency switcher"</a> plugin to sell in multiple currencies, then this plugin will maintain and provide its data for each order in both your shop's base currency and the order currency (if it differs).

- <strong>Reporting:</strong> Advanced reporting capabilities, allowing you to see all the information needed to make a MOSS (mini one-stop shop) VAT report. Currently WooCommerce 2.0 and 2.1 are supported and a report broken down by country and currency can be generated, with 2.2 support and support for downloading a full spreadsheet on every transaction to be added very soon. (Some reporting features will possibly/probably be added to the free version too.)

<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">Read more about the Premium version of this plugin at this link.</a>

Other features are still being weighed up and considered. It is believed (but not legally guaranteed), that armed with the above capabilities, a WooCommerce shop owner will be in a position to fulfil all the requirements of the EU VAT law: identifying the customer's location and collecting multiple pieces of evidence, applying the correct VAT rate, validating VAT numbers for B2B transactions, and having the data needed to create returns. (If in the EU, then you will also need to make sure that you are issuing your customers with VAT invoices containing the information required in your jurisdiction, via <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">a suitable WooCommerce invoice plugin</a>).

Before January 1st 2015, of course, you will want to be careful about which features you enable. Before that date, the previous VAT / IVA regime will continue to operate.

= Footnotes and legalese =

This plugin requires WooCommerce 2.0 or later (tested up to 2.2). It fetches data on current VAT rales from Amazon S3 (using SSL if possible); or, upon failure to connect to Amazon S3, from https://euvatrates.com. If your server's firewall does not permit this, then it will use static data contained in the plugin.

Geographical IP lookups are performed via the MaxMind GeoIP database, via the GeoIP-plugin, which you will be prompted to install; or, alternatively, if you use CloudFlare, then you can <a href="https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do-">activate the CloudFlare feature for sending geographical information</a>.

Please make sure that you review this plugin's installation instructions and have not missed any important information there.

Whether you think the EU's treaties with other jurisdictions will lead to success in enforcing the collection of taxes in other jurisdictions is a question for lawyers and potential tax-payers, not for software developers!

= Other information =

- Some other WooCommerce plugins you may be interested in: https://www.simbahosting.co.uk/s3/shop/

- This plugin is ready for translations, and we would welcome new translations (please post them in the support forum)

== Installation ==

Standard WordPress installation; either:

- Go to the Plugins -> Add New screen in your dashboard and search for this plugin; then install and activate it.

Or

- Upload this plugin's zip file into Plugins -> Add New -> Upload in your dashboard; then activate it.

After installation, you will want to configure this plugin, as follows:

1) Go to WooCommerce -> Settings -> Tax -> Standard Rates, and press the "Add / Update EU VAT Rates (Standard)"

2) If you have products that are liable for VAT at a reduced rate, then also go to WooCommerce -> Settings -> Tax -> Reduced Rate Rates, and press the "Add / Update EU VAT Rates (Reduced)"

You must remember, of course, to make sure that a) your WooCommerce installation is set up to apply taxes to your sales (WooCommerce -> Settings -> Tax) and b) that your products are placed in the correct tax class (choose "Products" from the WordPress dashboard menu).

== Frequently Asked Questions ==

(None yet)

== Changelog ==

= 1.3.0 - 2014-12-18 =

* FEATURE: Premium version now shows per-country VAT reports on WooCommerce 2.0 and 2.1 (2.2 to follow). Which reporting features will or won't go into the free version is still to-be decided.
* FIX: The value of the "Phrase matches used to identify VAT taxes" setting was reverting to default - please update it again if you had attempted to change it (after updating to this version)
* IMPORTANT TWEAK: Order VAT is now computed and stored at order time, to spare the computational expense of calculating it, order-by-order, when reporting. You should apply this update asap: orders made before you upgrade to it will not be tracked in your report. (Note also that reporting features are still under development, in case you're wondering where they are - they're not technically needed until the 1st quarter of 2015 ends, and only need to cover from 1st Jan 2015 onwards). 

= 1.2.0 - 2014-12-12 =

* COMPATIBILITY: Tested on WordPress 4.1
* TWEAK: Code re-factored
* TWEAK: Re-worked the readme.txt file to reflect current status
* FEATURE: Premium version has been launched: https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/
* FEATURE (Premium version): Ability to allow the customer to enter their VAT number, if they have one, and (if it validates) be exempted from VAT transactions. Compatible with WooCommerce's official extension (i.e. you can remove that extension, and your old data will be retained).
* FEATURE (Premium version): Dealing with conflicts: if the customer's IP address + billing (or shipping, according to your WooCommerce settings) conflict, then optionally the customer can self-certify their country (or, you can force them to do this always, if you prefer).
* FIX: The initial value of the "Phrase matches used to identify VAT taxes" setting could be empty (check in your WooCommerce -> Settings -> Tax options, if you are updating from a previous plugin version; the default value should be: VAT, V.A.T, IVA, I.V.A., Value Added Tax)

= 1.1.2 - 2014-12-10 =

* FIX: Fix bug which prevented France (FR) being entered into the rates table. If you had a previous version installed, then you will need to either wait 24 hours before pressing the button to update rates since you last did so, or to clear your transients, or enter French VAT (20% / 10%) manually into the tax table.
* TWEAK: Reduce time which current rates are cached for to 12 hours

= 1.1.1 - 2014-12-09 =

* FIX: Fix bug with display of info in admin area in WooCommerce 2.2

= 1.1 - 2014-12-06 =

* GeoIP information, and what information WooCommerce used in setting taxes, is now recorded at order time
* Recorded VAT-relevant information is now displayed in the admin area

= 1.0 - 2014-11-28 =

* First release: contains the ability to enter and update current EU VAT rates

== Screenshots ==

<em>Note: Screenshots are included below from <a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">the Premium version</a>. Please check the feature list for this plugin to clarify which features are available in which version.</em>

1. A button is added to allow you to enter all EU VAT rates with one click.

2. VAT information being shown in the order details page

3. Per-country VAT reports (more features currently being worked on)

== License ==

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

== Upgrade Notice ==
* 1.3.0 : First implementaton of reports (WC 2.0, 2.1 - not yet 2.2). Important update to make sure order meta-data is stored efficiently.
