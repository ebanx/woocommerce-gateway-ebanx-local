=== EBANX Local Payment Gateway for WooCommerce ===

Contributors: ebanxpay
Tags: credit card, boleto, ebanx, woocommerce, local payment gateway, brazil, cash payment, local payment, card payment, one-click payment, alternative payments, payment processing
Requires at least: 5.0
Tested up to: 5.5
Stable tag: 3.3.7
Requires PHP: 7.1
License: Apache v2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0

With EBANX Pay WooCommerce plugin you will be able to process cash and credit card payments in Brazil.

== Description ==

[EBANX Pay](https://www.ebanxpay.com/) is a payment processor that offers complete solutions for national businesses wanting to sell more in Brazil.
Whether you are an enterprise or running your own startup, EBANX Pay can help you sell more with ease and efficiency.


The installation of EBANX Pay Gateway for WooCommerce is a 100% free and allows you to accept the most popular local payment methods in Brazil.
With the EBANX Pay Gateway for WooCommerce you will be able to process relevant cash and credit card payments in Brazil.

You can install the EBANX Pay with a few steps and get impressive results.

**No technical knowledge is needed to get impressive results; the installation is simple how it should be.**

**EBANX Pay Advantages**

* Security is already taken care of, the customer’s sensitive data doesn’t go to your server but is saved in EBANX environment using PCI standard
* One-click purchases which allow your client to skip the checkout process
* Checkout payment form is responsive and adapts nicely to all mobile screen sizes and themes
* Everything you need in one integration, you don’t have to install any external plugins or extensions

**Customize and Manage Your Payments**

With the EBANX Pay plugin, you can:

* Choose which payment methods are displayed at checkout
* Set a maximum number of instalments
* Select an expiration date for cash payments
* Allow customers to save their credit card information and buy with just one-click and improve your conversions
* Set individual interest rates for each credit card instalment plan
* Create orders & request refunds directly in your WooCommerce store

The plugin also includes:

* Sandbox mode for testing
* Capture mode that when activated allows you to collect payments after a manual review
* Extra fields that are added automatically for payments made in Brazil where customers must provide more information to local regulatory authorities
* Support for checkout managers

**Account & Pricing**

Schedule a call with a Business Development Executive to learn more about our solutions and get to know our pricing per confirmed payment. 

**Want to do a Test Drive?**

Our demonstrations allow you to create a payment as a customer would and to explore all the plugin features **without having to install it**.

Looking for more detailed information? Visit our [Developer’s Academy](https://developers.ebanxpagamentos.com/getting-started/integrations/extensions-and-plugins/woocommerce-plugin/) for step-by-step guides, API references, and integration options or request a call with a Business Development Executive.

**Requirements**

All pages that incorporate the EBANX Pay plugin must be served over HTTPS.

**About EBANX Pay**

[EBANX is a local payments expert](https://www.ebanxpay.com) that offer complete solutions to connect eager buyers with local sellers, increasing the merchant’s revenue in the fastest growing markets in Brazil. Whether you are an enterprise or running your startup, EBANX Pay can help you sell more with ease and efficiency.

== Installation ==

**Automatic**

Automatic installation is the easiest way to do it, and it can be done without leaving your web browser. To do an automatic install of the EBANX Pay plugin, login to the WordPress Dashboard, go to the Plugins menu, and select “Add New.” Then, search for the “EBANX Pay” to find “EBANX Local Payment Gateway for WooCommerce” and click on “Install Now.” After the installation, you just have to activate and get ready to sell even more.

**Manual**

To install the plugin manually, download our plugin and upload it to your web server via an FTP application. Visit the [WordPress codex](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation "WordPress codex") for further instructions. Don't waste your time and improve your results!

**Via GitHub**

The EBANX Pay Gateway Plugin can also be installed using GitHub. You can find our repository [here](https://github.com/ebanx/checkout-woocommerce-local/ "EBANX GitHub repository"). To download the plugin from our repository, please view [our latest release](https://github.com/ebanx/woocommerce-gateway-ebanx-local/releases/latest "Latest Release from GitHub repository") and download the `woocommerce-gateway-ebanx-local.zip` package.

== Frequently Asked Questions ==

= Who can I contact if I still have questions? =

Reach out to one of our integration specialists at localintegration@ebanxpay.com or get in touch with us through our "Help & Support" tab inside the EBANX Dashboard. If you don't have an account yet, access our [EBANX Pay](https://www.ebanxpay.com).

= Where can I find more documentation or instructions? =

On the [EBANX Developer’s Academy](https://developers.ebanxpagamentos.com/getting-started/integrations/extensions-and-plugins/woocommerce-plugin/ "EBANX Pay Developer's Academy") you will find instructions and detailed information about all our plugins.

= Which payment types does EBANX Pay process? =

* Brazil
  * EBANX Boleto Bancário
  * Visa, Mastercard, American Express, Diner’s Club, Discover, Hipercard, Elo, and Aura Domestic Credit Cards

= Which currencies does EBANX Pay accept? =

* BRL - Real

= Can I use my own Checkout Manager plugin? =

Yes, you can.

1. Set up your billing fields in the checkout manager plugin page;
2. Go to the `EBANX Settings` page and open the `Advanced Options` section;
3. Enable the `Use my checkout manager fields` checkbox and fill in the field names as in step 1;
4. There you go, you’re all set!

== Changelog ==

== 3.3.7 ==
* 2021-01-08 - Fix gateway availability check.

== 3.3.6 ==
* 2021-01-04 - Get metadata from order when not found on subscription.

== 3.3.5 ==
* 2021-01-04 - Save card metadata on subscription instead of saving in the order.

== 3.3.4 ==
* 2020-12-21 - Improve notes on subscription payment process.

== 3.3.3 ==
* 2020-11-03 - Improves and simplifies the filter to handle error messages.

== 3.3.2 ==
* 2020-11-03 - Improve customized error code constant.

== 3.3.1 ==
* 2020-10-30 - Refactor filter to customize API response errors.

== 3.3.0 ==
* 2020-10-30 - Add filter to customize API response errors.

== 3.2.0 ==
* 2020-09-23 - Add support to change payment method by user when order is subscriptions. Associate credit card token on subscription.

== 3.1.1 ==
* 2020-09-09 - Cart with only virtual and downloadable does not require processing.

== 3.1.0 ==
* 2020-09-09 - Add support to create payments with split rules.

== 3.0.4 ==
* 2020-08-31 - Include WooCommerce compatibility version. Fix credit card token resolver.

== 3.0.3 ==
* 2020-06-05 - Fix document fields if is a manual order.

== 3.0.2 ==
* 2020-06-05 - Fix credit card callback when fetching the deviceId and throw the error.

== 3.0.3 ==
* 2020-06-05 - Fix document fields if is a manual order.

= 3.0.1 =
* 2020-04-29 - Fix get undefined web service version.

= 3.0.0 =
* 2020-04-27 - Add support to WooCommerce 4.x. IMPORTANT NOTE: tested on PHP 7.1+, WordPress 5.4+ and WooCommerce 4.0+.

= 2.2.4 =
* 2020-03-25 - Fix get country error with benjamin update.

= 2.2.3 =
* 2020-03-25 - Fix checkout with international credit card with foreign document country.

= 2.2.2 =
* 2020-03-24 - Fix condition to enable/disable instalments on checkout.

= 2.2.1 =
* 2020-03-18 - Add EBANX javascript lib in global assets.

= 2.2.0 =
* 2020-03-18 - Enable installment when the cart has subscription.

= 2.1.8 =
* 2020-03-10 - Add customer document on admin panel. Add instalments field on create new order.

= 2.1.7 =
* 2020-02-28 - Adds payment related information on admin panel. Adjust translations.

= 2.1.6 =
* 2020-02-19 - Fix foreign document requirement when country is BR.

= 2.1.5 = 
* 2020-02-19 - Fix foreign document requirement.

= 2.1.4 = 
* 2020-02-19 - Add device finger print on benjamin request. Fix document country for foreign customers.

= 2.1.3 =
* 2020-02-17 - Fix style to hide document field.

= 2.1.2 =
* 2020-02-17 - Fix address validation when payment method is international credit card. Fix error when submit form with blank card.

= 2.1.1 =
* 2020-02-13 - Fix parsing the street number of the address

= 2.1.0 =
* 2020-02-12 - Add international credit card gateway

= 2.0.14 =
* 2020-02-08 - Fixes subscriptions cancelling after failed payment

= 2.0.13 =
* 2020-02-08 - Fixes error when order total is 0 and save user meta fields

= 2.0.12 =
* 2020-02-07 - Update benjamin library

= 2.0.11 =
* 2020-01-24 - Fixes error when save user meta fields

= 2.0.10 =
* 2020-01-24 - Refactor on-click payment process. Add streetComplement on address transformation.

= 2.0.9 =
* 2020-01-10 - Fixes recurrent payment adding payment_type_code in credit card transaction.

= 2.0.8 =
* 2020-01-10 - Fixes recurrent payment.

= 2.0.7 =
* 2020-01-10 - Increase plugin version.

= 2.0.6 =
* 2020-01-10 - Fixes one-click purchase.

= 2.0.5 =
* 2020-01-10 - Fixes and improve queries. Fixes on get database version.

= 2.0.4 =
* 2020-01-09 - Fix Text Domain information.

= 2.0.3 =
* 2020-01-09 - Add billing phone requirement config. Fix optional fields with optional label. Improve translation files.

= 2.0.2 =
* 2020-01-08 - Improve reademe.txt file.

= 2.0.1 =
* 2020-01-08 - Fix some documentation files.

= 2.0.0 =
* 2020-01-08 - First Release.

== Screenshots ==

1. EBANX Features - Allow your customers to save their credit card data to buy with just one-click; in addition to increasing your sales even more.
4. Plugin Configuration - To start your integration, send an email to [hello@ebanxpay.com](mailto:hello@ebanxpay.com) to request your test and live keys. Insert them and choose to enable the sandbox mode for testing.
6. Plugin Configuration - Set more advanced options such as Save Card Data, One-click payment, Enable Auto-Capture, Maximum number of Instalments, etc.

== Security section ==

When you use our plugin, you trust us with your information and agree that we may keep it and use it for the purposes of our commercial relationship. As we are a PCI compliant company, we will keep all your data safe, and will not use it for any other purposes.
