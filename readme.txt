=== BeyondCart Connector ===
Authors: beyondcart
Contributors: beyondcart
Tags: beyondcart, mobile app, mobile app for woocommerce, engagement platform, push notifications, react native mobile app, woocommerce mobile app
Requires at least: 4.7+
Tested up to: 6.6
Requires PHP: 7.3
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your eCommerce to a mobile app instantly and build customers for life! Analyze their behavior and drive repeat sales with targeted push notifications.

== Description ==

<h2>Turn One-time Shoppers into Reccuring Revenue</h2>

Connector to BeyondCart - SaaS product that transform your eCommerce to a mobile app instantly and build customers for life! Analyze their behavior and drive repeat sales with targeted push notifications.

<h3>Build customersfor life</h3>

Make users stick around and drive repeat purchases with a Mobile Shopping App and Customer Engagement Platform 

<h3>Boost your business with a Mobile Shopping App</h3>

Engage shoppers where they’re most likely to convert - their phone. Offer a personalized shopping experience that keep cusomers ready to buy.

Offer users an ultimate experience that help them find easily what they want wherever they are.
Your mobile shopping app is full with features that will retain your customers and will help you build community for a lifetime

<h3>Drive sustainable growth with Customer Engagment Platform</h3>

Use our customer engagement platform  to ultimate your targeting strategy and drive repeat sales with the power of push notifications.

While users interact with your mobile shopping app our customer engagement platform records their in-app behaviour.
The details of every session logged are used to form the isights you need to drive sales

<h3>Push notifications center</h3>

Drive sales and repeat purchases by sending data-driven push notifications based on customer in-app behaviour, preferences and purchase patterns.

<h3>Beyond Cart is super easy to integrate with your online store</h3>

✔ <strong>Our team of experts converts your store to a fully branded Android and iOS Shopping App</strong>
✔ <strong>We handle the app submission and publishing process, so there is nothing new to figure out</strong>
✔ <strong>After your app becomes available in the app stores we will support you to ensure the success of your project</strong>

<h3>Our website:</h3>
Any questions? Visit our website <a href="https://beyondcart.com/?utm_source=wordpress.org" target="_blank">beyondcart.com</a>

== Installation ==

1. Upload to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Apply plugin settings.

== Frequently Asked Questions ==

= Does the mobile app sync with my WooCommerce store? =

Yes, your app syncs directly with your WooCommerce store. Products, collections, inventory, pricing, images, and discounts automatically update in real-time.

= How much does BeyondCart cost? =

BeyondCart offers annual and monthly billing plans. Pricing varies depending on the plan you subscribe to; please review the plans on this page. Please note that all plans require Apple Developer ($99/year) and Google Play ($25, one-time fee) accounts in order to publish your app. Our specialists can help you get these accounts set up.

= Can we cancel at any time? =

Yes, of course! You can cancel at any time by emailing help@beyondcart.com

== External Services ==

This plugin relies on 3rd party services for its 'Sign in with Apple', 'Login with Google', and 'Login with Facebook' features:

= Sign in with Apple =
* Apple's authentication servers are contacted to fetch public keys for verifying JSON Web Tokens (JWT) when users sign in with their Apple IDs.
* Apple's authentication server URL: https://appleid.apple.com/auth/keys
* Apple's Privacy Policy: https://www.apple.com/legal/privacy/en-ww/
* Apple's Terms of Use: https://www.apple.com/legal/internet-services/terms/site.html

= Login with Google =
* Google's authentication servers are contacted when users sign in with their Google accounts.
* Google API Console: https://console.developers.google.com/
* Google's Privacy Policy: https://policies.google.com/privacy
* Google's Terms of Service: https://policies.google.com/terms

= Login with Facebook =
* Facebook's authentication servers are contacted when users sign in with their Facebook accounts.
* Facebook for Developers: https://developers.facebook.com/
* Facebook's Data Policy: https://www.facebook.com/policy.php
* Facebook's Terms of Service: https://www.facebook.com/terms.php

== Changelog ==
=2.0.1=
* Fix - Fixed not able to transfer cart to the webview

=2.0.0=
* Fix - Fixed problem on Validate Cart that returns error on mobile app only coupons.
* Refactor - Proxy endpoints

=1.8=
* Feature - Timestamp added to the webviews checkout link to prevent caching when user is logged in
* Feature - Keep the users logged when migrate app to newest version
* Fix - Fatal Error fixed on WC Coupon class in validate cart endpoint
* Fix - Added images on items in add discount endpoint
* Refactor - Cart Items Data response refactored and it returns the same fields on get cart, add/remove coupon

=1.7.7=
* Feature - Endpoint to check cart items if are still instock before proceeding to webview checkout endpoint

=1.7.6=
* Fix - Security Fix

=1.7.5=
* Fix - Smartbanner fixes

=1.7.4=
* Fix - Smartbanner fixes and do not show on Checkout

=1.7.3=
* Fix - Fixed Warnings on Webview Checkout

=1.7.2=
* Feature - New Mobile App Banner, native banner for Safari (Smartbanner.js removed)
* Fix - Remove Visual Composer Tags from Post description

=1.7.1=
* Fix - Points are now working with our own custom class, instead of YITH Points & Rewards
* Fix - Warning fixed in Admin/Coupons
* Feature - Sales Channel are now shown in Admin Orders screen, when the store is using HPOS feature
* Fix - Fixed Mobile App flag (post_meta) not stored in DB when the store is using HPOS feature

=1.7.0=
* Fix - Category App Image - small fix when there is a thumb set as well as app image
* Fix - In New Woo get_applied_coupons returned sometimes as a object, this is now fixed to be always array
* Fix - Yith Points and Rewards bug with applying points
* Fix - Added coupons in the array in some requests in Cart
* Fix - Web Tracking - cookies fix

=1.6.3=
* Fix - Fixed warnings in Woo v8.4.0 when calling the following endpoint: wp-json/wc/v3/shipping/zones/2/methods

=1.6.2=
* Fix - Fixed bug with variation products from Woocommerce v8.4.0 it adds slug to attributes response, but it breaks our app (it's a temporary fix)

=1.6.1=
* Fix - Fixed wrong namespace (JWT) and now Apple Login is working fine.

=1.6=
* Fix - Beyondcart Session Handler fix to be able to create orders in Woocommerce v8.3.1
* Fix - Smartbanner higher z-index

=1.5.5=
* Fix - Changed JWT namespace to Beyondcart\Firebase\JWT due to conflicts with other plugins. Do not update firebase/php-jwt or run composer install!

=1.5.4=
* Feature - Added filter hooks to be able to modify API response in Cart object (get_cart, rm/add coupon) - beyondcart_app_cart_data, beyondcart_app_cart_data_apply_coupon, beyondcart_app_cart_data_remove_coupon
* Feature - Added filter hooks to be able to modify API response in Category object - beyondcart_modify_category_content
* Feature - Added WPML/Polylang support in Cart actions
* Feature - Added WPML/Polylang support in Webview Checkouts

=1.5.3=
* Fix - Added filter hooks to be able to modify API response in Cart object (get_cart, rm/add coupon)
* Fix - Added filter hook to be able to modify API response in Variation object (beyondcart_app_variations_data)
* Fix - Replaced WC() with global $woocommerce in Add to Cart method
* Fix - Removed commented lines add/delete filter woocommerce_package_rates from Cart class

=1.5.2=
* Fix - If Funnel Kits is used for checkout, When order is created via webview app add flag Mobile in admin (use another order meta field)

=1.5.1=
* Fix - If Funnel Kits is used for checkout, When order is created via webview app add flag Mobile in admin (use another order meta field)

=1.5=
* Fix - Bug with expired JWT token, causing deleting the old products in cart when adding new

=1.4.8=
* Feature - Loyalty Program (WooRewards Integration) endpoints
* Fix - Fixed js error when webtracking is not active

=1.4.7=
* Feature - When order is created via webview app add flag Mobile in admin.
* Fix - Fixed problem on native app with webview checkout where sometimes miss to add in orders Mobile app: yes

=1.4.6=
* Feature - Webtracking implemented.
* Fix - Product not on sale stays with 0% discount label when WooDiscountRules integration is active.

=1.4.5=
* Fix - Native cart to Webview Checkout works now
* Fix - cart-key in request replaced with cart_key (in webview checkout mode)
* Fix - Logout method returned HTML instead of json success true/false

=1.4.4=
* Bug - If CoCart Plugin is activated do not use our BeyondCartSession (will work only in webview)

=1.4.3=
* Fix - Clear cart endpoint is now successfully empty the cart.
* Fix - After refactored version Featured Image in Post API didn't work
* Fix - Added version file for BeyondCart backend to be able to check plugin's version

=1.4.2=
* Fix - Bug with OnSale Category when Flycart Woo Discount Prices Plugin Integration is active.
* Fix - Fix to get attributes in product inner by menu_order
* Fix - Improved the code that gets attributes data in product inner (saving a lot of unnecessary queries against the DB)
* Fix - WP Plugin Feedback - Data Must be Sanitized, Escaped, and Validated / Variables and options must be escaped when echo'd
* Refactor - Removed duplicated code in Nav class (SmartBanner)

=1.4.1=
* Fix - WP Plugin Feedback - Data Must be Sanitized, Escaped, and Validated
* Fix - WP Plugin Feedback - Variables and options must be escaped when echo'd

=1.4=
* Refactor - Major plugin refactoring. API endpoints and methods divided into different classes based on their purpose.
* Feature - Modify the default Woo API /wp-json/wc/v3/products to be used for filtering and everywhere in the app. Makes our custom endpoint getProductsWithFilter absolute.
* Refactor - Removed unused method woocommerce_rest_product_object_query that are modifying rest endpoint 
* Bug - Fixed bug relating using wheel coupon, when you reuse the coupon twice or more.

=1.3.5=
* Fix - Coupon apply bug (remove_product_cat_coupon_validation), caused due namespace issue 

=1.3.4=
* Fix - WP Plugin Feedback - Using CURL Instead of HTTP API
* Fix - WP Plugin Feedback - Undocumented use of a 3rd Party or external service
* Fix - WP Plugin Feedback - Using file_get_contents on remote files
* Fix - WP Plugin Feedback - Data Must be Sanitized, Escaped, and Validated
* Fix - WP Plugin Feedback - Variables and options must be escaped when echo'd
* Fix - WP Plugin Feedback - Unsafe SQL calls
* Feature - Make the plugin compatible with new Woocommerce feature HPOS - High-Performance order storage (COT)

=1.3.3=
* Feature - SmartBanner added as an option in the plugin to be able to show a popup with AppStore/GooglePlay App links
* Feature - Transform Visual Composer Tags From The Product Description Into HTML

=1.3.2=
* Feature - Ability to Filter By Custom Taxonomy like (product_collection, product_tag, etc)

=1.3.1=
* Feature - Added readme.txt, nav replaced to BeyondCart
* Fix - Refactored few method names

=1.3.0=
* Feature - Add Coupon delete used by for user

=1.2.4=
* Feature - Flycart Woo Discount Prices Plugin Integration - apply dynamic prices from the plugin to the products
* Feature - Flycart Woo Discount Prices Plugin Integration - use custom On Sale category and get all discount products from the plugin
* Fix - Deprecated function wc_get_min_max_price_meta_query() replaced with a code for filtering by price range.

=1.2.3=
* Fix - Cart Major Bug - When user is with Expired bearer Token replace the current cart product with another one.
* Fix - Critical error for calling non-static method as static. 
* Fix - Remove filtering by sales channel in orders
* Fix - Search do not show products with visibility hidden

=1.2.2=
* Fix - getProductsWithFilter - menu_order - Add order option

=1.2.1=
* Fix - Hide products with visiblity hidden from the categories in app

=1.2.0=
* Fix - BeyondCart Session Handler rework.
* Feature - Save guest cart to registered user

=1.1.9=
* Fix - Fixed filter by multiple attributes in filter products API endpoint
* Fix - Return stock status outofstock in filter products API endpoint
* Fix - Static function fix
* Fix - Warnings Division by Zero fixed in $sale_percentage_min/$sale_percentage_max

=1.1.8=
* Feature - Hourly cron to cache used terms by category in plugin's new table 'wp_grind_mobile_app_terms'
* Feature - API endpoint to get the cached terms

=1.1.6=
* Feature - Integration Mrejanet Speedy
* Feature - Integration Mrejanet Econt
* Feature - Custom category images

=1.1.5=
* Feature - Get products by filters, sortby
* Feature - Delete user endpoint

=1.1.4=
* Feature - Show/hide shipping methods in site and app

=1.1.3=
* Change - Change woocommerceRestFilters to post_modified_gmt

=1.1.2=
* Fix - Get allowed countries instead of all countries

=1.1.1=
* add Feature - Woocommerce Rest Filters for get parameter modified_after

=1.0.7=
* Fix - Style enqueue
* Fix - Hide Coupon code in webview checkout
* Fix - App Only coupons in webview checkouts
* Feature - Return available payment methods for selected shipping method
* Feature - Get checkout fields

=1.0.6=
* Fix - Add variation price min and max to calculate sale percentages and price ranges

=1.0.5=
* Fix - Add Cart variation attributes label and term name
* Feature - Add app only coupons
* Fix - Sales channel in admin
* Fix - Add customer to order on checkout 

=1.0.4=
* Fix - Add missing endpoints in Product.php.
* Fix - Renamed endpoint /variable to /variations in Product.php

=1.0.3=
* Feature - Add save mobile app configs from Admin Backoffice

=1.0.2=
* Fix - On change quantity in app, return whole cart and not just the total
* Feature - Add third party App/Api keys from the plugins settings (firebase, fb, onesignal)
* Feature - Admin Backoffice - new field in Orders listing screen if order is from App or Website
* Feature - Web Checkout - hidden field to check if the order is from App or Website

=1.0.1=
* Feature - Cart Requests, Order Review, Checkout, Shipping
* Feature - Stripe Payment Intent