# WooCommerce Product Compare (Simple)

Adds a **Compare** button to WooCommerce shop pages, allowing customers to compare up to **4 products** in a responsive modal table. Includes features like product image, price, categories, SKU, affiliate availability, and global attributes.

---

## 📦 Features

- Add **Compare** buttons on product loop items (archive pages)
- Compare up to **4 WooCommerce products** in a modal
- Display the following comparison data:
  - Product Image
  - Price
  - Category
  - SKU
  - Affiliate availability (with logo & link)
  - Global attributes (e.g., Color, Size, etc.)
- Compatible with **external products** and global attributes
- Fully responsive and styled with CSS
- User state stored in localStorage to persist comparison list

---

## 🚀 Installation

1. Upload the plugin folder to `/wp-content/plugins/woocommerce-product-compare-simple`
2. Activate the plugin from the WordPress admin panel
3. No setup required — it works automatically on product archive pages

---

## 🔧 Usage

### Compare Button Placement

The plugin automatically adds a button group under each product (on archive pages):

- **View**: links to the product page
- **Compare**: adds the product to the comparison list

### Compare Modal

Clicking “Compare” will:

- Store the selected product ID in `localStorage`
- Open a modal showing all selected products side-by-side
- Display available information, including:
  - Product image
  - Price
  - Categories
  - SKU
  - Additional affiliate links (if available via ACF or custom field `_additional_affiliate_links`)
  - Global product attributes

---

## 🧠 Custom Field Format for Affiliate Links

To show logos for "Available In" column, create a **custom field** named:


`\_additional\_affiliate\_links`


Its value must be a serialized array of objects with keys `name` and `url`. Example:

```php
[
    [
        "name" => "Amazon",
        "url" => "https://amazon.com/example"
    ],
    [
        "name" => "BestBuy",
        "url" => "https://bestbuy.com/example"
    ]
]
````

Supported store names with logos:

* `Amazon`
* `BestBuy`
* `HomeDepot`
* `Rona`
* `HOD`

Others will fallback to displaying the name as plain text.

---

## 🧩 Shortcode

You can manually place the Compare button with:

```php
[compare_button]
```

This requires the global `$product` to be available (e.g., inside WooCommerce loops or single product templates).

---

## 🧼 Clear / Remove Actions

* “Remove” button on each product column removes it from comparison
* “Clear All” button resets the compare list and closes the modal

---

## 🖥 Styling

Uses the `compare.css` file included in the plugin. Key styles:

* Equal-width product columns
* Responsive scroll behavior on smaller screens
* Adjustable thumbnail height and alignment
* Uses CSS variables for color and font theming (e.g., `--wdtPrimaryColor`, `--wdtSecondaryColor`)

---

## ⚙️ Technical Notes

* Stores comparison list in `localStorage` (`compareList`)
* Modal open state is tracked with `wcp_open_modal` flag in `localStorage`
* Product data fetched via AJAX from `get_compare_data` action
* Uses `wc_get_product_terms()` to display global attribute terms
* Compatible with **external products** and **global WooCommerce attributes**

---

## 📁 File Structure

```
woocommerce-product-compare-simple/
├── compare.js         # JS logic for compare state and modal
├── compare.css        # All styling for modal and table
├── woocommerce-product-compare-simple.php
├── README.md
```

---

## 🧑‍💻 Author

**Lime Advertising**
[https://limeadvertising.com](https://limeadvertising.com)

---

## ✅ To-Do / Future Enhancements

* Add option to persist comparison list across sessions (user meta)
* Include short descriptions or additional meta
* Enable compare from product page (single)

---
