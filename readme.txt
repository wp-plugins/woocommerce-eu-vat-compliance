=== WooCommerce EU VAT Compliance ===
Contributors: DavidAnderson
Requires at least: 3.2
Tested up to: 4.2
Stable tag: 1.9.1
Tags: woocommerce, eu vat, vat compliance, iva, moss, vat rates, eu tax, hmrc, digital vat, tax, woocommerce taxes
License: GPLv3+
Donate link: http://david.dw-perspective.org.uk/donate

Assists with EU VAT compliance for WooCommerce, for the new VAT regime that began 1st January 2015, including for with the MOSS system.

== Description ==

= The New EU VAT (IVA) law =

Since January 1st 2015, all digital goods (including electronic, telecommunications, software, ebook and broadcast services) sold across EU borders have been liable under EU law to EU VAT (a.k.a. IVA) charged in the country of *purchase*, at the VAT rate in that country (background information: http://www2.deloitte.com/global/en/pages/tax/articles/eu-2015-place-of-supply-changes-mini-one-stop-shop.html). This applies even if the seller is not based in the EU, and there is no minimum threshold.

= How this plugin can take away the pain =

This WooCommerce plugin provides features to assist with EU VAT law compliance. Currently, those features include:

- <strong>Identify your customers' locations:</strong> this plugin will record evidence of your customer's location, using their billing or shipping address, and their IP address (via a GeoIP lookup).

- <strong>Evidence is recorded, ready for audit:</strong> full information that was used to calculate VAT and customer location is displayed in the WooCommerce order screen in the back-end.

- <strong>Display prices including correct VAT from the first page:</strong> GeoIP information is also used to show the correct VAT from the first time a customer sees a product. A widget and shortcode are also provided allowing the customer to set their own country (whole feature requires WooCommerce 2.2.9 or later).

- <strong>Currency conversions:</strong> Most users (if not everyone) will be required to report VAT information in a specific currency. This may be a different currency from their shop currency. This feature causes conversion rate information to be stored together with the order, at order time. Currently, four official sources of exchange rates are available: the European Central Bank (ECB), the Danish National Bank, the Central Bank of the Russian Federation, and HM Revenue & Customs (UK).

- <strong>Entering and maintaining each country's VAT rates:</strong> this plugin assists with entering EU VAT rates accurately by supplying a single button to press in your WooCommerce tax rates settings, to add or update rates for all countries (standard or reduced) with one click.

- <strong>Reporting:</strong> Advanced reporting capabilities, allowing you to see all the information needed to make a MOSS (mini one-stop shop) VAT report. The report is sortable and broken down by country, VAT rate, VAT type (traditional/variable) and order status.

- <strong>Forbid EU sales if any goods have VAT chargeable</strong> - for shop owners for whom EU VAT compliance is too burdensome, this feature will allow you to forbid EU customers to check-out if they have selected any goods which are subject to EU VAT (whilst still allowing purchase of other goods, unlike the built-in WooCommerce feature which allows you to forbid check-out from some countries entirely).

- <strong>Central control:</strong> brings all settings, reports and other information into a single centralised location, so that you don't have to deal with items spread all over the WordPress dashboard.

- <strong>Mixed shops:</strong> You can sell goods subject to EU VAT under the 2015 digital goods regulations and other physical goods which are (until 2016) subject to traditional base-country-based VAT regulations. The plugin supports this via allowing you to identify which tax classes in your WooCommerce configuration are used for 2015 digital goods items. Products which you place in other tax classes are not included in calculations/reports made by this plugin for per-country tax liabilities, even if VAT was charged upon them. (For such goods, you will calculate how much you owe your local tax-man by using WooCommerce's built-in tax reports).

- <strong>Distinguish VAT from other taxes:</strong> if you are in a jurisdiction where you have to apply other taxes also, then this plugin can handle that: it knows which taxes are EU VAT, and which are not.

- <strong>Add line to invoices:</strong> If VAT was paid on the order, then an extra, configurable line can be added to the footer of the PDF invoice (when using the <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>).

- <strong>Refund support:</strong> includes information on refunded VAT, on relevant orders (WooCommerce 2.2 introduced the capability to refund and partially refund orders)

<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">A Premium version is on sale at this link</a>, and currently has these *additional* features ready:

- <strong>VAT-registered buyers can be exempted, and their numbers validated:</strong> a VAT number can be entered at the check-out, and it will be validated (via VIES). Qualifying customers can then be exempted from VAT on their purchase, and their information recorded. This feature is backwards-compatible with the old official WooCommerce "EU VAT Number" extension, so you will no longer need that plugin, and its data will be maintained. The customer's VAT number will be appended to the billing address where shown (e.g. order summary email, PDF invoices). An extra, configurable line specific to this situation can be added to the footer of the PDF invoice (when using the <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>).

- <strong>Optionally allow B2B sales only</strong> - for shop owners who wish to only make sales that are VAT-exempt (i.e. B2B sales only), you can require that any EU customers enter a valid EU VAT number at the check-out.

- <strong>CSV download:</strong> A CSV containing comprehensive information on all orders with EU VAT data can be downloaded (including full compliance information). Manipulate in your spreadsheet program to make arbitrary calculations.

- <strong>Non-contradictory evidences:</strong> require two non-contradictory evidences of location (if the customer address and GeoIP lookup contradict, then the customer will be asked to self-certify his location, by choosing between them).

- <strong>Show multiple currencies for VAT taxes on PDF invoices produced by <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>.

- <strong>Support for the WooCommerce subscriptions extension</strong>

<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">Read more about the Premium version of this plugin at this link.</a>

It is believed (but not legally guaranteed), that armed with the above capabilities, a WooCommerce shop owner will be in a position to fulfil all the requirements of the EU VAT law: identifying the customer's location and collecting multiple pieces of evidence, applying the correct VAT rate, validating VAT numbers for B2B transactions, and having the data needed to create returns. (If in the EU, then you will also need to make sure that you are issuing your customers with VAT invoices containing the information required in your jurisdiction, via <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">a suitable WooCommerce invoice plugin</a>).

= Footnotes and legalese =

This plugin is tested on WooCommerce 2.1 up to 2.3 (releases up to 1.7.1 were also tested on WC 2.0 - you can still download those versions if you wish). It fetches data on current VAT rales from Amazon S3 (using SSL if possible); or, upon failure to connect to Amazon S3, from https://euvatrates.com. If your server's firewall does not permit this, then it will use static data contained in the plugin.

Geographical IP lookups are performed via WooCommerce's built-in geo-location features (WC 2.3+), or if on WC 2.2 or earlier then via the MaxMind GeoIP database via the GeoIP-plugin, which you will be prompted to install; or, alternatively, if you use CloudFlare, then you can <a href="https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do-">activate the CloudFlare feature for sending geographical information</a>.

Please make sure that you review this plugin's installation instructions and have not missed any important information there.

Please note that, just as with WordPress and its plugins generally (including WooCommerce), this plugin comes with no warranty of any kind and you deploy it entirely at your own risk. Furthermore, nothing in this plugin (including its documentation) constitutes legal or financial or any other kind of advice of any sort. In particular, you remain completely and solely liable for your own compliance with all taxation laws and regulations at all times, including research into what you must comply with. Installing any version of this plugin does not absolve you of any legal liabilities, or transfer any liabilities of any kind to us, and we provide no guarantee that use of this plugin will cover everything that your store needs to be able to do.

Whether you think the EU's treaties with other jurisdictions will lead to success in enforcing the collection of taxes in other jurisdictions is a question for lawyers and potential tax-payers, not for software developers!

Many thanks to Diego Zanella, for various ideas we have swapped whilst working on these issues. Thanks to Dietrich Ayala, whose NuSOAP library is included under the LGPLv2 licence.

= Other information =

- Some other WooCommerce plugins you may be interested in: https://www.simbahosting.co.uk/s3/shop/

- This plugin is ready for translations (English, Finnish, French and German are currently available), and we would welcome new translations (please post them in the support forum; <a href="http://plugins.svn.wordpress.org/woocommerce-eu-vat-compliance/trunk/languages/">the POT file is here</a>, or you can contact us and ask for a web-based login for our translation website).

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

= I want to make everyone pay the same prices, regardless of VAT =

This is not strictly an EU VAT compliance issue, and so does not come under the remit of this plugin. (Suggestions that can be found on the Internet that charging different prices in difference countries breaks non-discrimination law have no basis in fact). There are, however, solutions available for this problem; for example: https://marketpress.com/product/woocommerce-eu-vat-checkout/

== Changelog ==

= 1.9.1 - 2015-04-09 =

* FEATURE: In-dashboard reports table now includes "refunds" column
* TWEAK: Added explanatory note and link to WooCommerce refunds documentation, to help users understand the meaning/derivation of refunds data
* TWEAK: Updated a couple of the plugin screenshots
* TWEAK: Added free/Premium comparison table to free version
* TRANSLATIONS: Updated POT file
* FIX: Fix a bug in 1.9.0 that caused 100% discounted orders (i.e. 100% coupon) to result in an erronenous message appearing in the reports dashboard

= 1.9.0 - 2015-04-08 =

* FEATURE: The order-page widget now additionally displays VAT refund information, if a refund exists on the order
* FEATURE: The CSV download (Premium) now contains additional column with VAT refund information (per-rate, and total, in both order and reporting currencies)
* TWEAK: Premium version now contains support link to the proper place (not to wordpress.org's free forum)
* FIX: "Export CSV" button/link did not handle the chosen date range correctly in all situations
* FIX: Bug that caused items in orders with the same VAT rate, but which differed through some being digital VAT and others traditional VAT (i.e. physical goods), being wrongly banded together in CSV download VAT summaries.

= 1.8.5 - 2015-04-02 =

* FEATURE: Add "Items (without VAT)" column to dashboard VAT report. (Requires all orders in the selected period to have been made with WC 2.2 or later).
* TWEAK: Tested + compatible with WP 4.2 and later (tested on beta3-31975)

= 1.8.4 - 2015-03-24 =

* TWEAK: Prevent PHP notice when collating report data on orders recorded by older versions of the plugin
* TWEAK: Change the default order statuses selected on the reports page to 'completed' and 'processing' only. (It's unlikely that data for orders with statuses like 'failed' or 'pending payment' are what people want to see at first).
* TWEAK: Cause selected status boxes on the report page to be retained when selecting a different quarter

= 1.8.3 - 2015-03-16 =

* FIX: Correct one of the VAT column names in the CSV download
* FIX: Display 0, not 1, where relevant in secondary VAT columns in the CSV download
* FIX: Prevent fatal error on reports page if the user had never saved their settings.
* TWEAK: If the user has never saved their settings, then default to using ECB as the exchange rate provider (instead of saving no currency conversion information).
* TRANSLATION: Updated POT file, and updated French and Finnish translations.

= 1.8.1 - 2015-03-13 =

* FIX: Fix issue in updater that could cause blank page on some sites

= 1.8.0 - 2015-03-05 =

* FIX: Reports table now sorts on click on column headings again (unknown when it was broken)
* FEATURE: EU VAT report now re-coded to show data in the configured reporting currency (only), and to show shipping VAT separately
* FEATURE: Downloadable CSV now shows separate VAT totals for each rate in separate rows, and shows separate rows for variable and traditional non-variable VAT (if your shop sells both kinds of goods)
directory due to licensing complications.
* FEATURE: Downloadable CSV now shows information on the configured reporting currency (as well as the order currency)
* FEATURE: (Premium) - updater now added so that the plugin integrates fully with the WP dashboard's updates mechanism
* TWEAK: Removed the static 'rates' column from the VAT report table (which only showed the current configured rates), and instead show a row for each rate actually charged.
* TWEAK: Reports page now uses the built-in WooCommerce layout, including quick-click buttons for recent quarters (some code used from Diego Zanella, gratefully acknowledged)
* TWEAK: Columns in downloadable CSV are now translatable (translations welcome)
* TWEAK: Re-ordered and re-labelled some columns in CSV download for clarity
* TWEAK: Provide link to download location for geoip-detect plugin, if relevant - it is no longer present in the wordpress.org
* TRANSLATION: New POT file

= 1.7.8 - 2015-02-28 =

* TRANSLATION: Finnish translation, courtesy of Arhi Paivarinta

= 1.7.7 - 2015-02-23 =

* FIX: Deal with undocumented change in WC's tax tables setup in WC 2.3 - the "add/update rates" feature is now working again on WC 2.3

= 1.7.6 - 2015-02-20 =

* TWEAK: VAT number fields will no longer appear at the check-out if there were no VAT-liable items in the cart
* TWEAK: Add wc_eu_vat_default_vat_number_field_value filter, allowing developers to pre-fill the VAT number field (e.g. with a previously-used value)

= 1.7.5 - 2015-02-17 =

* TWEAK: If on WC 2.3 or greater, then use WC's built-in geo-location code for geo-locating, and thus avoid requiring either CloudFlare or a second geo-location plugin.
* TWEAK: Avoided using a deprecated method in WC 2.3

= 1.7.4 - 2015-02-13 =

* FIX: The HMRC (UK) decided to move their rates feed to a new URL this month (again!), removing one of the under-scores from the URL (also see changelog for 1.6.7). This fix will also be OK next month in case this was a mistake and they revert, or even if they switch back to Dec 2014's location. Update in order to make sure you are using current rates.

= 1.7.2 - 2015-02-07 =

* COMPATIBILITY: Tested on WooCommerce 2.3 (RC1). Note that WooCommerce EU VAT Compliance will over-ride WooCommerce 2.3's built-in geo-location features - so, you should not need to adjust any settings after updating to WooCommerce 2.3. WooCommerce 2.0 is no longer officially supported or tested (though this release is still believed to be compatible).
* TWEAK: Add order number to the CSV download (allowing order number to differ from the WooCommerce order ID - e.g. if using http://www.woothemes.com/products/sequential-order-numbers-pro/).
* TWEAK: Introduce WC_EU_VAT_NOCOUNTRYPRESELECT constant, allowing you to disable the built-in country pre-selection (if, for example, you already have an existing solution)

= 1.7.1 - 2015-01-20 =

* FIX: No longer require the shop base country to be in the EU when applying VAT exemptions for B2B customers
* FEATURE: Add an option for a separate checkbox for the "show prices without taxes" option in the country-selection widget (in addition to the existing, but not-necessarily-easy-to-find, menu option on the country list)
* TRANSLATION: Updated French translation (thanks to Guy Pasteger)

= 1.7.0 - 2015-01-13 =

* USER NOTE: This plugin is already compatible with version 2.0 of the GeoIP detect plugin, but if/when you update to that, you will need to update the GeoIP database (as version 2.0 uses a new format) - go to Tools -> GeoIP Detection ... you will then need to reload the dashboard once more to get rid of the "No database" warning message.
* FEATURE: Optionally forbid checkout if any goods liable to EU VAT are in the cart (this can be a better option than using WooCommerce's built-in feature to forbid all sales at all to EU countries - perhaps not all your goods are VAT-liable. Note that this is a stronger option that the existing option to only forbid consumer sales (i.e. customers who have no access to VAT exemption via supply of a VAT number))
* FEATURE: Support mixed shops, selling goods subject to EU VAT under the 2015 digital goods regulations and other goods subject to traditional base-country-based VAT regulations. The plugin supports this via allowing you to identify which tax classes in your WooCommerce configuration are used for 2015 digital goods items. Products which you place in other tax classes are not included in calculations/reports made by this plugin for per-country tax liabilities, even if VAT was charged upon them. (For such goods, you calculate how much you owe your local tax-man by using WooCommerce's built-in tax reports).
* FEATURE: Within {iftax}{/iftax} tags, you can use the special value value {country_with_brackets} to show the country that tax was calculated using, surrounded by brackets, if one is relevant; or nothing will be shown if not. Example: {iftax}incl. VAT {country_with_brackets}{/iftax}. This is most useful for mixed shops, where you will not what the confuse the customer by showing the country for products for which the VAT is not based upon country.
* FIX: Country pre-selection drop-down via shortcode was not activating if the page URL had a # in it.
* FIX: Unbalanced div tag in Premium plugin on checkout page if self-certification was disabled.
* TWEAK: Negative VAT number lookups are now cached for 1 minute instead of 7 days (to mitigate the possible undesirable consequences of cacheing a false negative, and given that we expect very few negatives anyway)
* TWEAK: Change prefix used for transient names, to effectively prevent any previously cached negative lookups for certain valid Spanish VAT numbers (see 1.6.14) being retained, without requiring the shop owner to manually flush their transients.
* TRANSLATION: Updated French translation (thanks to Guy Pasteger)

= 1.6.14 - 2015-01-10 =

* FEATURE: Upon discovery of a valid Spanish VAT number which the existing API server did not return as valid, we now use the official VIES service directly, and fall back to a second option if that does not respond positively (thus adding some redundancy if one service is down).
* FEATURE: VAT number validity at the checkout is now checked as it is typed (i.e. before order is placed), and feedback given allowing the customer to respond (e.g. hint that you have chosen a different country to that which the VAT number is for).
* FEATURE: Support for the official exchange rates of the Central Bank of the Russian Federation (http://www.cbr.ru)
* TWEAK: Move the position of the "VAT Number" field at the checkout to the bottom of the billing column, and make it filterable
* TWEAK: If Belgian customer enters a 9-digit VAT number, then automatically prefix with a 0 (https://www.gov.uk/vat-eu-country-codes-vat-numbers-and-vat-in-other-languages)
* TRANSLATIONS: Updated POT file

= 1.6.13 - 2015-01-08 =

* FIX: The button to add tax rates was not appearing when WordPress was en Français.
* TWEAK: Add TVA/T.V.A. to the list of taxes recognised as VAT by default
* TWEAK: Readiness test in the free version will now alert if the WooCommerce Subscriptions extension is active (free version does not contain the extra code needed to support it)
* TWEAK: Add link in the control centre to the official EU PDF detailing current VAT rates

= 1.6.12 - 2015-01-06 =

* FEATURE: CSV downloads now take notice of the chosen dates in the date selector widget (reports) (i.e. so you can now also download selected data, instead of only downloading all data)
* FIX: Some more translated strings are now translated in the admin interface.
* FIX: Restore functionality on WooCommerce < 2.2 (checkout broken in 1.6.0)
* FIX: Don't tweak the "taxes estimated for" message on the cart page on WooCommerce < 2.2.9, since the country choice widget requires this version
* FIX: The button on the report date selector form, if accessed via the compliance centre (rather than WooCommerce reports) was not working

= 1.6.11 - 2015-01-06 =

* FIX: Restore ability to run on PHP 5.2
* FIX: If no current exchange rates were available at check-out time, and HTTP network download failed, then this case was handled incorrectly.
* FIX: Some settings strings were not being translated in the admin interface.
* FIX: "Taxes estimated for" message on the cart page now indicates the correct country
* TWEAK: Move widget + shortcode code to a different file
* TWEAK: CSV order download will now only list orders from 1st Jan 2015 onwards, to prevent large numbers of database queries for orders preceeding the VAT law on shops with large existing order lists.
* TWEAK: CSV order download will now intentionally show orders from non-EU countries (since these could be subject to audit for compliance also); a later release will make this optional. Before, these orders were shown, though not intentionally, and the data was incomplete.
* TRANSLATION: Updated French translation (thanks to Guy Pasteger)

= 1.6.9 - 2015-01-04 =

* FIX: Download of current VAT rates via HTTP was not working (bundled copy of rates in the plugin always ended up getting used)
* FEATURE: New readiness tests added for checking access to current VAT rates via network, checking that each country has an entry in a tax table, and checking that they agree with the apparent current rates.
* TWEAK: Don't load un-needed PHP classes if not in admin area (minor performance improvement)

= 1.6.8 - 2015-01-03 =

* FEATURE: VAT rate tables can now be pre-filled for any tax class (not just WooCommerce's built-in standard / reduced), and you can choose which rates to fetch them from
* FIX: Fix bug (since 1.6.0) in the free version that caused any widget-selected country's VAT rate to be applied at the check-out, despite other settings.
* FIX: Where no reduced rate exists (currently, Denmark), the standard rate is added instead
* UPDATE: Default VAT rates for Luxembourg updated to reflect new values (Jan 2015) - you will need to update your WooCommerce tax tables to pick up the new rates
* TWEAK: Round prices before comparing taxed and untaxed prices (otherwise two actually identical prices may apparently differ due to the nature of PHP floating point arithmetic - which could cause an "including tax" label to show when tax was actually zero)
* TWEAK: CSV spreadsheet download now supplies date in local format (as well as standard ISO-8601 format) (suggestion from Guy Pasteger)
* TWEAK: Date entry boxes in the control centre now have a date-picker widget (as they did if used from the WooCommerce reports page)
* TWEAK: Record + display information on which exchange rate provider was used to convert (useful for audit), and the recorded rate
* TWEAK: Added new readiness test: tests that all coupons are applied before tax (doing so after tax leads to non-compliant VAT invoices)
* TWEAK: Added new readiness test: check that tax is enabled for the store
* TRANSLATIONS: Updated POT file

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

7. Compliance report, checking a number of common essentials for configuring your store correctly for EU VAT.

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
* 1.9.1 : Refund information now included in dashboard report, order-page widget, and CSV download. Free/Premium comparison table added.