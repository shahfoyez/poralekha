# Order Fulfillment

How StoreEngine handles shipping fulfillment — marking order items as shipped,
recording courier + tracking, notifying the customer, and (optionally)
auto‑completing the order once everything is delivered.

Fulfillment is a **core** capability: an ordinary single‑vendor store manages it
entirely from wp‑admin with **no addon**. Two optional layers extend it — the
**Email addon** sends the customer notification, and the **Multi‑Vendor addon**
lets vendors fulfill their own items from the storefront dashboard.

---

## 1. Concept

A paid order can contain several line items. Each line item has its own
**shipping status** that moves forward through a fixed lifecycle, plus optional
**courier + tracking** details. Marking items is independent of the order's
overall status — an order with two items can have one "delivered" and one "still
awaiting shipment". Only **physical** products are shippable; digital products
are skipped everywhere.

### Shipping lifecycle (forward‑only)

| Order | Status value | Label |
|------:|--------------|-------|
| 1 | `ready_for_ship` | Ready for ship |
| 2 | `awaiting_shipment` | Awaiting shipment |
| 3 | `shipped` | Shipped |
| 4 | `on_the_way` | On the way |
| 5 | `out_for_delivery` | Out for delivery |
| 6 | `delivered` | Delivered |
| 7 | `returned` | Returned |

A line can only move **forward** (or stay put — re‑saving the same status to
update tracking is allowed). The canonical list lives in
`StoreEngine\Utils\Constants::get_shipping_statuses()`, shared by every code
path so admin and vendor can never drift.

---

## 2. Who does what

### Store admin (core — no addon)

**wp‑admin → StoreEngine → Orders → open an order**. A **Fulfillment** panel
appears in the left column (under the products), shown only for **saved orders
that contain at least one physical product**. Per physical line:

- **Status** — forward‑only dropdown
- **Courier** — free text (e.g. DHL)
- **Tracking #** — free text
- **Tracking URL** — optional, becomes a clickable link for the customer
- **Save**

Digital‑only orders show no Fulfillment panel.

### Vendor (Multi‑Vendor addon)

**Storefront dashboard → Store orders**. Each order is a collapsible row showing
a status summary; expanding it reveals the same per‑item controls as the admin
panel — but a vendor only sees and can change **their own** line items.
All‑digital orders render as a static "No shipment required" row (no controls).

### Customer (core — no addon)

**Storefront dashboard → My Orders → order details**. A **Shipping & tracking**
section lists each **physical** item with its status pill and
"Courier — tracking #" (a link when a tracking URL was provided). Digital‑only
orders omit the section entirely. When the Email addon is active, the customer
also receives a "Your item has shipped" email per shipment (see §6).

---

## 3. Data model

No schema changes were introduced — fulfillment reuses existing tables.

| What | Where | Notes |
|------|-------|-------|
| Per‑item shipping status | `wp_storeengine_order_product_lookup.shipping_status` | One row per line item, **keyed by `order_item_id`** |
| Courier / tracking | `wp_storeengine_orders_meta`, key `_storeengine_shipment_{order_item_id}` | Serialized array (below) |
| Vendor ownership | `wp_storeengine_order_product_lookup.vendor_id` | Populated from product `post_author` |

The shipment meta array:

```php
[
    'courier'         => 'DHL',
    'tracking_number' => 'ABC123',
    'tracking_url'    => 'https://…',   // optional
    'shipped_at'      => '2026-06-18 10:00:00', // stamped once, on first shipped-or-later
    'vendor_id'       => 12,            // owning vendor (audit/display)
    'status'          => 'shipped',     // mirror of the lookup status
]
```

**Two invariants that matter:**

1. **Key by `order_item_id`, never `product_id`** — an order can have two lines
   of the same product; keying by product id would mutate both.
2. **Only positive `order_item_id` rows** — the Multi‑Vendor commission
   calculator stores refund markers (`shipping_status = 'refund:<id>'`) on
   synthetic **negative** `order_item_id` rows. Every read/write filters
   `order_item_id > 0`.

---

## 4. The shared service

Every path — admin AJAX and vendor REST — routes through one method so the
behavior is identical:

```php
\StoreEngine\Classes\OrderShipment::record(
    int    $order_item_id,
    string $new_status,           // one of Constants::get_shipping_statuses()
    array  $tracking = [],        // ['courier' => '', 'tracking_number' => '', 'tracking_url' => '']
    string $actor_label = ''      // 'Store admin' or the vendor store name
): array|WP_Error;
```

It performs, in order:

1. Validate the status (must be in the enum).
2. Resolve the lookup row by `order_item_id` (positive only) → 404 if missing.
3. Block digital products → 400.
4. Enforce forward‑only → 409 on a backward move.
5. Write the status to the lookup row.
6. Write courier/tracking to order meta; stamp `shipped_at` once.
7. Add an internal order note (`"{actor} updated “{item}” to {status}. Courier: …, Tracking: …"`).
8. Fire `storeengine/order/item_shipped` (only on shipped‑or‑later **and** either
   it just entered shipped‑territory or the tracking changed — no spam on every
   micro‑transition).
9. Fire the core delivery hooks (`before_single_product_delivered`,
   `all_product_delivered` when every positive line is delivered,
   `after_single_product_delivered`).

Returns a result array (`shipping_status`, `status_label`, `shipment`,
`all_delivered`, …) or a `WP_Error` whose data carries the HTTP `status`.

**Callers add their own authorization:**

- **Admin** — AJAX action `update_shipping_status` (`includes/ajax/shipping.php`),
  capability `manage_options`, actor `"Store admin"`.
- **Vendor** — `POST /wp-json/storeengine/v1/vendor/fulfillment/shipment`
  (`addons/multi-vendor/api/fulfillment.php`): approved‑vendor gate **then** a
  per‑row ownership check (vendor resolved from `lookup.vendor_id`, never the
  request) → 403 if the line isn't theirs.

---

## 5. Hooks (developer reference)

All fired by `OrderShipment::record()`, so they fire for admin **and** vendor
shipments.

| Hook | Args | When |
|------|------|------|
| `storeengine/order/item_shipped` | `$order_id, $order_item_id, $product_id, $shipment(array), $new_status` | Item reaches shipped‑or‑later (or tracking changes) |
| `storeengine/before_single_product_delivered` | `$product_id, $order_id, $old_status, $new_status` | Before each status write's hooks |
| `storeengine/after_single_product_delivered` | `$product_id, $order_id, $old_status, $new_status` | After |
| `storeengine/all_product_delivered` | `$order_id` | Every positive line on the order is `delivered` |

Example — custom carrier webhook on shipment:

```php
add_action( 'storeengine/order/item_shipped', function ( $order_id, $item_id, $product_id, $shipment, $status ) {
    if ( 'shipped' !== $status ) {
        return;
    }
    my_push_to_carrier( $order_id, $shipment['courier'], $shipment['tracking_number'] );
}, 10, 5 );
```

---

## 6. Customer email

The "Your item has shipped" email is the `ItemShipped` class in the **Email
addon** (`addons/email/order/item-shipped.php`), settings key
`order_item_shipped`. It listens to `storeengine/order/item_shipped`, so it works
for single‑ and multi‑vendor stores alike.

- **Toggle:** wp‑admin → StoreEngine → Settings → Emails → *order item shipped*
  (`customer.is_enable`, default on).
- **Placeholders:** the standard order tokens plus `{shipped_item_name}`,
  `{shipment_status}`, `{shipment_courier}`, `{shipment_tracking_number}`,
  `{shipment_tracking_url}`, `{shipment_tracking_link}`.
- Disabling the Email addon doesn't break fulfillment — status/tracking still
  works; only the email stops.

---

## 7. Settings

| Setting | Location | Default | Effect |
|---------|----------|---------|--------|
| `order_item_shipped` email | Settings → Emails (Email addon) | On | Customer shipment email |
| `auto_complete_on_all_delivered` | Multi‑Vendor settings | Off | When on, auto‑advance an order **processing → completed** once every line is delivered |

The auto‑complete listener ships with the **Multi‑Vendor addon**
(`addons/multi-vendor/hooks.php`, on `storeengine/all_product_delivered`) and
only advances from `processing`. A store without Multi‑Vendor can implement the
same by hooking `all_product_delivered` directly (see §5).

---

## 8. Behavior & edge cases

- **Digital products** — never shippable. Blocked server‑side (400), hidden in
  every UI (admin filters them out; the customer section skips them; an
  all‑digital vendor order shows "No shipment required").
- **Multi‑vendor orders** — each vendor manages only their own lines; the shared
  order status is never touched by a vendor. `all_product_delivered` fires only
  when **every** vendor's lines are delivered.
- **Forward‑only** — backward moves are rejected (409). Re‑saving the same status
  with new courier/tracking is allowed (updates the meta, re‑emails only if the
  tracking number changed).
- **Variations** — handled correctly because writes are keyed by `order_item_id`,
  not product/variation id.
- **Refund rows** — excluded everywhere via `order_item_id > 0`.
- **No schema/version bump** — courier/tracking live in order meta.

---

## 9. End‑to‑end example

1. Customer buys 2 physical items (order `#191`, status `processing`).
2. Admin opens `#191` → **Fulfillment**, sets item A to **Shipped**, courier
   `DHL`, tracking `ABC123`, **Save**.
   - Lookup status for A → `shipped`; meta `_storeengine_shipment_{A}` written.
   - Order note logged; `storeengine/order/item_shipped` fires → customer emailed
     with the DHL tracking.
3. Admin later sets A and B to **Delivered**.
   - On the last one, `all_product_delivered` fires. If
     `auto_complete_on_all_delivered` is on (and Multi‑Vendor active), the order
     advances `processing → completed`.
4. The customer's **My Orders → order details** shows a *Shipping & tracking*
   table: each item's status pill + the DHL tracking link.

---

## 10. File reference

**Core**

- `includes/classes/order-shipment.php` — `OrderShipment::record()` (the engine)
- `includes/utils/constants.php` — `get_shipping_statuses()` / `get_shipping_status_label()`
- `includes/ajax/shipping.php` — admin AJAX `update_shipping_status`
- `includes/api/orders.php` — adds `order_item_id` / `shipping_type` / `shipping_status` / `shipment` to order REST items
- `dev_storeengine/containers/BackendDashboard/Pages/Orders/OrderEditor/Fulfillment/` — admin React panel
- `templates/frontend-dashboard/pages/partials/order-shipping-status.php` — customer order‑details section

**Email addon**

- `addons/email/order/item-shipped.php` — `ItemShipped` email

**Multi‑Vendor addon**

- `addons/multi-vendor/api/fulfillment.php` — vendor REST endpoint
- `templates/multi-vendor/frontend-dashboard/pages/vendor-orders.php` — vendor "Store orders" UI
- `addons/multi-vendor/assets/fulfillment.js` — vendor dashboard Save/accordion handler
- `addons/multi-vendor/settings.php` / `hooks.php` — `auto_complete_on_all_delivered` setting + listener
