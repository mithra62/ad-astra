# mithra62/Shop — E-Commerce SaaS Architecture Plan

> **Platform:** laravel-base (Laravel 12, PHP 8.2+)
> **Namespace:** `mithra62\Shop\` (already wired in `composer.json`)
> **Author / Lead:** Eric
> **Date:** 2026-04-30
> **Inspiration:** CartThrob for ExpressionEngine

---

## Table of Contents

1. [Platform Assessment](#1-platform-assessment)
2. [Module Structure — `mithra62/Shop`](#2-module-structure)
3. [New Entry Types](#3-new-entry-types)
4. [New Custom Field Types](#4-new-custom-field-types)
5. [The Cart Layer](#5-the-cart-layer)
6. [Order System](#6-order-system)
7. [Product Types](#7-product-types)
8. [Subscriptions](#8-subscriptions)
9. [Discounts & Coupons](#9-discounts--coupons)
10. [Payment Gateway Abstraction](#10-payment-gateway-abstraction)
11. [Shipping Abstraction](#11-shipping-abstraction)
12. [Tax Engine](#12-tax-engine)
13. [Digital Product Delivery](#13-digital-product-delivery)
14. [Tenancy Considerations](#14-tenancy-considerations)
15. [Database Schema Additions](#15-database-schema-additions)
16. [Implementation Roadmap](#16-implementation-roadmap)
17. [Open Questions & Concerns](#17-open-questions--concerns)

---

## 1. Platform Assessment

The existing `laravel-base` platform is an exceptionally strong backbone for this. Having studied the codebase, here is what you get for free — and what you need to build.

### What the platform already provides

**Entry / EntryType system** maps almost perfectly to CartThrob's Channel Entry model. Every commerce object — Products, Orders, Subscriptions, Coupons, Discount Rules — becomes an Entry in a purpose-built EntryGroup with a specialised EntryType class. The `AbstractEntryType` lifecycle hooks (`beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `validate`) give you a clean, idiomatic place to enforce business rules without polluting controllers or repositories.

**Field system** gives you typed, polymorphic storage via `field_values` (text, integer, float, date, boolean, JSON). The `Relationship` field type stores associations in `entry_relationships`, which is exactly right for linking OrderItems to Products, Products to TaxClasses, etc. The `value_json` column in `field_values` is the natural home for the new Matrix/Grid field type you need for product attributes and variants.

**ProductEntryType already exists.** It enforces price consistency (sale price must be less than price), requires a SKU before publishing, and auto-applies an "out-of-stock" status when `stock_quantity` reaches zero. This is an excellent starting point but needs to grow substantially.

**Status Groups** let you model the full lifecycle of Orders, Subscriptions, and Products with custom statuses (e.g., `pending`, `paid`, `processing`, `shipped`, `refunded`, `cancelled` for orders) without any schema changes.

**Tenancy plan** (`TenantPlan.md`) is already thoughtfully designed with shared-database + `tenant_id` scoping. Every new shop table needs the same `tenant_id` treatment.

**Spatie MediaLibrary** is installed — crucial for product images and digital file storage.

**Spatie Permissions** — roles and permissions are ready; you'll add shop-specific permissions (`shop.manage_products`, `shop.process_orders`, etc.).

**`mithra62\Shop\`** is already registered as a PSR-4 autoload namespace pointing at `mithra62/Shop` in the project root. This is your shop module home.

### Key gaps to fill

- No cart/session persistence layer
- No payment processing abstraction
- No shipping method abstraction
- No tax rules engine
- No discount/coupon engine
- No order number generation or order line-item storage
- No subscription billing loop
- No secure digital download delivery
- No Matrix/Grid field type
- No Money/Price field type
- No `Select` / `Dropdown` field type (needed everywhere)

---

## 2. Module Structure

Everything commerce-related lives under `mithra62/Shop/` and is autoloaded as `mithra62\Shop\`. This keeps it decoupled from the core CMS — it can be extracted into its own Composer package later or offered as a tenant-installable add-on.

```
mithra72/Shop/
├── Cart/
│   ├── CartManager.php           # Facade entry point
│   ├── CartItem.php              # Value object
│   ├── CartRepository.php        # Session + DB persistence
│   └── CartCalculator.php        # Totals, taxes, discounts
│
├── Checkout/
│   ├── CheckoutService.php
│   └── CheckoutPipeline.php      # Laravel pipeline for checkout steps
│
├── Discounts/
│   ├── AbstractDiscount.php
│   ├── DiscountEngine.php
│   ├── Types/
│   │   ├── PercentageDiscount.php
│   │   ├── FixedDiscount.php
│   │   ├── FreeShippingDiscount.php
│   │   ├── BuyXGetYDiscount.php
│   │   └── VolumeDiscount.php
│   └── Rules/
│       ├── AbstractRule.php
│       ├── MinimumOrderAmountRule.php
│       ├── ProductRule.php
│       ├── CategoryRule.php
│       └── CustomerGroupRule.php
│
├── EntryTypes/
│   ├── OrderEntryType.php
│   ├── OrderItemEntryType.php
│   ├── SubscriptionEntryType.php
│   ├── DiscountEntryType.php
│   ├── CouponEntryType.php
│   ├── ShippingZoneEntryType.php
│   └── TaxRateEntryType.php
│
├── Field/
│   ├── Types/
│   │   ├── Matrix.php            # Grid/repeater field
│   │   ├── Money.php             # Integer cents storage, formatted display
│   │   ├── Select.php            # Dropdown with configurable options
│   │   ├── ProductAttributes.php # Specialised Matrix for variant axes
│   │   └── PriceModifier.php     # CartThrob-style option price override
│
├── Gateway/
│   ├── AbstractPaymentGateway.php
│   ├── GatewayManager.php        # Registry + factory
│   ├── PaymentResult.php         # Value object
│   ├── Omnipay/
│   │   └── OmnipayGateway.php    # Thin wrapper around Omnipay
│   └── Contracts/
│       ├── CanCharge.php
│       ├── CanRefund.php
│       ├── CanSubscribe.php
│       └── CanSavePaymentMethod.php
│
├── Order/
│   ├── OrderService.php
│   ├── OrderNumberGenerator.php
│   ├── OrderStateMachine.php
│   └── Pipelines/
│       └── CreateOrderPipeline.php
│
├── Shipping/
│   ├── AbstractShippingMethod.php
│   ├── ShippingManager.php
│   ├── ShippingQuote.php         # Value object
│   ├── ShippingAddress.php       # Value object
│   └── Methods/
│       ├── FlatRateShipping.php
│       ├── FreeShipping.php
│       ├── WeightBasedShipping.php
│       └── ExternalRateShipping.php  # UPS/FedEx/USPS via API
│
├── Tax/
│   ├── TaxEngine.php
│   ├── TaxRate.php               # Value object
│   ├── TaxableItem.php           # Interface
│   └── Resolvers/
│       ├── ByCountryResolver.php
│       ├── ByStateResolver.php
│       └── VatResolver.php
│
├── Subscription/
│   ├── SubscriptionService.php
│   ├── BillingCycle.php
│   └── SubscriptionState.php
│
├── Download/
│   ├── DownloadToken.php
│   ├── DownloadService.php
│   └── DownloadLimiter.php
│
└── Providers/
    └── ShopServiceProvider.php   # Registers everything; boots field types, entry types
```

The `ShopServiceProvider` should be auto-discovered via Laravel's package discovery or explicitly added to `bootstrap/providers.php`. It registers the `GatewayManager`, `CartManager`, and `TaxEngine` as singletons, seeds default statuses and entry group/type definitions on first boot, and registers all custom field types with the `field_types` table.

---

## 3. New Entry Types

All commerce data is modelled as Entries. This is the CartThrob philosophy and it works extremely well here because you inherit editing UI, statuses, relationships, categories, and the field system for free.

### 3.1 ProductEntryType (extend existing)

The existing `ProductEntryType` handles price validation and stock status, but needs significant growth.

**Required fields (via FieldLayout):**

| Handle | Field Type | Notes |
|---|---|---|
| `sku` | Text | Required before publish (already enforced) |
| `price` | Money | Integer cents; handles currency display |
| `sale_price` | Money | Must be < price when set |
| `stock_quantity` | Number (int) | Tracks physical inventory |
| `weight` | Number (float) | Grams or oz — configure per tenant |
| `dimensions` | Matrix | Rows: length, width, height |
| `product_type` | Select | `simple`, `physical`, `digital`, `subscription` |
| `tax_class` | Relationship | → TaxClass entry |
| `shipping_class` | Relationship | → ShippingClass entry |
| `product_images` | Media (Spatie) | Multiple images |
| `download_file` | Media (Spatie) | For digital products |
| `download_limit` | Number (int) | Max downloads; 0 = unlimited |
| `download_expiry_days` | Number (int) | Days after purchase; 0 = no expiry |
| `attributes` | ProductAttributes | Matrix variant axes (e.g., Size: S/M/L, Color: Red/Blue) |
| `subscription_plan` | Relationship | → SubscriptionPlan entry (if type = subscription) |

**Lifecycle concerns:**

`beforeCreate`/`beforeUpdate` should build a variant table from `attributes` if present, generating child entries (or JSON rows in the Matrix field) for each variant combination. This is the most complex part of product modelling — see Section 7.

### 3.2 OrderEntryType

Orders are Entries. The `title` field is the human-readable order number (e.g., `ORD-2026-00042`). Status transitions are the core workflow.

**Status group:** `order_statuses`

Recommended statuses: `pending_payment`, `paid`, `processing`, `partially_shipped`, `shipped`, `delivered`, `refunded`, `partially_refunded`, `cancelled`, `on_hold`.

**Required fields:**

| Handle | Field Type | Notes |
|---|---|---|
| `order_number` | Text | Formatted; generated on create |
| `customer_id` | Relationship | → User |
| `billing_address` | Matrix | Name, address lines, city, state, zip, country |
| `shipping_address` | Matrix | Same schema as billing |
| `line_items` | Relationship | → OrderItem entries |
| `subtotal` | Money | Before discounts and tax |
| `discount_total` | Money | Sum of all applied discounts |
| `shipping_total` | Money | Chosen shipping method cost |
| `tax_total` | Money | Calculated tax |
| `grand_total` | Money | Final charge amount |
| `currency` | Text | ISO 4217 (e.g., `USD`) |
| `gateway_handle` | Text | Which gateway processed this |
| `transaction_id` | Text | Gateway reference |
| `payment_method_last4` | Text | For display |
| `coupon_codes` | Text | Comma-separated applied coupons |
| `customer_notes` | Textarea | |
| `internal_notes` | Textarea | Staff only |
| `ip_address` | Text | For fraud detection |
| `metadata` | value_json | Arbitrary gateway/webhook data |

**`OrderEntryType` lifecycle concerns:**

- `beforeCreate`: Generate `order_number` via `OrderNumberGenerator`; freeze pricing (copy product prices into line items so price changes don't mutate historical orders).
- `afterCreate`: Fire `OrderCreated` event; trigger confirmation email; decrement product `stock_quantity`.
- `beforeUpdate`: If status changes to `refunded`, trigger refund flow via gateway.
- `afterUpdate`: Fire `OrderStatusChanged` event; update subscription state if applicable.
- `validate`: Require all address fields when status moves to `processing` or beyond.

### 3.3 OrderItemEntryType

Each line item in an order is its own Entry, related back to the Order via the `line_items` Relationship field. This feels slightly heavyweight but gives you a full editing UI, searchability, and custom fields on line items — exactly what CartThrob's order item channel provided.

**Required fields:**

| Handle | Field Type | Notes |
|---|---|---|
| `product_id` | Relationship | → Product entry (snapshot reference) |
| `product_title` | Text | Snapshot of title at purchase time |
| `sku` | Text | Snapshot |
| `unit_price` | Money | Frozen at purchase |
| `quantity` | Number (int) | |
| `line_total` | Money | unit_price × quantity |
| `options` | value_json | Selected variant/attribute options |
| `tax_rate` | Number (float) | Applied rate snapshot |
| `tax_amount` | Money | |
| `download_token` | Text | For digital products |
| `fulfillment_status` | Select | `pending`, `fulfilled`, `refunded` |

> **Concern:** If order line items as full Entries feels too heavy for your use case, an alternative is to store them as a JSON blob in a `line_items` Matrix field on the Order entry itself. This is simpler to query but loses the per-item editability. Recommend starting with full Entries — you can always denormalise later.

### 3.4 SubscriptionPlanEntryType

Plans define the recurring billing schedule. They are not subscriptions themselves — they are templates.

**Required fields:**

| Handle | Field Type | Notes |
|---|---|---|
| `billing_interval` | Select | `daily`, `weekly`, `monthly`, `quarterly`, `annual` |
| `billing_interval_count` | Number (int) | e.g., `3` for every 3 months |
| `trial_days` | Number (int) | Free trial period |
| `price` | Money | Per-cycle price |
| `setup_fee` | Money | One-time charge on signup |
| `max_cycles` | Number (int) | 0 = until cancelled |
| `gateway_plan_id` | Text | Stripe Price ID or similar |

### 3.5 SubscriptionEntryType

One entry per active customer subscription.

**Required fields:**

| Handle | Field Type | Notes |
|---|---|---|
| `customer_id` | Relationship | → User |
| `plan_id` | Relationship | → SubscriptionPlan |
| `product_id` | Relationship | → Product |
| `gateway_subscription_id` | Text | Stripe Subscription ID, etc. |
| `current_period_start` | Date | |
| `current_period_end` | Date | |
| `trial_ends_at` | Date | Nullable |
| `cancelled_at` | Date | Nullable |
| `ends_at` | Date | When access expires post-cancel |
| `cycle_count` | Number (int) | How many billing cycles completed |
| `last_payment_id` | Relationship | → Order (the renewal order) |

**Statuses:** `trialing`, `active`, `past_due`, `cancelled`, `expired`

### 3.6 DiscountEntryType

Discounts are applied automatically when cart conditions are met. No code required.

**Required fields:**

| Handle | Field Type | Notes |
|---|---|---|
| `discount_type` | Select | `percentage`, `fixed_amount`, `free_shipping`, `buy_x_get_y` |
| `discount_value` | Number (float) | Amount or percentage |
| `applies_to` | Select | `order`, `specific_products`, `specific_categories` |
| `product_ids` | Relationship | → Product entries (if applies_to = specific_products) |
| `category_ids` | Relationship | → Categories |
| `minimum_order_amount` | Money | Threshold to activate |
| `minimum_quantity` | Number (int) | Cart item quantity threshold |
| `customer_group` | Select | `all`, `registered`, `wholesale` |
| `usage_limit` | Number (int) | Total redemptions allowed; 0 = unlimited |
| `usage_count` | Number (int) | Auto-incremented |
| `starts_at` | Date | |
| `expires_at` | Date | Nullable |
| `priority` | Number (int) | Higher = evaluated first |
| `stackable` | Boolean | Can stack with other discounts? |

### 3.7 CouponEntryType

Extends discount logic but requires a customer-entered code.

**Required fields:** All Discount fields, plus:

| Handle | Field Type | Notes |
|---|---|---|
| `code` | Text | Unique per tenant; uppercase enforced |
| `usage_limit_per_customer` | Number (int) | Per-customer cap |
| `first_time_only` | Boolean | Only for new customers |

> Both Discount and Coupon entries share a `DiscountSettings` custom field type (similar to CartThrob's approach) that bundles all the rule configuration into a single structured JSON field. The `DiscountEngine` reads this at runtime.

### 3.8 TaxRateEntryType

| Handle | Field Type | Notes |
|---|---|---|
| `rate` | Number (float) | e.g., `8.875` for 8.875% |
| `country` | Select | ISO 3166-1 alpha-2 |
| `state_province` | Text | Optional; state/province code |
| `tax_class` | Relationship | → TaxClass entry |
| `is_compound` | Boolean | Compound tax (applied on top of other taxes) |
| `applies_to_shipping` | Boolean | |
| `label` | Text | e.g., "NY Sales Tax" |
| `priority` | Number (int) | Evaluation order |

### 3.9 TaxClassEntryType

Simple taxonomy of tax classes (e.g., `standard`, `reduced`, `zero`, `exempt`, `digital_goods`). Products reference a TaxClass; TaxRates are scoped to a TaxClass.

### 3.10 ShippingZoneEntryType / ShippingRateEntryType

A ShippingZone groups countries/regions; ShippingRates attach methods and prices to zones. Both are Entries, giving the tenant a full editing UI.

**ShippingZone fields:** `name`, `countries` (JSON array), `states` (JSON array), `zip_codes` (Textarea).

**ShippingRate fields:** `zone` (Relationship → ShippingZone), `method_handle` (Select — from registered shipping methods), `method_label` (Text), `min_order_amount` (Money), `max_order_amount` (Money), `min_weight` (Number), `max_weight` (Number), `price` (Money), `is_free` (Boolean), `method_settings` (value_json — passed to the method class).

---

## 4. New Custom Field Types

These are concrete classes under `mithra62\Shop\Field\Types\`, registered via `ShopServiceProvider`. Each is inserted as a row into the `field_types` table and becomes available in the field builder UI.

### 4.1 Matrix (Grid/Repeater)

This is the most powerful and most needed field type. Stores `value_json`. The JSON schema is an ordered array of row objects, each keyed by column handle.

```json
[
  { "option_name": "Small", "price_modifier": 0, "sku_suffix": "-S", "inventory": 50 },
  { "option_name": "Medium", "price_modifier": 200, "sku_suffix": "-M", "inventory": 30 },
  { "option_name": "Large", "price_modifier": 400, "sku_suffix": "-L", "inventory": 15 }
]
```

**Field settings (stored in `fields.settings`):**
```json
{
  "columns": [
    { "handle": "option_name", "label": "Option Name", "type": "text", "required": true },
    { "handle": "price_modifier", "label": "Price Modifier (cents)", "type": "integer" },
    { "handle": "sku_suffix", "label": "SKU Suffix", "type": "text" },
    { "handle": "inventory", "label": "Inventory", "type": "integer" }
  ],
  "min_rows": 0,
  "max_rows": 0,
  "add_button_label": "Add Row"
}
```

`storageColumn()` returns `value_json`. The `cast()` method returns a `MatrixValue` collection object with helper methods like `->rows()`, `->sum('price_modifier')`, `->where('sku_suffix', '-M')`.

### 4.2 ProductAttributes

A specialised Matrix for defining variant axes. Each row is a variant dimension (e.g., "Size"), and the JSON stores the axis options as a sub-array.

```json
[
  {
    "axis": "Size",
    "options": ["XS", "S", "M", "L", "XL", "2XL"],
    "type": "select"
  },
  {
    "axis": "Color",
    "options": ["Red", "Blue", "Black", "White"],
    "type": "swatch"
  }
]
```

The `ProductEntryType` reads this field in `afterCreate`/`afterUpdate` to generate or sync variant entries (or rows in a `variants` Matrix field on the same entry). The combinatorial explosion (Size × Color = up to 24 variants) needs lazy-generation — only create variant rows when a customer first selects that combination, or eagerly at product creation with a UI "generate variants" button.

### 4.3 Money

Stores value in **integer cents** in `value_integer`. Never uses floats for currency. The `cast()` method returns a `MoneyValue` object that formats for display (`$12.99`), handles currency symbol lookup, and supports arithmetic without floating-point drift.

**Field settings:**
```json
{
  "currency": "USD",
  "allow_negative": false,
  "min": 0,
  "max": null
}
```

Per-tenant, the currency is resolved from tenant settings if not explicitly set on the field, so you can drive currency from `tenant.settings.shop.currency`.

### 4.4 Select (Dropdown)

Simple but missing from the current field type roster and needed pervasively across all commerce entry types.

**Field settings:**
```json
{
  "options": [
    { "value": "physical", "label": "Physical Product" },
    { "value": "digital", "label": "Digital Download" },
    { "value": "subscription", "label": "Subscription" }
  ],
  "multiple": false,
  "allow_blank": true
}
```

Stores `value_text` (single) or `value_json` (multiple). The `cast()` method returns the label for display, the value for logic.

### 4.5 PriceModifier

A CartThrob-inspired field that stores an array of option/price pairs — the original CartThrob Price Modifier field. Used for simple "add-on" pricing without full variant generation.

```json
[
  { "label": "Add Gift Wrapping", "value": "gift_wrap", "price": 500, "sku": "GIFT" },
  { "label": "Rush Processing (+$10)", "value": "rush", "price": 1000 }
]
```

This is distinct from `ProductAttributes` (which generates variants) — `PriceModifier` is for ancillary choices a customer makes at add-to-cart time that modify the line item price without creating separate inventory.

### 4.6 DiscountSettings

A structured field that bundles all discount rule configuration into a single JSON value. Behaves like a mini-form-within-a-form in the UI. Stored in `value_json`. The `DiscountEngine` reads this field to determine how to apply a discount.

```json
{
  "type": "percentage",
  "value": 15.0,
  "conditions": {
    "minimum_order": 5000,
    "applies_to": "order",
    "customer_group": "all",
    "stackable": false,
    "priority": 10,
    "date_range": { "start": "2026-11-28", "end": "2026-11-30" }
  }
}
```

---

## 5. The Cart Layer

The cart is the most session-sensitive part of the system. It must work for guests (session-backed), authenticated users (database-backed), and merge correctly on login.

### 5.1 CartItem (Value Object)

```php
class CartItem
{
    public string $id;            // UUID — stable across merges
    public int    $entryId;       // Product entry ID
    public int    $quantity;
    public int    $unitPrice;     // Cents, frozen at add-to-cart
    public array  $options;       // Selected attribute/modifier values
    public ?int   $subscriptionPlanId;
    public array  $metadata;      // Arbitrary — coupon attribution, etc.
}
```

### 5.2 CartRepository

Two concrete implementations behind one interface:

**`SessionCartRepository`** — stores serialised cart items in Laravel's session. Fast, zero DB cost, suitable for guests.

**`DatabaseCartRepository`** — stores items in a `carts` + `cart_items` table (see Section 15). Required for: logged-in users, abandoned cart recovery, cross-device carts, analytics.

**`HybridCartRepository`** — the real implementation used in production. Checks auth state: if guest, delegates to session; if authenticated, delegates to DB. On login, merges the session cart into the DB cart with `mergeGuestCart(string $sessionId, int $userId)`.

### 5.3 CartManager

The primary service. Registered as a singleton in `ShopServiceProvider`.

```php
interface CartManagerContract
{
    public function add(int $productId, int $qty = 1, array $options = []): CartItem;
    public function update(string $itemId, int $qty): CartItem;
    public function remove(string $itemId): void;
    public function clear(): void;
    public function applyCoupon(string $code): void;
    public function removeCoupon(string $code): void;
    public function items(): Collection;
    public function totals(): CartTotals;       // Subtotal, discounts, shipping, tax, grand total
    public function isEmpty(): bool;
    public function count(): int;              // Total item count (respects quantity)
}
```

### 5.4 CartCalculator

Computes the `CartTotals` value object. The calculation pipeline is:

1. Sum `unit_price × quantity` for all items → **subtotal**
2. Pass cart through `DiscountEngine` → **discount_total** (automatic discounts only)
3. Apply any coupon codes → **coupon_total**
4. Look up chosen shipping method and zone → **shipping_total**
5. Pass `(subtotal - discounts + shipping)` through `TaxEngine` → **tax_total**
6. Sum everything → **grand_total**

This pipeline must be deterministic and side-effect-free — the same inputs always produce the same outputs. Critically, it must be called both in the cart view (for display) and again at checkout (to prevent price manipulation between cart and order creation).

### 5.5 Tenancy

When multi-tenancy is active, the `CartRepository` scopes all DB queries to `tenant_id`. Session carts use a namespaced session key: `shop.{tenant_id}.cart`.

---

## 6. Order System

### 6.1 Order Creation Flow (Checkout Pipeline)

Using Laravel's `Pipeline` to make the checkout process composable and testable:

```
ValidateCartNotEmpty
→ ValidateInventory           (decrement reservations)
→ ApplyDiscountsAndCoupons
→ ResolveShippingMethod
→ CalculateTax
→ FreezeLineTotals
→ ProcessPayment              (calls GatewayManager)
→ CreateOrderEntry            (writes Entry + OrderItems)
→ DecrementInventory          (commit stock reduction)
→ SendOrderConfirmation
→ ClearCart
→ HandleDigitalDelivery       (if any digital items)
→ HandleSubscriptionActivation (if any subscription items)
```

Each pipe is a class implementing `handle(CheckoutContext $context, Closure $next)`. `CheckoutContext` is a mutable value object carrying the cart, customer, addresses, chosen shipping, gateway, payment token, and the resulting order. If any pipe throws, the pipeline halts, inventory reservations are released, and the payment is voided.

### 6.2 OrderNumberGenerator

Generates human-readable, sequential, tenant-scoped order numbers. Default format: `ORD-{YEAR}-{SEQUENCE}` (e.g., `ORD-2026-00001`). The sequence is stored in a `sequences` table (or Redis for high-volume tenants) and is atomic to prevent duplicate numbers under concurrent checkout.

### 6.3 OrderStateMachine

Status transitions are guarded. Not every status is reachable from every other status. The state machine is defined as a map of `[current_status => [allowed_next_statuses]]` and enforced in `OrderEntryType::validate()`.

```
pending_payment → paid, cancelled
paid            → processing, refunded, cancelled
processing      → shipped, partially_shipped, on_hold
shipped         → delivered, partially_refunded
delivered       → refunded, partially_refunded
```

Transitions fire domain events: `OrderPaid`, `OrderShipped`, `OrderRefunded`, etc. These events are hookable by other modules (subscriptions, loyalty points, analytics).

### 6.4 Refunds

Partial and full refunds are supported. A refund is recorded by creating a `Refund` entry (or a signed `Money` transaction record) linked to the order, then calling `$gateway->refund($transactionId, $amount)`. The order status transitions to `refunded` or `partially_refunded` depending on the refund amount vs. grand total. Stock is optionally restocked (configurable per refund).

---

## 7. Product Types

The `product_type` Select field on a Product entry drives behaviour. All product types share the same `ProductEntryType` class — the type determines which checkout pipeline steps apply.

### 7.1 Simple Product

A product with no variants, no shipping weight required, no downloads. The baseline. Price is a single `Money` field.

### 7.2 Physical Product

Has weight, dimensions, and stock quantity. Inventory is decremented on order creation and can be restocked on refund or manual admin action. Triggers the `ResolveShippingMethod` pipeline step. The `ShippingAddress` is required at checkout.

**Inventory management concerns:**

- Stock reservation: Decrement reserved stock when an order enters `pending_payment`, so two concurrent checkouts can't both succeed on the last unit. Release the reservation if payment fails or the order times out.
- Low stock threshold: A `low_stock_threshold` field triggers a `LowStockAlert` event when `stock_quantity` drops below it.
- Backorders: A `allow_backorders` Boolean field permits overselling with a warning to the customer.

### 7.3 Digital Product

No shipping. No weight. After successful payment, the `HandleDigitalDelivery` pipeline step generates a `DownloadToken` (see Section 13), stores it on the `OrderItem` entry, and emails the customer a secure link. Download limits and expiry are enforced at the token level.

### 7.4 Optioned Product (Variants)

Uses the `ProductAttributes` field to define variant axes. Variants can be modelled two ways:

**Approach A — Child Entries:** Each variant combination is its own Product entry, flagged as a child (`parent_id` Relationship field pointing to the parent product). Children inherit the parent's taxonomy/categories but carry their own SKU, price, and stock. This is the CartThrob approach and gives you maximum flexibility and queryability.

**Approach B — Matrix Rows:** Variants are stored as rows in a `variants` Matrix field on the parent product. Simpler to manage but harder to query individually. Good for products with few, simple variants.

Recommendation: **Approach A** for the shop system; expose "parent" products in the storefront and resolve the correct child at add-to-cart time based on selected options.

### 7.5 Subscription Product

When `product_type = subscription`, the checkout flow activates `HandleSubscriptionActivation` instead of `DecrementInventory`. After payment, a `Subscription` entry is created and the gateway's subscription billing is initiated (if the gateway supports recurring billing natively, e.g., Stripe). Otherwise, a queued job (`ProcessSubscriptionRenewal`) handles billing on `current_period_end`.

---

## 8. Subscriptions

### 8.1 Billing Modes

**Gateway-managed billing** (preferred): Stripe, Braintree, and similar gateways handle recurring charges natively. You call `$gateway->createSubscription(...)` at checkout and receive webhook events when renewals succeed or fail. The `HandleWebhook` controller (powered by Spatie's webhook client, already installed) processes `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted`, etc., and updates the `Subscription` entry status accordingly.

**Self-managed billing**: For gateways that don't support subscriptions natively, a Laravel scheduled command runs daily, finds subscriptions where `current_period_end <= now()`, charges via the stored payment method, creates renewal Order entries, and advances `current_period_start`/`current_period_end`.

### 8.2 Dunning

When a renewal payment fails, the subscription moves to `past_due`. A configurable dunning schedule retries (e.g., at days 1, 3, 7 after first failure) and emails the customer each time. After the final retry, the subscription is cancelled and the `ends_at` date is set.

### 8.3 Proration

When a customer upgrades or downgrades mid-cycle, the `SubscriptionService` calculates the prorated credit/charge. For gateway-managed billing, this is delegated to the gateway's proration API. For self-managed, it's a credit entry on the customer's next invoice.

### 8.4 Trial Periods

Configured on `SubscriptionPlan.trial_days`. During trial, no payment is taken. When the trial expires, the first billing cycle is charged. The customer's subscription status is `trialing` during this period.

---

## 9. Discounts & Coupons

Following CartThrob's model: discounts and coupons are Entries, and the settings that define their behaviour are stored in a `DiscountSettings` custom field. The `DiscountEngine` evaluates all active discounts and valid coupons against the cart at calculation time.

### 9.1 DiscountEngine

```php
class DiscountEngine
{
    public function evaluate(Cart $cart, array $couponCodes = []): DiscountResult;
}
```

The engine:
1. Loads all `published` Discount entries where `starts_at <= now()` and (`expires_at` is null or `expires_at >= now()`).
2. Loads Coupon entries matching the provided `$couponCodes`, with the same date guards plus usage limit checks.
3. Evaluates each discount/coupon against `AbstractRule` conditions. Rules are ANDed within a discount; multiple discounts are evaluated by priority.
4. Non-stackable discounts: only the highest-value qualifying discount applies (plus all free-shipping discounts, which are always stackable).
5. Returns a `DiscountResult` value object listing each applied discount, the line items it affects, and the amounts reduced.

### 9.2 Discount Types

**PercentageDiscount** — reduces order or item total by a percentage. Supports `applies_to_shipping` flag.

**FixedDiscount** — reduces total by a fixed money amount. Prorated across line items for partial refund purposes.

**FreeShipping** — zeroes out shipping total. Stackable with other discount types.

**BuyXGetY** — buy N of product A, get M of product B free (or at a percentage off). Requires `buy_quantity`, `buy_product_ids`, `get_quantity`, `get_product_ids`, `get_discount_type`, `get_discount_value` on the DiscountSettings JSON.

**VolumeDiscount** — tiered pricing: buy 1-4 at full price, 5-9 at 10% off, 10+ at 20% off. Tiers stored as a Matrix sub-field within DiscountSettings.

### 9.3 Coupon Validation

Before a coupon code is applied, `CouponValidator` checks: code exists and is published, date range is valid, usage limit not exceeded, per-customer usage limit not exceeded, first-time-only restriction (checks if customer has prior orders), and minimum order amount met. Validation errors are returned as a keyed array so the frontend can display specific messages.

### 9.4 Discount Integration with CartCalculator

The `CartCalculator` delegates entirely to `DiscountEngine` for discount computation. It does not contain any discount logic itself. This keeps discounts testable in isolation and makes the engine swappable.

---

## 10. Payment Gateway Abstraction

This is the most strategically important decoupling. The payment layer is designed to be usable standalone — outside of the shop — for ad-hoc payment engagements. It lives at `mithra62\Shop\Gateway\` but has no hard dependency on the cart or order system.

### 10.1 AbstractPaymentGateway

```php
abstract class AbstractPaymentGateway
{
    abstract public function handle(): string;           // e.g., 'stripe', 'paypal'
    abstract public function label(): string;
    abstract public function isTestMode(): bool;
    abstract public function charge(PaymentRequest $request): PaymentResult;
    abstract public function refund(string $transactionId, int $amountCents): PaymentResult;
    abstract public function getConfigFields(): array;  // For admin UI rendering
    abstract public function validateConfig(array $config): array;

    // Optional — override to signal capability
    public function canSavePaymentMethod(): bool { return false; }
    public function canSubscribe(): bool         { return false; }
    public function canPartialRefund(): bool     { return true; }
}
```

### 10.2 PaymentRequest (Value Object)

```php
class PaymentRequest
{
    public int    $amountCents;
    public string $currency;        // ISO 4217
    public string $paymentToken;    // From frontend tokenisation (e.g., Stripe.js token)
    public ?int   $savedMethodId;   // If charging a saved card
    public string $description;
    public array  $metadata;        // Order number, customer ID, etc.
    public ?BillingAddress $billingAddress;
    public bool   $capture;         // True = charge now; false = authorise only
}
```

### 10.3 PaymentResult (Value Object)

```php
class PaymentResult
{
    public bool   $success;
    public string $transactionId;
    public string $status;          // 'captured', 'authorised', 'failed', 'pending'
    public ?string $errorCode;
    public ?string $errorMessage;
    public array  $rawResponse;     // Full gateway response for logging
    public ?string $savedMethodToken; // Vault token if card was saved
}
```

### 10.4 OmnipayGateway

A thin adapter around any Omnipay-supported gateway. Since you already know Omnipay well, this bridge is straightforward: it maps `PaymentRequest` to an Omnipay purchase request, normalises the Omnipay response into a `PaymentResult`, and wraps exceptions in `PaymentException`.

```php
class OmnipayGateway extends AbstractPaymentGateway
{
    public function __construct(
        private \Omnipay\Common\GatewayInterface $omnipayGateway,
        private array $config
    ) {}

    public function charge(PaymentRequest $request): PaymentResult
    {
        $response = $this->omnipayGateway->purchase([
            'amount'    => number_format($request->amountCents / 100, 2, '.', ''),
            'currency'  => $request->currency,
            'token'     => $request->paymentToken,
            'returnUrl' => route('shop.checkout.return'),
            'cancelUrl' => route('shop.checkout.cancel'),
        ])->send();

        return new PaymentResult(
            success:       $response->isSuccessful(),
            transactionId: $response->getTransactionReference() ?? '',
            status:        $response->isSuccessful() ? 'captured' : 'failed',
            errorMessage:  $response->getMessage(),
            rawResponse:   $response->getData(),
        );
    }
}
```

### 10.5 GatewayManager

Registry and factory. Gateways are registered by handle. Per-tenant configuration (API keys, etc.) is stored in tenant settings under `shop.gateway.{handle}.*` and injected at instantiation time.

```php
class GatewayManager
{
    public function register(string $handle, string $class): void;
    public function resolve(string $handle): AbstractPaymentGateway;
    public function available(): Collection;         // All registered gateways
    public function enabled(?int $tenantId): Collection; // Tenant-configured gateways
}
```

### 10.6 Ad-hoc / Standalone Payments

Because the gateway abstraction has no dependency on cart, order, or entry systems, it can be used standalone for any payment need: invoicing, donations, event registrations, instalment plans. A minimal `AdHocPaymentService` exposes this for use outside the shop checkout pipeline. This fulfils your stated goal of a decoupled payment infrastructure.

### 10.7 Stored Payment Methods

For subscriptions and one-click reorder, a `payment_methods` table stores vault tokens (never raw card data) per customer. `CanSavePaymentMethod` interface marks gateways that support this. The checkout UI shows saved methods alongside the new-card form.

---

## 11. Shipping Abstraction

### 11.1 AbstractShippingMethod

```php
abstract class AbstractShippingMethod
{
    abstract public function handle(): string;
    abstract public function label(): string;
    abstract public function getQuote(ShipmentContext $context): ShippingQuote;
    abstract public function getConfigFields(): array;
    public function isAvailable(ShipmentContext $context): bool { return true; }
}
```

`ShipmentContext` carries: cart items with weights and dimensions, origin address (warehouse), destination `ShippingAddress`, and the tenant's `ShippingZone` match.

### 11.2 ShippingQuote (Value Object)

```php
class ShippingQuote
{
    public string $methodHandle;
    public string $methodLabel;
    public int    $amountCents;
    public ?string $estimatedDelivery; // e.g., "3-5 business days"
    public array  $metadata;
}
```

### 11.3 Built-in Methods

**FlatRateShipping** — fixed price per order or per item. Configured in `ShippingRate` entry.

**FreeShipping** — zero cost; can be conditional on a minimum order amount. Also triggered by the `FreeShippingDiscount` type.

**WeightBasedShipping** — rate tiers based on total cart weight. Rate table stored as Matrix field in `ShippingRate` entry.

**ExternalRateShipping** — live rates from carrier APIs (UPS, FedEx, USPS, Canada Post). Calls the carrier's rate API with the shipment details and returns available services. Credentials stored in tenant gateway settings. Each carrier becomes a subclass; a `CarrierRateCache` layer caches responses for the checkout session to avoid repeated API calls.

### 11.4 ShippingManager

Analogous to `GatewayManager`. Resolves available methods for a given `ShipmentContext`, filtering by zone match and method availability. Returns a `Collection<ShippingQuote>` for the customer to choose from.

### 11.5 Multi-warehouse

Warehouses are modelled as Entries with an address Matrix field. A `WarehouseResolver` selects the optimal warehouse for a shipment (nearest to customer, or round-robin). Initially, most tenants will have one warehouse; the resolver defaults to a single configured origin address from tenant settings.

---

## 12. Tax Engine

### 12.1 TaxEngine

```php
class TaxEngine
{
    public function calculate(TaxableCart $cart, ShippingAddress $address): TaxResult;
}
```

Takes a cart and destination address; returns a `TaxResult` with total tax amount and a breakdown of rates applied (for display on the order and for accounting/remittance).

### 12.2 Rate Resolution

1. Look up the customer's country and state from `ShippingAddress`.
2. Load all `TaxRate` entries that match the country (and optionally state), ordered by priority.
3. For each cart item, resolve its `TaxClass` via the related `tax_class` field on the Product entry.
4. Apply matching rates to each item. Items with a `zero` or `exempt` TaxClass are skipped.
5. Handle compound taxes: a compound rate is applied on top of the already-taxed subtotal rather than the pre-tax subtotal.
6. If `applies_to_shipping` is true on a rate, apply it to the shipping total as well.

### 12.3 Tax-Inclusive Pricing

Configurable per tenant. If prices are entered inclusive of tax (common in EU/UK/Australia), the `TaxEngine` extracts the tax component from the price rather than adding it on top. The `Money` field type and `CartCalculator` are aware of this flag.

### 12.4 VAT/GST

For digital goods sold to EU customers, the customer's country determines the VAT rate (not the seller's country). The `VatResolver` implements the EU digital services VAT rules: identify the customer's country from billing address and IP, apply the correct country rate, and tag the tax record for OSS (One Stop Shop) reporting. This is a complex regulatory requirement; the initial implementation should be functional but should clearly document its limitations and encourage tenants to seek local tax advice.

### 12.5 Tax-Exempt Customers

A `tax_exempt` Boolean and `tax_exempt_id` Text field on the User profile (via `UserSchema`) allows marking specific customers as exempt. The `TaxEngine` checks this before applying any rates.

---

## 13. Digital Product Delivery

### 13.1 DownloadToken

Generated per-OrderItem after successful payment. Stored in the `download_token` field on the `OrderItem` entry.

```php
class DownloadToken
{
    public string $token;          // 64-char random hex
    public int    $orderItemId;
    public int    $customerId;
    public int    $downloadCount;
    public int    $downloadLimit;  // From product; 0 = unlimited
    public ?Carbon $expiresAt;     // Null = no expiry
    public Carbon  $createdAt;
}
```

Tokens are stored in a `download_tokens` table (not in `field_values`) because they need to be queried by token value efficiently.

### 13.2 Download Endpoint

`GET /shop/downloads/{token}` — a controller that:
1. Looks up the token; 404 if not found.
2. Checks expiry; 410 Gone if expired.
3. Checks download limit; 403 if exceeded.
4. Optionally verifies the requesting user is the token owner (allow guest links for simplicity, but IP-rate-limit).
5. Fetches the file from Spatie MediaLibrary using the product's `download_file` media collection.
6. Increments `download_count`.
7. Streams the file with `Content-Disposition: attachment` and appropriate MIME type. Do **not** expose the real file path.

### 13.3 Token Regeneration

Customers can request a new token from their account (if download limit or expiry is blocking them). Admin can also regenerate tokens manually.

---

## 14. Tenancy Considerations

### 14.1 With Tenancy (SaaS mode)

Per the existing `TenantPlan.md`, all new shop tables receive a `tenant_id` column. The `BelongsToTenant` trait is applied to all shop models (Cart, CartItem, Order, DownloadToken, etc.).

Each tenant configures its own:
- Enabled payment gateways (from their tenant settings)
- Shipping methods and rates
- Tax rates and classes
- Currency and locale
- Email templates for order confirmations

The `GatewayManager`, `ShippingManager`, and `TaxEngine` all accept an optional `?int $tenantId` parameter and use it to scope their configuration lookups. When `null`, they fall back to system-wide defaults — useful for the single-tenant (installable) mode.

### 14.2 Without Tenancy (Installable mode / fake tenant layer)

As requested, the tenant layer is faked: a single default "tenant" with `tenant_id = 1` is seeded. All `BelongsToTenant` queries resolve to this. The `TenantManager` singleton returns the default tenant without any middleware or subdomain resolution.

This means the architecture is identical in both modes — swapping in real tenancy is a matter of wiring the `ResolveTenant` middleware and removing the hardcoded default. The codebase never needs to know whether it's in SaaS mode or single-tenant mode.

### 14.3 Per-Tenant Shop Settings

Stored under the existing `settings` table using the domain/key pattern already in the platform:

```
shop.currency           → USD
shop.weight_unit        → oz
shop.dimension_unit     → in
shop.prices_include_tax → false
shop.order_prefix       → ORD
shop.low_stock_threshold → 5
shop.guest_checkout     → true
shop.gateway.stripe.publishable_key → pk_live_...
shop.gateway.stripe.secret_key      → sk_live_...
shop.gateway.stripe.webhook_secret  → whsec_...
shop.shipping.origin_address        → {...}
```

---

## 15. Database Schema Additions

These tables supplement the existing CMS schema. All follow the established conventions (bigint PKs, timestamps, `tenant_id` when tenancy is active).

### `carts`
```
id               bigint PK
tenant_id        bigint FK → tenants.id
session_id       string nullable index    -- Guest carts
user_id          bigint nullable FK → users.id
currency         string(3) default 'USD'
coupon_codes     json nullable
metadata         json nullable
expires_at       timestamp nullable
created_at / updated_at
index: (tenant_id, session_id)
index: (tenant_id, user_id)
```

### `cart_items`
```
id               bigint PK
cart_id          bigint FK → carts.id cascade delete
entry_id         bigint FK → entries.id restrict
quantity         unsignedSmallInt
unit_price       bigint           -- Cents, frozen at add time
options          json nullable    -- Selected variant/modifier options
metadata         json nullable
created_at / updated_at
```

### `download_tokens`
```
id               bigint PK
tenant_id        bigint nullable FK → tenants.id
token            string(64) unique index
order_item_id    bigint FK → entries.id  -- The OrderItem entry
user_id          bigint nullable FK → users.id
download_count   unsignedInt default 0
download_limit   unsignedInt default 0   -- 0 = unlimited
expires_at       timestamp nullable
created_at / updated_at
```

### `sequences`  (for order number generation)
```
id               bigint PK
tenant_id        bigint nullable FK → tenants.id
sequence_name    string               -- e.g., 'order_number_2026'
next_value       bigint unsigned default 1
updated_at
unique: (tenant_id, sequence_name)
```

### `payment_methods` (stored vault tokens)
```
id               bigint PK
tenant_id        bigint nullable FK → tenants.id
user_id          bigint FK → users.id
gateway_handle   string
token            string               -- Vault token from gateway
last4            string(4) nullable
card_brand       string nullable      -- visa, mastercard, etc.
expiry_month     tinyint nullable
expiry_year      smallint nullable
billing_name     string nullable
is_default       boolean default false
created_at / updated_at
```

### `tax_snapshots` (per-order tax audit trail)
```
id               bigint PK
order_entry_id   bigint FK → entries.id
rate_handle      string
rate_label       string
rate_percent     decimal(8,4)
taxable_amount   bigint    -- Cents
tax_amount       bigint
applied_to       enum('items','shipping','both')
created_at
```

### Existing table additions

**`entries`** — no schema change needed. The `product_type`, `order_number`, and all other commerce fields live in `field_values` and `entry_relationships` per the existing pattern.

**`users`** — add `tax_exempt boolean default false` and `tax_exempt_id string nullable` via UserSchema fields (no migration needed if using the existing user schema system).

---

## 16. Implementation Roadmap

Work in order. Each phase is shippable and independently testable.

### Phase 1 — Foundation (Week 1-2)
- [ ] Scaffold `mithra62/Shop/` directory and `ShopServiceProvider`
- [ ] Register `ShopServiceProvider` in `bootstrap/providers.php`
- [ ] Implement `Money` field type + `MoneyValue` value object
- [ ] Implement `Select` field type
- [ ] Implement `Matrix` field type + `MatrixValue` collection
- [ ] Implement `ProductAttributes` field type (extends Matrix)
- [ ] Implement `PriceModifier` field type
- [ ] Seed shop field types into `field_types` table

### Phase 2 — Product Catalogue (Week 3-4)
- [ ] Extend `ProductEntryType` with all new fields (via FieldLayout migration/seeder)
- [ ] Implement `SubscriptionPlanEntryType`
- [ ] Implement `TaxClassEntryType` and `TaxRateEntryType`
- [ ] Implement `ShippingZoneEntryType` and `ShippingRateEntryType`
- [ ] Seed default entry groups: `shop_products`, `shop_orders`, `shop_subscriptions`, `shop_discounts`

### Phase 3 — Cart (Week 5)
- [ ] `CartItem` value object
- [ ] `SessionCartRepository`
- [ ] `DatabaseCartRepository` + `carts`/`cart_items` migration
- [ ] `HybridCartRepository` with guest→auth merge
- [ ] `CartManager` façade + service binding
- [ ] `CartCalculator` (totals only — discounts stubbed, tax stubbed)

### Phase 4 — Payment Gateway (Week 6-7)
- [ ] `AbstractPaymentGateway` + `PaymentRequest` + `PaymentResult`
- [ ] `GatewayManager` registry
- [ ] `OmnipayGateway` wrapper
- [ ] `payment_methods` migration + saved-method flow
- [ ] Tenant-scoped gateway config resolution
- [ ] `AdHocPaymentService` standalone wrapper

### Phase 5 — Discounts & Coupons (Week 8)
- [ ] `DiscountSettings` field type
- [ ] `DiscountEntryType` and `CouponEntryType`
- [ ] `AbstractDiscount` + five discount type classes
- [ ] `AbstractRule` + rule classes
- [ ] `DiscountEngine`
- [ ] `CouponValidator`
- [ ] Wire `CartCalculator` to `DiscountEngine`

### Phase 6 — Tax Engine (Week 9)
- [ ] `TaxEngine` + `TaxResult` value object
- [ ] `ByCountryResolver` + `ByStateResolver`
- [ ] Tax-inclusive pricing support
- [ ] `VatResolver` (EU digital goods)
- [ ] `tax_snapshots` migration
- [ ] Wire `CartCalculator` to `TaxEngine`

### Phase 7 — Shipping (Week 10)
- [ ] `AbstractShippingMethod` + `ShippingQuote` + `ShipmentContext`
- [ ] `FlatRateShipping`, `FreeShipping`, `WeightBasedShipping`
- [ ] `ShippingManager`
- [ ] Wire `CartCalculator` to `ShippingManager`
- [ ] `ExternalRateShipping` skeleton (concrete carriers in Phase 10+)

### Phase 8 — Orders & Checkout (Week 11-12)
- [ ] `OrderEntryType` with all fields and status group
- [ ] `OrderItemEntryType`
- [ ] `OrderNumberGenerator` + `sequences` migration
- [ ] `OrderStateMachine`
- [ ] `CheckoutPipeline` with all pipe classes
- [ ] `OrderService`
- [ ] Inventory decrement + reservation logic
- [ ] Order confirmation email (use existing notification/mail infrastructure)

### Phase 9 — Digital Delivery (Week 13)
- [ ] `download_tokens` migration
- [ ] `DownloadToken` model + `DownloadService`
- [ ] `HandleDigitalDelivery` checkout pipe
- [ ] `GET /shop/downloads/{token}` controller
- [ ] Token regeneration endpoint

### Phase 10 — Subscriptions (Week 14-15)
- [ ] `SubscriptionEntryType` with all fields and status group
- [ ] `SubscriptionService`
- [ ] `HandleSubscriptionActivation` checkout pipe
- [ ] Webhook processing (Spatie webhook client) for Stripe subscription events
- [ ] Self-managed billing fallback (scheduled command)
- [ ] Dunning retry logic
- [ ] Upgrade/downgrade + proration

### Phase 11 — Tenancy Integration (Week 16)
- [ ] Add `tenant_id` to all new shop tables
- [ ] Apply `BelongsToTenant` trait to shop models
- [ ] Fake single-tenant mode (seed `tenant_id = 1` everywhere)
- [ ] Tenant settings integration for shop config
- [ ] Scope `GatewayManager`, `ShippingManager`, `TaxEngine` to tenant

---

## 17. Open Questions & Concerns

**Currency handling.** A multi-currency storefront is significantly more complex than single-currency. The `Money` field type stores one currency per product. If you need multi-currency, you'll need a `CurrencyConverter` service and a decision about whether prices are stored in multiple currencies or converted at display/checkout time. Recommend single-currency per tenant for v1.

**Order item storage approach.** The "OrderItems as full Entries" approach is architecturally pure and consistent, but creates a lot of entries. A high-volume store generating 1,000 orders/day with an average of 5 items each creates 5,000 OrderItem entries daily. With the existing entry/field_values schema this is manageable but should be profiled. The alternative (JSON line items on the Order entry) is pragmatic and worth keeping as a fallback.

**Product variants — combinatorial explosion.** The "child Entries per variant" approach for optioned products is powerful but can generate hundreds of entries for a product with 3 axes × 10 options each. Consider lazy variant generation (create the child entry on first purchase, not at product creation) and a hard variant limit per product.

**Omnipay maintenance.** Omnipay is somewhat unmaintained for newer gateways (Stripe's Omnipay adapter lags Stripe API versions). You're already aware of this. The `AbstractPaymentGateway` contract means you can add direct SDK integrations (e.g., a `StripeDirectGateway` using the official `stripe-php` SDK) without changing any calling code. Recommend doing Stripe as a direct integration from the start.

**Tax compliance.** The `TaxEngine` handles rate application correctly, but actual compliance (knowing which rates apply where, filing returns) is entirely the merchant's responsibility. The system should make clear in the UI and docs that it provides a rate-application engine, not tax advice. Consider integrating with a tax compliance SaaS (Avalara, TaxJar) as an optional `ExternalTaxGateway` implementation behind the `TaxEngine` interface.

**VAT on digital goods.** This is genuinely complex (EU OSS rules, UK VATMOSS, Australian GST on digital services, etc.). The `VatResolver` should be built with extensibility in mind from day one, but its initial scope should be clearly documented.

**Refund accounting.** The current plan creates refund records but doesn't address double-entry bookkeeping, revenue recognition, or accounting software integration. If merchants need to export to QuickBooks/Xero/etc., a `BookkeepingAdapter` abstraction should be added to Phase 10+.

**Search and filtering.** Product browsing typically needs price-range filtering, attribute filtering, and full-text search — none of which the current `EntryQueryBuilder` supports directly (it queries `entries` columns, not `field_values`). Recommend a `ProductIndexer` that flattens product field values into a `product_search_index` table (or Meilisearch/Typesense) for queryable fields. This is Phase 10+ work.

**Email templates.** Order confirmation, shipping notification, refund receipt, subscription renewal, and failed payment emails all need templated content. The existing platform's Twig templating is a natural fit. A `ShopMailer` service with per-tenant Twig templates (stored in the database or as files) should be part of Phase 8.

**API surface.** A JSON API for headless storefronts (React/Vue frontends, mobile apps) should be designed alongside the server-side checkout. The existing `darkaonline/l5-swagger` package is already installed, making documentation straightforward. Authentication is already handled by Sanctum.

---

*This document represents a first-pass architecture. Many details will be refined as implementation progresses. The CartThrob philosophy — using your content management primitives (Entries, Field Types, Statuses) as the backbone of commerce data — is deeply sound and should be held firmly as complexity grows.*

---

**Sources consulted:**
- [CartThrob Overview Documentation](https://www.cartthrob.com/docs/pages/general_information/)
- [CartThrob Discounts & Coupons](https://www.cartthrob.com/docs/discounts/index.html)
- [CartThrob Discount Plugins (Developer Hooks)](https://www.cartthrob.com/docs/developers/discount_plugins/index.html)
- [CartThrob Configurable Product Types](https://www.cartthrob.com/2-the-control-panel/4-products/product-types/configurable.html)
- [Building E-Commerce with ExpressionEngine (CreativeArc)](https://creativearc.com/blog/building-an-e-commerce-website-with-expressionengine)
