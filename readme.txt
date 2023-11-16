=== Ingeni Woo Purchase Limits ===

Contributors: Bruce McKinnon
Tags: woocommerce
Requires at least: 6.3
Tested up to: 6.3.2
Stable tag: 2023.03

Used in conjunction with Woocommerce. Provides the ability to limit item purchases to specific user roles user over a period of time. Other user roles may either be blocked from purchase, or allowed to purchase without limitation.

Works with both Simple and Variable products.



== Description ==

* - Used in conjunction with Woocommerce. 

* - Limit number of items that can be purchased by a group of users, over a period of time.




== Installation ==

1. Upload the 'ingeni-woo-purchase-limit’ folder to the '/wp-content/plugins/' directory.

2. Activate the plugin through the 'Plugins' menu in WordPress.



== Frequently Asked Questions ==

Q - Where are the purchase limit settings?

A - For Simple products, the settings are in the Product Data > Inventory tab.
For Variable products, the settings are in the individual Product Data > Variations > Variation panels.


Q - Can you have different purchase limits for individual variations of Variable products?

A - Yes you can - set the rules with the individual variation properties panel.


Q - How do I disable the purchase limits for a specific product?

A - Set the Max Qty to -1 (minus one). This is the default setting.


Q - How can I block a User Role from purchasing a product?

A - Set the Max Qty to 0 (zero) and then set either the required Users Role, or set the role to 'All roles'.



== Changelog ==

v2023.01 - Initial version.

v2023.02 - Now checks for 'completed', 'on hold' and 'processing' order statuses when counting past orders.

v2023.03 - count_past_orders_by_product() - Fixed order status strings - full list at https://woocommerce.wp-a2z.org/oik_api/wc_get_order_statuses/
