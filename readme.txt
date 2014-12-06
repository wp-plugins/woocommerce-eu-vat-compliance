=== WooCommerce EU VAT Compliance ===
Contributors: DavidAnderson
Requires at least: 3.1
Tested up to: 4.0
Stable tag: 1.0
Tags: woocommerce, eu vat, vat compliance, moss, vat rates, eu tax, hmrc, woocommerce taxes
License: GPLv3
Donate link: http://david.dw-perspective.org.uk/donate

Assists with EU VAT compliance for WooCommerce, for the new VAT regime beginning 1st January 2015, including for with the MOSS system.

== Description ==

From January 1st 2015, all digital goods sold to EU consumers are liable to EU VAT charged in the country of *purchase*, at the VAT rate in that country (background information: http://www.telegraph.co.uk/finance/businessclub/11254829/New-EU-VAT-rules-threaten-to-kill-UK-micro-firms.html).

This applies even if the seller is not based in the EU, and there is no minimum threshold - 100% of sales to EU customers are liable to these per-country taxes. (Whether you think the EU can enforce the collection of taxes in other jurisdictions is a question for lawyers and potential tax-payers, not for software developers).

This WooCommerce plugin provides features to assist with EU VAT compliance from January 1st 2015. Currently, those features include:

- A facility to automatically configure all current EU reduced and standard VAT rates in your WooCommerce tax tables. Much better than typing in approximately 30 different country codes and VAT rates by hand.

- (Not yet released - undergoing testing) - A facility to record necessary information about the buyer's location

- This is an early release - more features will be added later; some will be available only in a premium version; when the premium version is ready for sale, a link will appear here.

Before January 1st 2015, of course, you will want to be careful about which features you enable. Before that date, the previous VAT regime will continue to operate.

This plugin requires WooCommerce 2.0 or later (tested up to 2.2). It will not interfere with any other taxes or reports which you have set up. It fetches data on current VAT rales from Amazon S3 (using SSL if possible); or, upon failure to connect to Amazon S3, from https://euvatrates.com. If your server's firewall does not permit this, then it will use static data contained in the plugin. Please make sure that you review this plugin's installation instructions and have not missed any important information there.

= Legalese =

Please note that, just as with WordPress and its plugins generally (including WooCommerce), this plugin comes with no warranty of any kind and you deploy it entirely at your own risk. Furthermore, nothing in this plugin (including its documentation) constitutes legal or financial or any other kind of advice of any sort. In particular, you remain completely and solely liable for your own compliance with all taxation regulations at all times. Installing this plugin does not absolve you of any legal liabilities, and we provide no guarantee that use of this plugin will cover everything that your store needs to be able to do (for example, you must make sure that your customers are issued with valid VAT receipts).

= Other information =

- Some other WooCommerce plugins you may be interested in: https://www.simbahosting.co.uk/s3/shop/

- This plugin is ready for translations, and we would welcome new translations (please post in the support forum)

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

= 1.0 - 2014-11-28 =

* First release: contains the ability to enter and update current EU VAT rates

== Screenshots ==

1. A button is added to allow you to enter all EU VAT rates with one click.

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
* First release - ability to enter all EU VAT rates
