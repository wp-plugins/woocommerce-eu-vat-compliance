=== WooCommerce EU VAT Compliance ===
Contributors: DavidAnderson
Requires at least: 3.1
Tested up to: 4.1
Stable tag: 1.6.7
Tags: woocommerce, eu vat, vat compliance, iva, moss, vat rates, eu tax, hmrc, digital vat, tax, woocommerce taxes
License: GPLv3
Donate link: http://david.dw-perspective.org.uk/donate

Assists with EU VAT compliance for WooCommerce, for the new VAT regime beginning 1st January 2015, including for with the MOSS system.

== Description ==

= The New EU VAT (IVA) law =

From January 1st 2015, all digital goods (including electronic, telecommunications, software, ebook and broadcast services) sold across EU borders are liable under EU law to EU VAT (a.k.a. IVA) charged in the country of *purchase*, at the VAT rate in that country (background information: http://www2.deloitte.com/global/en/pages/tax/articles/eu-2015-place-of-supply-changes-mini-one-stop-shop.html). This applies even if the seller is not based in the EU, and there is no minimum threshold.

= How this plugin can take away the pain =

This WooCommerce plugin provides features to assist with EU VAT law compliance from January 1st 2015. Currently, those features include:

- <strong>Identify your customers' locations:</strong> this plugin will record evidence of your customer's location, using their billing or shipping address, and their IP address (via a GeoIP lookup).

- <strong>Forbid EU sales (feature not yet released)</strong> - for shop owners for whom EU VAT compliance is too burdensome, this feature will allow you to forbid EU customers to check-out.

- <strong>Evidence is recorded, ready for audit:</strong> full information that was used to calculate VAT is displayed in the WooCommerce order screen in the back-end.

- <strong>Display prices including correct VAT from the first page:</strong> GeoIP information is also used to show the correct VAT from the first time a customer sees a product. A widget and shortcode are also provided allowing the customer to set their own country (whole feature requires WooCommerce 2.2.9 or later).

- <strong>Currency conversions:</strong> Most users (if not everyone) will be required to report VAT information in a specific currency. This may be a different currency from their shop currency. This feature causes conversion rate information to be stored together with the order, at order time. Currently, three official sources of exchange rates are available: the European Central Bank (ECB), the Danish National Bank, and HM Revenue & Customs (UK).

- <strong>Entering and maintaining each country's VAT rates:</strong> this plugin assists with entering EU VAT rates accurately by supplying a single button to press in your WooCommerce tax rates settings, to add or update rates for all countries (standard or reduced) with one click.

- <strong>Reporting:</strong> Advanced reporting capabilities, allowing you to see all the information needed to make a MOSS (mini one-stop shop) VAT report. The report is sortable and broken down by country, currency and order status.

- <strong>Central control:</strong> brings all settings, reports and other information into a single centralised location, so that you don't have to deal with items spread all over the WordPress dashboard.

- <strong>Distinguish VAT from other taxes:</strong> if you are in a jurisdiction where you have to apply other taxes also, then this plugin can handle that: it knows which taxes are VAT, and which are not.

- <strong>Add line to invoices:</strong> If VAT was paid on the order, then an extra, configurable line can be added to the footer of the PDF invoice (when using the <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>).

<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">A Premium version is on sale at this link</a>, and currently has these *additional* features ready:

- <strong>VAT-registered buyers can be exempted, and their numbers validated:</strong> a VAT number can be entered at the check-out, and it will be validated (via VIES). Qualifying customers can then be exempted from VAT on their purchase, and their information recorded. This feature is backwards-compatible with the old official WooCommerce "EU VAT Number" extension, so you will no longer need that plugin, and its data will be maintained. The customer's VAT number will be appended to the billing address where shown (e.g. order summary email, PDF invoices). An extra, configurable line specific to this situation can be added to the footer of the PDF invoice (when using the <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>).

- <strong>Optionally allow B2B sales only</strong> - for shop owners who wish to only make sales that are VAT-exempt (i.e. B2B sales only), you can require that any EU customers enter a valid EU VAT number at the check-out.

- <strong>CSV download:</strong> A CSV containing all orders with EU VAT data can be downloaded (including full compliance information).

- <strong>Non-contradictory evidences:</strong> require two non-contradictory evidences of location (if the customer address and GeoIP lookup contradict, then the customer will be asked to self-certify his location, by choosing between them).

- <strong>Show multiple currencies for VAT taxes on PDF invoices produced by <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>.

- <strong>Support for the WooCommerce subscriptions extension</strong>

<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">Read more about the Premium version of this plugin at this link.</a>

Other features are still being weighed up and considered. It is believed (but not legally guaranteed), that armed with the above capabilities, a WooCommerce shop owner will be in a position to fulfil all the requirements of the EU VAT law: identifying the customer's location and collecting multiple pieces of evidence, applying the correct VAT rate, validating VAT numbers for B2B transactions, and having the data needed to create returns. (If in the EU, then you will also need to make sure that you are issuing your customers with VAT invoices containing the information required in your jurisdiction, via <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">a suitable WooCommerce invoice plugin</a>).

Before January 1st 2015, of course, you will want to be careful about which features you enable. Before that date, the previous VAT / IVA regime will continue to operate.

= Footnotes and legalese =

This plugin requires WooCommerce 2.0 or later (tested up to 2.2). It fetches data on current VAT rales from Amazon S3 (using SSL if possible); or, upon failure to connect to Amazon S3, from https://euvatrates.com. If your server's firewall does not permit this, then it will use static data contained in the plugin.

Geographical IP lookups are performed via the MaxMind GeoIP database, via the GeoIP-plugin, which you will be prompted to install; or, alternatively, if you use CloudFlare, then you can <a href="https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do-">activate the CloudFlare feature for sending geographical information</a>.

Please make sure that you review this plugin's installation instructions and have not missed any important information there.

Please note that, just as with WordPress and its plugins generally (including WooCommerce), this plugin comes with no warranty of any kind and you deploy it entirely at your own risk. Furthermore, nothing in this plugin (including its documentation) constitutes legal or financial or any other kind of advice of any sort. In particular, you remain completely and solely liable for your own compliance with all taxation laws and regulations at all times, including research into what you must comply with. Installing any version of this plugin does not absolve you of any legal liabilities, or transfer any liabilities of any kind to us, and we provide no guarantee that use of this plugin will cover everything that your store needs to be able to do.

Whether you think the EU's treaties with other jurisdictions will lead to success in enforcing the collection of taxes in other jurisdictions is a question for lawyers and potential tax-payers, not for software developers!

Many thanks to Diego Zanella, for various ideas we have swapped whilst working on these issues.

= Other information =

- Some other WooCommerce plugins you may be interested in: https://www.simbahosting.co.uk/s3/shop/

- This plugin is ready for translations (English, French and German are currently available), and we would welcome new translations (please post them in the support forum; <a href="http://plugins.svn.wordpress.org/woocommerce-eu-vat-compliance/trunk/languages/">the POT file is here</a>, or you can contact us and ask for a web-based login for our translation website).

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

= How can I display a widget allowing a visitor to pre-select their country, when viewing products (and thus set VAT accordingly)? =

There is a widget for this; so, look in your dashboard, in Appearance -> Widgets. You can also display it anywhere in page content, using a shortcode, optionally including an option for displaying prices without taxes: [euvat_country_selector include_notaxes="true|false"]. Note: this feature requires WooCommerce 2.2.9 or later, as previous versions did not include the necessary hooks to make this feature possible.

== Changelog ==

= 1.6.7 - 2015-01-01 =

* TWEAK: Added a 'classes' parameter to the [euvat_country_selector] shortcode, allowing CSS classes to be added to the widget
* TWEAK: Correct filter name in base XML provider
* FIX: "VAT Number" heading would sometimes show at the check-out when it was not needed (Premium)
* FIX: The HMRC (UK) decided to move their rates feed to a different URL this month, swapping hyphens for under-scores. How stupid. This fix will also be OK next month in case this was a mistake and they revert.

= 1.6.6 - 2014-12-31 =

* FIX: Fix bug that could cause the 'Phrase matches used to identify VAT taxes' and 'Invoice footer text (B2C)' settings to be reset to default values.
* TWEAK: Add help text to the settings in the control centre, mentioning the {iftax} and {country} tags.
* TWEAK: Automatic entries in WooCommerce tables now show the VAT rate in the name - because compliant invoices in some states require to show the rate. It is recommended that you go and update your tables in WooCommerce -> Settings -> Tax -> (rate), if this applies to you (you may need to delete all existing rows).

= 1.6.5 - 2014-12-31 =

* TWEAK: Those with non-EU billing addresses (or shipping, if that's what you're using) are no longer exempted from other checks (specifically, self-certification in the case of an address/GeoIP conflict). This release is for the Premium version only (since the tweak does not affect the free version).

= 1.6.4 - 2014-12-31 =

* FEATURE: Support official exchange rates from the Danish National Bank (https://www.nationalbanken.dk/en/statistics/exchange_rates/Pages/Default.aspx)
* TRANSLATION: German translation is now updated, courtesy of Gunther Wegner.
* TRANSLATION: New French translation, courtesy of Guy Pasteger.

= 1.6.3 - 2014-12-30 =

* FEATURE: You can now enter special values in WooCommerce's 'Price display suffix' field: anything enclosed in between {iftax} and {/iftax} will only be added if the item has taxes; and within that tag, you can use the special value {country} to show the country that tax was calculated using. Example: {iftax}incl. VAT{/iftax} More complicated example: {iftax}incl. VAT ({country}){/iftax}
* FIX: Resolve issue that required self-certification even when none was required, if the user was adding an extra option to the self-certification field via a filter.

= 1.6.2 - 2014-12-30 =

* FIX: Remove debugging code that was inadvertantly left in 1.6.0
* FIX: Fix fatal PHP error in admin products display (since 1.6.0)

= 1.6.0 - 2014-12-30 =

* FEATURE: Detect visitor's country and display prices accordingly on all shop pages from their first access (requires WooCommerce 2.2.9 or later; as noted in the WooCommerce changelog - https://wordpress.org/plugins/woocommerce/changelog/ - that is the first version that allows the taxable country to be changed at this stage). This feature also pre-sets the billing country on the check-out page.
* FEATURE: Option to make entry of VAT number for VAT exemption either optional, mandatory, or not possible. (Previously, only 'optional' was available). This means that store owners can decide to always charge VAT, or to not take orders from EU customers who are not VAT exempt. (Non-EU customers can still make orders; if you do not wish that to be possible, then there are existing WooCommerce settings for that). (This option is only relevant to the premium version, as the free version has no facility for entering VAT numbers).
* FEATURE: Support for WooCommerce subscriptions (Premium)
* TWEAK: Self-certification option now asks for 'country of residence', rather than of current location; to comply with our updated understanding of what the user should be asked to do. (But note that the message was, and continues to be, over-ridable via the wc_eu_vat_certify_message filter).
* TWEAK: Make it possible (via a filter, wc_eu_vat_certify_form_field) to not pre-select any option for the self-certified VAT country. If your view is no option should be pre-selected, then you can use this filter. (We offer you no legal or taxation advice - you are responsible to consult your own local resources).
* TWEAK: First beginnings of the readiness report: will now examine your WC version and "tax based on" setting.
* TWEAK: EU VAT report now moved to the 'Taxes' tab of the WooCommerce reports (from 'Orders')
* TRANSLATION: German translation is now complete, courtesy of Gunther Wegner. POT file updated.

= 1.5.7 - 2014-12-29 =

* FEATURE: Add the option to add configurable footer text to invoices produced by the <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">WooCommerce PDF invoices and packing slips plugin</a>, if VAT was paid; or a different message is a valid VAT number was added and VAT was removed.
* FEATURE: New German translation, courtesy of Gunther Wegner

= 1.5.6 - 2014-12-27 =

* FEATURE (Premium): Option to display converted amounts for VAT taxes on invoices produced by the <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">WooCommerce PDF invoices and packing slips plugin</a>.
* TWEAK: Prevent many useless database queries for reports when handling orders made before the plugin was active
* TWEAK: Prevent a PHP notice on WC < 2.2 for the VAT number field at the checkout
* FIX: Prevent PHP notices + missing data for some currency combinations in the CSV spreadsheet download

= 1.5.5 - 2014-12-26 =

* FIX: Monaco and the Isle of Man were previously being erroneously omitted from reports, despite being part of the EU for VAT purposes
* FIX: The Isle of Man was being missed when rates were being automatically added/updated
* FEATURE: If the customer added a VAT number for VAT exemption (Premium), then it will be appended to the billing address, where relevant (e.g. email order summary, PDF invoices). Credit to Diego Zanella for the idea and modified code.
* FEATURE: Rate information is now saved at order time in more detail, and displayed by rate; this is important data, especially if you sell goods which are not all in the same VAT band (i.e. different VAT bands in the same country, e.g. standard rate and reduced rate)
* TWEAK: Move compliance information on the order screen into its own meta box
* TWEAK: Exchange rate information is now stored with the order in a more convenient format - we recommend you update (though, the old format is still supported; but, it's not 1st Jan yet, so actually we recommend you apply every update until then, as nobody has a good reason to be running legacy code before the law launches).

= 1.5.4 - 2014-12-26 =
 
* FIX: Back-end order page now shows the VAT paid as 0.00 instead of 'Unknown', if a valid VAT number was entered. The VAT number is also shown more prominently.
* FIX: Add missing file to 1.5.2 release (exchange rate providers were not working properly without it)
* TWEAK: Settings page will now require the user to confirm that they wish to leave, if they have unsaved changes

= 1.5.2 - 2014-12-24 =

* TWEAK: Re-worked the exchange rate cacheing layer to provide maximum chance of returning an exchange rate (out-of-date data is better than no data)

= 1.5.1 - 2014-12-24 =

* FEATURE: Added the European Central Bank's exchange rates as a source of exchange rates

= 1.5.0 - 2014-12-24 =

* FEATURE: Currency conversion: if your shop sells in a different currency than you are required to make VAT reports in, then you can now record currency conversion data with each order. Currently, the official rates of HM Revenue & Customs (UK) are used; more providers will be added.

= 1.4.2 -2014-12-23 =

* FEATURE: Control centre now contains relevant WooCommerce settings, and links to tax tables, for quick access

= 1.4.1 - 2014-12-22 =

* FEATURE: Dashboard reports are now available on WooCommerce 2.2, with full functionality (so, now available on WC 2.0 to 2.2)
* FEATURE: All versions of the plugin can now select date ranges for reports
* FEATURE: Download all VAT compliance data in CSV format (Premium version)
* TWEAK: Report tables are now sortable via clicking the column headers

= 1.4.0 - 2014-12-19 =

* FEATURE: Beginnings of a control centre, where all functions are brought together in a single location, for ease of access (in the dashboard menu, WooCommerce -> EU Vat Compliance)
* TRANSLATIONS: A POT file is available for translators to use - http://plugins.svn.wordpress.org/woocommerce-eu-vat-compliance/trunk/languages/wc_eu_vat_compliance.pot

= 1.3.1 - 2014-12-18 =

* FEATURE: Reports have now been added to the free version. So far, this is still WC 2.0 and 2.1 only - 2.2 is not yet finished.
* FIX: Reporting in 1.3.0 was omitting orders with order statuses failed/cancelled/processing, even if the user included them

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


1. A button is added to allow you to enter all EU VAT rates with one click. <em>Note: Screenshots are included below from <a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">the Premium version</a>. Please check the feature list for this plugin to clarify which features are available in which version.</em>

2. VAT information being shown in the order details page

3. Per-country VAT reports

4. Download all compliance information in a spreadsheet.

5. Compliance dashboard, bringing all settings and information into one place

6. Currency conversions, if you sell and report VAT in different currencies.

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
* 1.6.7 : Fix settings saving bug; use tax rates in tax names instead of country. Add classes parameter to shortcode.