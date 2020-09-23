# CHANGELOG

== 3.2.0 ==
* 2020-09-23 - Add support to change payment method by user when order is subscriptions. Associate credit card token on subscription.

## 3.1.1
* 2020-09-09 - Cart with only virtual and downloadable does not require processing.

## 3.1.0
* 2020-09-09 - Add support to create payments with split rules.

## 3.0.4
* 2020-08-31 - Include WooCommerce compatibility version. Fix credit card token resolver.

## 3.0.3
* 2020-06-05 - Fix document fields if is a manual order.

## 3.0.2
* 2020-06-05 - Fix credit card callback when fetching the deviceId and throw the error.

## 3.0.1
* 2020-04-29 - Fix get undefined web service version.

## 3.0.0
* 2020-04-27 - Add support to WooCommerce 4.x. IMPORTANT NOTE: tested on PHP 7.1+, WordPress 5.4+ and WooCommerce 4.0+.

## 2.2.4
* 2020-03-25 - Fix get country error with benjamin update.

## 2.2.3
* 2020-03-25 - Fix checkout with international credit card with foreign document country.

## 2.2.2
* 2020-03-24 - Fix condition to enable/disable instalments on checkout.

## 2.2.1
* 2020-03-18 - Add EBANX javascript lib in global assets.

## 2.2.0
* 2020-03-18 - Enable installment when the cart has subscription.

## 2.1.8
* 2020-03-10 - Add customer document on admin panel. Add instalments field on create new order.

## 2.1.7
* 2020-02-28 - Adds payment related information on admin panel. Adjust translations.

## 2.1.6
* 2020-02-19 - Fix foreign document requirement when country is BR.

## 2.1.5
* 2020-02-19 - Fix foreign document requirement.

## 2.1.4
* 2020-02-19 - Add device finger print on benjamin request. Fix document country for foreign customers.

## 2.1.3
* 2020-02-17 - Fix style to hide document field.

## 2.1.2
* 2020-02-17 - Fix address validation when payment method is international credit card. Fix error when submit form with blank card.

## 2.1.1
* 2020-02-13 - Fix parsing the street number of the address

## 2.1.0
* 2020-02-12 - Add international credit card gateway

## 2.0.14
* 2020-02-08 - Fixes subscriptions cancelling after failed payment

## 2.0.13
* 2020-02-08 - Fixes error when order total is 0 and save user meta fields

## 2.0.12
* 2020-02-07 - Update benjamin library

## 2.0.11
* 2020-01-24 - Fixes error when save user meta fields

## 2.0.10
* 2020-01-24 - Refactor on-click payment process. Add streetComplement on address transformation.

## 2.0.9
* 2020-01-10 - Fixes recurrent payment adding payment_type_code on credit card transaction

## 2.0.8
* 2020-01-10 - Fixes recurrent payment.

## 2.0.7
* 2020-01-10 - Increase plugin version.

## 2.0.6
* 2020-01-10 - Fixes one-click purchase.

## 2.0.5
* 2020-01-10 - Fixes and improve queries. Fixes on get database version.

## 2.0.4
* 2020-01-09 - Fix Text Domain information.

## 2.0.3
* 2020-01-09 - Add billing phone requirement config. Fix optional fields with optional label. Improve translation files.

## 2.0.2
* 2020-01-08 - Improve reademe.txt file.

## 2.0.0
* 2020-01-06 - First Release.
