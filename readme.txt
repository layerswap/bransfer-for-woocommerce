=== Plugin Name ===
Contributors: bransfer
Tags: ctypto, payment, bransfer
Tested up to: 5.8
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
== Description ==
 
The plugin is allowing to accept crypto payments in Woocommerce instantly and without fees.

BRANSFER PAYMENT GATEWAY PLUGIN FOR WOOCOMMERCE

KEY FEATURES
  
  1. Accept cryptocurrency payments from your customers, such as BTC, ETH, XRP, and others supported by major crypto exchanges.
  2. No blockchain fee.
  3. Instant confirmation.
  4. Detailed payment history and reporting.

CUSTOMER JOURNEY
  
  1. After adding items to the cart, the customer proceeds to checkout.
  3. Then, selects Bransfer as a payment method.
  4. The Bransfer invoice is generated, and the customer gets redirected to Bransfer's payment form. The invoice will display an amount to pay in the selected cryptocurrency at an exchange rate locked for few minutes. If the price changes, the form will force the customer to confirm a new price or cancel the payment.
  5. The customer completes the payment using the selected cryptocurrency wallet immediately.
  6. When the transaction is confirmed, Bransfer notifies the merchant, and the corresponding amount is credited to the Bransfer merchant's exchange account.
 
== Installation ==

REQUIREMENTS

  1. This plugin requires WooCommerce.
  2. A Bransfer merchant account.

PLUGIN INSTALLATION

  1. Get started by signing up for a Bransfer merchant account.
  2. Look for the Bransfer plugin via the WordPress Plugin Manager. From your WordPress admin panel, go to Plugins > Add New > Search plugins and type Bransfer.
  3. Select Brasnfer crypto payments for WooCommerce and click on Install Now and then on Activate Plugin.

After the plugin is activated, Bransfer will appear in the WooCommerce > Settings > Payments section.

PLUGIN CONFIGURATION

After you have installed the Bransfer plugin, the configuration steps are:

  1. Generate an API token(JWT) from your Brasnfer merchant account.
    1.1 Login to your Bransfer merchant account and go to the API Access section.
    1.2 Click Generate button to create a token.
  2. Create a new Application for your Woocommerce store.
  3. Application ID is saved in the newly created application's Settings -> Application Info's Id field. 
  4. Log in to your WordPress admin panel, select WooCommerce > Payments and click on the "Set up" button next to the Bransfer.
  5. Copy the token from 1.2) point and paste it into the API Token field.
  6. Copy the Application ID from 3) point and paste it into the Application ID field.
  7. Enter an email address into the Receiver Email field to receive notification from Bransfer IPN to the selected email address. 
  8. Click "Save changes" at the bottom of the page.

ORDER FULFILMENT

This plugin also includes an IPN (Instant Payment Notification) endpoint to update your WooCommerce order status.

  1. When the customer decides to pay with Bransfer, he is presented with a Bransfer invoice while the WooCommerce order will be set to "Pending."
  2. The customer pays from the redirected URL, the status of the WooCommerce order will change to Processing or Completed.
  3. If a Bransfer invoice expires before the customer completed the payment, the merchant can automatically mark the WooCommerce order as Cancelled via the plugin settings.

== Changelog ==
 
= 1.0 =

== Screenshots ==