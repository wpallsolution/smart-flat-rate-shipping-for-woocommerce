=== Smart Flat Rate Shipping for WooCommerce ===
Contributors: hplugin
Tags: woocommerce, shipping, flat rate, free shipping, shipping rules
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flat-rate shipping methods with per-item fees, free-shipping thresholds, product, category and location conditions, plus a simulator.

== Description ==

**[Read the documentation](https://docs.wpallsolution.com/#/collection/sfrs-plugin)** – setup, conditions, the simulator and troubleshooting. Need help? **[Contact support](https://support.wpallsolution.com/)**.

Smart Flat Rate Shipping adds one shipping method to WooCommerce that offers as many flat-rate options as you configure. Each option has a fee, optional conditions, and a priority.

**What you can do**

* Fixed fee, optional per-item fee, optional "free shipping from" subtotal.
* Conditions: cart subtotal, cart quantity, products, product categories, destination country, state and postcode.
* AND inside a group, OR between groups, with a readable summary in the list.
* Shipping simulator: test a sample cart and destination without a real cart, order, payment or stock change. It uses the same pricing engine as checkout.
* Diagnostics: environment checks and an optional, bounded log that explains why a method matched or not.
* Works with WooCommerce shipping zones, taxes, multiple packages and native shipping methods. It never hides or changes other methods.
* Documentation: [step-by-step guides](https://docs.wpallsolution.com/#/collection/sfrs-plugin) for every screen and condition.

**How rates are calculated**

Cost = base fee + per-item fee x chargeable units. If the eligible subtotal reaches the free-shipping threshold, the cost is 0.

* Subtotals exclude tax. Coupon discounts are deducted by default (a setting lets you ignore them).
* Eligible items: for the first matching condition group, the items selected by its "contains any of" product/category conditions; every item if the group has none.
* Per-item fees count all cart units or only eligible units, as chosen per method.
* Methods are independent: every matching method is offered, none hides another, and prices are never added together.
* Location conditions need the customer's address. Until a value is entered, a location condition does not match (also "is not one of"), so a rate is never shown to the wrong place.
* Postcodes support exact values, a trailing wildcard (380*) and numeric ranges (380001...380060). Spaces, dashes and case are ignored.

**Not included (yet)**

Weight, shipping-class and coupon conditions, formulas, scheduling, import/export, splitting a cost between products. Some of these (weight, shipping class, coupon, role and day/time conditions, tiered rates, import/export) are provided by the separate Smart Flat Rate Shipping Pro add-on. The free plugin makes no external requests and sends no data anywhere.

== For developers ==

Add-ons can extend the plugin with these hooks: filters `sfrs_condition_types`, `sfrs_evaluate_condition`, `sfrs_sanitize_condition`, `sfrs_draft_condition`, `sfrs_describe_condition`, `sfrs_context`, `sfrs_pricing_adjust`, `sfrs_rule_defaults`, `sfrs_sanitize_request`, `sfrs_price_summary`, `sfrs_admin_tabs`; actions `sfrs_condition_value_fields`, `sfrs_form_pricing_rows`, `sfrs_simulator_fields` and `sfrs_render_tab_{tab}`.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin to `/wp-content/plugins/smart-flat-rate-shipping-for-woocommerce` or install it from the Plugins screen, then activate it.
3. Go to WooCommerce > Smart Flat Rate and add a method.
4. Go to WooCommerce > Settings > Shipping, open a shipping zone and add the "Smart Flat Rate" method once. Customers in that zone can now receive your methods.

Need more detail? See the [documentation](https://docs.wpallsolution.com/#/collection/sfrs-plugin).

== Usage examples ==

**Standard shipping, free over 1000**
Base fee 80, free shipping from 1000. Carts with an eligible subtotal of 1000 or more get 0.

**Per-item shipping**
Base fee 40, per-item fee 10. Three items cost 40 + 3 x 10 = 70.

**Express for one state only**
Base fee 150, condition: Destination state is one of IN:GJ.

**Cheaper shipping for a category**
Condition: Product categories contains any of "Books", per-item fee 5, "Only units of eligible products".

**Local delivery by postcode**
Condition: Destination postcode is one of 380001...380060, 3900*.

== Frequently Asked Questions ==

= Customers see no shipping option. =
Add the "Smart Flat Rate" method to a shipping zone covering the customer's address, make sure at least one method is enabled, and check Diagnostics & help.

= Can I add the method to several zones? =
Yes. Each zone should contain it once. A second copy in the same zone is ignored to avoid duplicate options.

= Do the cart and checkout blocks work? =
The rates are produced by a standard WooCommerce shipping method, and the Store API used by the blocks returned them in testing with WooCommerce 11.2.1. The optional customer description is shown only in the classic cart and checkout.

= Are taxes supported? =
Each method can be taxable or not. Taxable methods use your WooCommerce shipping tax settings. Subtotal comparisons always exclude tax.

= What happens when I deactivate or delete the plugin? =
Deactivation keeps everything. Deleting the plugin removes data only if "Delete all methods and settings when the plugin is deleted" is ticked under Settings. WooCommerce data is never touched.

= Does it collect data? =
No. There is no telemetry and no external request. The optional diagnostics log stores only country/state, totals and per-method results for the last 20 evaluations, and is off by default.

= How can I get support? =
Use the [contact page](https://support.wpallsolution.com/) if you need help, want to request a new feature or need a customization. Please include your WordPress, WooCommerce and plugin versions.

= Where is the documentation? =
The [documentation](https://docs.wpallsolution.com/#/collection/sfrs-plugin) covers installation, methods and pricing, conditions, the simulator and troubleshooting.

== Screenshots ==

1. Shipping methods list with amounts, status, priority and a readable condition summary.
2. Add/edit method with pricing options and AND/OR condition groups.
3. Test shipping (simulator) result showing which methods match and why.
4. Diagnostics and help with environment checks.

== Changelog ==

= 1.0.1 =
* Fix: the method description (for example "Delivered in 2 business days") now also shows in the Checkout Block, not only in the classic checkout.
* Declared compatibility with the Cart and Checkout Blocks (tested with the Checkout Block on WooCommerce 11.2).

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.1 =
Method descriptions now show in the Checkout Block.

= 1.0.0 =
First release.
