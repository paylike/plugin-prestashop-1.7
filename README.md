# Prestashop plugin for Paylike

This plugin is *not* developed or maintained by Paylike but kindly made
available by the community.

Released under the MIT license: https://opensource.org/licenses/MIT

You can also find information about the plugin here: https://paylike.io/plugins/prestashop

## Supported Prestashop versions

* The plugin has been tested with most versions of Prestashop at every iteration. We recommend using the latest version of Prestashop, but if that is not possible for some reason, test the plugin with your Prestashop version and it would probably function properly. 
* Magento version last tested on: *1.7.4.3*

## Installation

1. Log in as administrator and click "Modules" from the left menu and then upload it clicking "UPLOAD A MODULE" form the top.
2. Click the "Configure" button when done installing. 
3. Add the Public and App key that you can find in your Paylike account and enable the plugin and click save from the bottom.

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
 
 * To refund an order you can use the paylike tool box you can find on the order edit screen by selecting refund in the select and inputing the amount.
 * To void an order you can use the paylike tool box by selecting Void.
 * To capture an order in delayed mode, you can either use the status set in settings (move the order to that status), or you can use the tool. 
 
  
