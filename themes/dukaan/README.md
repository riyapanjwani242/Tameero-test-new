# Dukaan — Urdu RTL WooCommerce Shop Theme

**Version:** 1.0.0 · **Requires:** WordPress 6.0+, PHP 7.4+ · **License:** GPL-2.0+

An Urdu-first, RTL-first WooCommerce shop theme built for Pakistani
bedding & clothing sellers who take orders on **WhatsApp** with an
**advance-payment** model. Warm maroon + gold design, `Noto Nastaliq
Urdu` typography, and a one-tap WhatsApp order button on every product.

> Roman Urdu: Ye theme un Pakistani sellers ke liye hai jo WhatsApp par
> order lete hain. Product page par Add to Cart ke sath bara sa
> "WhatsApp par Order Karein" button ata hai — customer ek tap mein
> pehle se likhe hue message ke sath aap ke number par pohnch jata hai.

---

## Features

- **WhatsApp Order button** on the single product page (after Add to
  Cart) — prefilled message with product name, price and URL.
- **Announcement / offer strip** on top — editable text, on/off switch.
- **RTL-first design** with Urdu font (Noto Nastaliq Urdu, line-height 2).
- Responsive product grid: **4 / 3 / 2** columns (desktop / tablet / mobile).
- Sale badges show the real **-% discount**, not just "Sale!".
- Home page: hero, featured categories, latest 8 products, a Sale
  section that appears only when sale products exist, and a trust
  strip (delivery / advance payment / WhatsApp).
- WooCommerce gallery zoom, lightbox and slider support.
- Header cart count that refreshes with AJAX add-to-cart.
- Shop sidebar + 3 footer widget columns; Primary & Footer menus.
- Works **without WooCommerce too** (falls back to posts) — no fatals.
- No jQuery dependency; vanilla JS for mobile menu, search toggle and
  the announcement close button.
- Fully translatable — every string uses the `dukaan` text domain.

## Installation

1. Copy the `dukaan` folder into `wp-content/themes/` — or zip the
   folder and upload it from **Appearance → Themes → Add New → Upload**.
2. **Activate** the theme.
3. (Recommended) Install & activate **WooCommerce**, set currency to
   **PKR**, and create your products.
4. Set your WhatsApp number in the Customizer (see below) — without it,
   the WhatsApp button stays hidden.
5. Assign menus under **Appearance → Menus** (Primary + Footer) and add
   widgets under **Appearance → Widgets** (Shop Sidebar, Footer 1–3).

> In WebDevHub, the **Online Store** blueprint already ships
> WooCommerce + PKR — activate Dukaan and the shop is ready.

## Customizer Guide

**Appearance → Customize → Dukaan Theme**

| Setting | What it does |
|---|---|
| **WhatsApp Number** | Digits only, with country code (e.g. `923001234567`). Used by the WhatsApp Order button and the footer contact link. Empty = button hidden. |
| **Announcement Bar On** | Show/hide the gold strip on top. |
| **Announcement Text** | The offer line shown in the strip. |
| **Primary Color** | Maroon by default (`#7b1e2b`). Applies to header, buttons and links via a CSS variable. |
| **Hero Heading / Hero Text** | Home page hero title and line (defaults are set for a bedding/clothing shop). |
| **Footer Note** | Small line under the footer — address, timings, anything. |

Site title/logo: **Site Identity** (Custom Logo supported).
Menus: **Menus** — `Primary` shows in the header, `Footer` in footer column 2 (when no widget is placed there).

## How selling works with Dukaan

1. Customer sees a product → taps **WhatsApp par Order Karein**.
2. WhatsApp opens on **your number** with the product name, price and
   link already typed — the customer just presses send.
3. You confirm on chat, take **advance payment** (JazzCash / EasyPaisa /
   bank), and dispatch. Delivery charges are told per order.
4. The normal WooCommerce cart/checkout also works if you enable it
   (COD included in WebDevHub's Store blueprint).

## Files

```text
dukaan/
├── style.css            Theme header + base styles
├── rtl.css              RTL adjustments (auto-loaded for RTL locales)
├── functions.php        Setup, menus, widget areas, enqueues
├── header.php           Announcement bar, branding, nav, search, cart
├── footer.php           3 widget columns + note
├── front-page.php       Hero, categories, products, sale, trust strip
├── index.php / archive.php / search.php   Post grids
├── page.php / single.php                  Pages & posts
├── sidebar.php          Shop sidebar (sidebar-1)
├── 404.php / comments.php
├── inc/
│   ├── customizer.php   "Dukaan Theme" Customizer section
│   └── woocommerce.php  Woo integration, wrappers, WhatsApp button
├── template-parts/content-card.php
├── assets/
│   ├── css/dukaan.css   Full design
│   └── js/dukaan.js     Vanilla JS (menu / search / announcement)
└── screenshot.png
```

## Notes for developers

- All WooCommerce code lives in `inc/woocommerce.php` behind a
  `class_exists( 'WooCommerce' )` guard.
- All output is escaped (`esc_html` / `esc_attr` / `esc_url`); all
  Customizer settings have sanitize callbacks.
- Prices and counts inside RTL text are kept LTR via `rtl.css` rules.

---

*Dukaan — WebDev Hub Studio ki taraf se, Pakistani sellers ke liye.*
