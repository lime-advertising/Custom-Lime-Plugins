# WooCommerce Product Compare (Simple)

Easily add a **Compare** button to your WooCommerce store. Let customers select up to **4 products** and view them side-by-side in a sleek, responsive modal. Displays key product details like image, price, category, SKU, availability (via affiliates), and global attributes like size or color.

---

## 📦 Features

* Adds **Compare** buttons automatically on product archive (loop) pages
* Modal compares up to **4 WooCommerce products**
* Includes key comparison data:

  * Product Image
  * Price
  * Categories
  * SKU
  * Affiliate availability (logo + link)
  * Global product attributes (e.g. Size, Color)
* Fully responsive and mobile-friendly
* Compatible with **external products**
* Remembers selected products via `localStorage`
* Custom shortcode support

---

## 🚀 Installation

1. Upload the plugin folder to `/wp-content/plugins/woocommerce-product-compare-simple`
2. Activate via **Plugins** in the WordPress admin
3. No setup required — Compare buttons appear automatically on archive pages

---

## 🔧 Usage

### Compare Button Group

On archive pages (like `/shop`), each product shows:

* **View** – links to the product page
* **Compare** – adds product to the comparison list and opens modal

---

### Modal Comparison Table

Clicking “Compare” opens a modal table that includes:

* Product name (with link)
* Image
* Price
* Categories
* SKU
* Affiliate availability (via custom field)
* Global attributes (e.g., Size, Material)

Modal is responsive with sticky feature column and horizontal scroll.

---

## 🧠 Affiliate Logos Field

To show affiliate logos in the **"Available In"** column, add a custom field named:

```
_additional_affiliate_links
```

Its value must be an array of associative arrays:

```php
[
  [ "name" => "Amazon", "url" => "https://amazon.com/example" ],
  [ "name" => "BestBuy", "url" => "https://bestbuy.ca/example" ]
]
```

Supported logos:

* Amazon
* BestBuy
* HomeDepot
* Rona
* HOD

Other names will display as plain text links.

---

## 🔢 Shortcode

To manually place a compare button (e.g. on single product pages):

```php
[compare_button]
```

⚠️ `$product` must be available globally (inside WooCommerce loops or templates).

---

## 🧼 Clear & Remove Actions

* **Remove**: button under each product to remove it from the table
* **Clear All**: resets the compare list and closes the modal

---

## 🖥 Styling

Custom styles are defined in `compare.css`:

* Fixed-width equal columns
* Sticky left column for “Feature” names
* Mobile-friendly scroll with optional "Swipe right to view more" animation
* CSS variables used for easy theme integration:

  * `--wdtPrimaryColor`
  * `--wdtSecondaryColor`
  * `--wdtFontTypo_Base`, etc.

---

## ⚙️ Technical Details

* Compares up to 4 products using localStorage (`compareList`)
* AJAX-loaded comparison data via `get_compare_data` action
* Modal open state tracked with `wcp_open_modal`
* Supports global attributes (taxonomy-based)
* Uses `wc_get_product_terms()` for taxonomy attribute values
* Compatible with **external products** and **variable/global attributes**

---

## 📁 File Structure

```
woocommerce-product-compare-simple/
├── compare.js                     # All JS logic (compare list, modal, AJAX)
├── compare.css                    # Modal/table styling
├── woocommerce-product-compare-simple.php
├── README.md
```

---

## ✅ Roadmap / To-Do

* Persist compare list to user meta (for logged-in users)
* Add support for short description comparison
* Enable comparison from single product pages
* Add “Highlight differences” toggle
* Export table as PDF or print view

---

## 👨‍💻 Author

**Lime Advertising**
[https://limeadvertising.com](https://limeadvertising.com)

---