# Prestashop plugin for Paylike [![Build Status](https://travis-ci.org/paylike/plugin-prestashop-1.7.svg?branch=master)](https://travis-ci.org/paylike/plugin-prestashop-1.7)

This plugin is *not* developed or maintained by Paylike but kindly made
available by the community.

Released under the MIT license: https://opensource.org/licenses/MIT

You can also find information about the plugin here: https://paylike.io/plugins/prestashop-1.7

## Supported Prestashop versions
[![Last succesfull test](https://log.derikon.ro/api/v1/log/read?tag=prestashop17&view=svg&label=Prestashop&key=ecommerce&background=011638)](https://log.derikon.ro/api/v1/log/read?tag=prestashop17&view=html)

* The plugin has been tested with most versions of Prestashop at every iteration. We recommend using the latest version of Prestashop, but if that is not possible for some reason, test the plugin with your Prestashop version and it would probably function properly.
    - Last tested on version: Prestashop 1.7.7.5


## Installation
Once you have installed Prestashop, follow these simple steps:
1. Signup at [paylike.io](https://paylike.io) (it’s free)
1. Create a live account
1. Create an app key for your Prestashop website
1. Log in as administrator and click "Modules" from the left menu and then upload it clicking "UPLOAD A MODULE" form the top.
2. Click the "Configure" button when done installing.
3. Add the Public and App key that you can find in your Paylike account and enable the plugin and click save from the bottom.


## One Page Supercheckout compatibility patch
* Last tested on: *One Page Supercheckout v6.0.9*

In order to enable compatibility feature provided by our module, please change the content of the following files as described below:
---> File: prestashop_root/modules/supercheckout/views/js/front/supercheckout.js
1. find line `if ($('input:radio[name="payment_method"]:checked').hasClass('binary')) {`:
2. add the following code snippet before:
```php
// Start: Changes for PAYLIKE PAYMENT MODULE
if (payment_module_name == 'paylikepayment') {
    PayLikePayment.init();
    PayLikePayment.pay();
    hide_progress();
    $("html, body").animate({scrollTop: $("#hosted-fields-form").offset().top}, "fast");
    return;
}
// END: Changes for PAYLIKE PAYMENT MODULE
```

## Updating settings
Under the extension settings, you can:
 * Update the payment method text in the payment gateways list
 * Update the payment method description in the payment gateways list
 * Update the title that shows up in the payment popup
 * Update the popup description, choose whether you want to show the popup  (the cart contents will show up instead)
 * Add test/live keys
 * Set payment mode (test/live)
 * Change the capture type (Instant/Manual via Paylike Tool)
 * Change the status of the order which is going to trigger a capture in delayed mode.


 ## Refunding, voiding and capturing
 * To `Refund` an order make sure you checked the "Refund Paylike" checkbox durring the default Prestashop procedure for Partial Refund. Standard Refund and Return Product procedures also have this feature only for Prestashop version >= 1.7.7.
 Note: If for some reason the Refund procedure via Paylike fails, you will be notified and manual action will be required in your online Paylike Tool account.
 * To `Void` an order move the order status to "Canceled".
 * To `Capture` an order in delayed mode, use the status set in Paylike module settings (move the order to that status).

 * For Prestashop < 1.7.7 you can procede capture, void and refund actions via Paylike toolbox also.

 ## Advanced
 Due to the floating point precision issue with some numbers, it is recommended to have the bcmath extension installed.

 ## Changelog

#### 1.4.0:
- Updated js SDK to 10.js
- Updated logic to work with SDK v10 version
- Removed module version from other files

#### 1.2.0:
- Compatibility fixes for Prestashop version > 1.7.7.x
- Updated tests

#### 1.1.2:
- One Page Supercheckout compatibility patch

#### 1.1.1:
- Update to js SDK 6.js

#### 1.1.0:
- Integration with One Page Checkout v4.0.10 - by PresTeamShop: Disable preloader after order payment popup is triggered

#### 1.0.9:
- Bug Fix: Check Terms & Conditions checkbox before order submission.
- Bug fix - Disable the checkout button together with the checkbox

#### 1.0.8:
- Bug fix - Escape double quotes for json encoded products string

#### 1.0.7:
- Updated Travis
- Compatibility fix - PHP 5.6 Tested on Prestashop 1.7.2.4 +
- Bug fix - Replace deprecated window load event listener on checkout page
- Add onepagecheckout support

#### 1.0.6:
- Added logic to validate the public and private keys upon saving.
- Fixes - Code review
- Updated tests

#### 1.0.5:
- Fix tests for newer PS versions
- Add transaction id to payment storage

#### 1.0.4:
- Modified structure with tests
- Fix rounding and add travis
- Update testing info

#### 1.0.3:
- Update readme, refactor javascript

#### 1.0.2:
- Fixed translation issue

#### 1.0.1:
- Added zip file
- Removed capture descriptor

#### 1.0.0:
- Initial commit
