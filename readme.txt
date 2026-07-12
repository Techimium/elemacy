=== Elemacy — All-in-One Elementor Addon (Theme Builder, Popups, Forms, Loop Builder, Dynamic Tags) ===
Contributors: techimium, mdashraful
Tags: elementor, elementor addons, theme builder, popup builder, dynamic tags
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
Requires Plugins: elementor
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One Elementor addon to replace them all. Theme Builder, Display Conditions, Popups & Floating Elements, Loop Builder, Form Builder, Dynamic Tags, Custom CSS, and a suite of widgets — all free.

== Description ==

**Elemacy is a powerful, all-in-one Elementor addon** that gives you a complete website-building suite — without installing a dozen separate plugins. Build custom headers, footers, single post layouts, and archive pages with the Theme Builder. Assign templates with display conditions. Create popups, banners, top bars, and floating elements. Build contact forms, dynamic post loops, navigation menus, and use real WordPress and ACF data anywhere with dynamic tags.

Stop installing five different plugins to get five different features. Elemacy brings everything together in one lean, well-coded package. The core feature set is completely free; advanced capabilities are available in **Elemacy Pro**.

> Works with the **free version of Elementor** — Elementor Pro is **not** required.

---

### 🏆 What's Included in the Free Version?

Elemacy gives you a genuinely powerful set of features at no cost. No credit card, no trial period — just install and build:

- ✅ **Theme Builder** (Header, Footer, Single, Archive, 404, Search, Loop, CPT templates)
- ✅ **Display Conditions** (assign templates to specific parts of your site)
- ✅ **Popups & Floating Elements** (popups, top/bottom bars, banners, floating elements)
- ✅ **Loop Builder** (dynamic Grid & Carousel with AJAX pagination)
- ✅ **Form Builder** with email notifications
- ✅ **Custom CSS** per widget / section / column / page
- ✅ **Dynamic Tags** (Post, Site, Archive, Author, Utility, WooCommerce & ACF data)
- ✅ **Widgets** — Nav Menu, Site Logo, Search, ACF Accordion, ACF Icon List, ACF Gallery
- ✅ **Modular architecture** — enable only the features you need

---

### 🎨 Theme Builder

Take complete control of every part of your WordPress theme using Elementor's drag-and-drop editor. Elemacy hooks into the standard WordPress template system, so it works with virtually **any theme**.

**Build custom templates for:**

* **Header** – Design a pixel-perfect header and replace your theme's default header entirely.
* **Footer** – Create a custom footer with widgets, menus, social links, and anything Elementor supports.
* **Single Post / Page** – Design a unique layout for individual posts, pages, or any custom post type.
* **Archive Pages** – Build custom archive layouts for blog, categories, tags, products, or custom post type archives.
* **Post Archive** – Dedicated template for your WordPress blog post listing page.
* **404 Page** – Turn your error page into a branded, conversion-optimized experience.
* **Search Results** – Customize how search results are presented to visitors.
* **Loop Template** – Design the individual card/item template used by the Loop Builder widget.
* **Custom Post Type Support** – Automatically detects all registered public custom post types and generates Single and Archive template options for each.

**Theme compatibility** is handled out of the box for popular themes including Astra, Blocksy, GeneratePress, Genesis, Kadence, Neve, OceanWP, Storefront, and the Beaver Builder Theme, with a sensible default for everything else.

---

### 🎯 Display Conditions

Assign your Theme Builder templates exactly where they belong. Each template can have one or more **Include** ("show on") and **Exclude** ("hide on") rules. A template with no conditions acts as a global fallback.

**Conditions available in the free version:**

* **Entire Site** – Apply a template everywhere.
* **Singular Post Type** – Apply to all items of a specific post type (posts, pages, products, custom post types).
* **Post Type Archive** – Apply to the archive of a specific post type.

The conditions builder also previews the advanced conditions unlocked by **Elemacy Pro** (Front Page, Blog, 404, Search Results, By Author, Specific Post, Child Of, taxonomy term/archive conditions, date & author archives, and more).

---

### 💬 Popups & Floating Elements

Create high-converting popups, top/bottom bars, banners, and floating elements — all designed in Elementor and managed from the Elemacy dashboard. (This module is included free; enable it from the Modules page.)

**One module, four element types:**

* **Popup** – Classic centered modal with overlay.
* **Top / Bottom Bar** – Site-wide notification bars.
* **Banner** – A dismissible, fully positionable bar.
* **Floating Element** – Pin any design to a corner or edge of the screen.

**Design & behavior:**

* Built on a **custom Elementor document type** — design the content and styling visually in the Elementor editor (size, position, overlay color & opacity, animation, close button styling, auto-close, prevent-scroll, z-index, and more), with a live framed preview.
* **Display conditions** – Target popups using the same conditions engine as the Theme Builder.
* **Triggers (free):** Page Load, Click.
* **Rules (free):** Frequency Cap ("show up to X times"), Hide for Logged-in Users.
* Lightweight, vanilla-JS frontend engine that loads **only when a popup actually matches the current page** — zero overhead otherwise.

> **⚡ Elemacy Pro** adds advanced triggers (Scroll, Exit Intent, Inactivity, Scroll to Element, On Popup Close), advanced rules (Once Per period, Devices, Schedule, Page Views, Sessions, Referrer, User Roles, Browser), plus popup analytics.

---

### 🔁 Loop Builder Widget

Display any post type — including custom post types — in a dynamic, fully customizable grid or carousel. No coding required.

**Loop Grid features:**

* Select any Elementor template as the loop item design
* Choose any public post type as your data source (including custom post types)
* Query builder: posts per page, offset, order by (date, title, menu order, random), and sort direction (ASC/DESC)
* Exclude the current post from the loop automatically
* Built-in **AJAX Pagination** — page through results without a full reload
* Pagination styles: Numbers, Previous/Next, or Numbers + Previous/Next
* Responsive column control (1–12 columns per breakpoint)
* Responsive column gap and row gap controls
* Full pagination styling: colors (normal/hover/active), typography, border, border-radius, padding

**Loop Carousel features:**

* All the power of the Loop Grid in a smooth, swipeable carousel format
* Ideal for testimonials, product showcases, team members, and more

---

### 📋 Form Builder Widget

Build fully custom contact forms — or any kind of form — directly inside Elementor. No third-party form plugin needed.

**Supported field types:** Text, Email, Textarea, Telephone, Select (dropdown), Radio buttons, Checkbox.

**Form configuration:**

* Drag-and-drop field ordering with a repeater control
* Per-field label, placeholder, required status, and column width (20%–100%)
* Show or hide field labels
* Custom field IDs for use in email templates
* Configurable option lists for Select, Radio, and Checkbox fields

**Actions after submit:**

* **Email Notification** – Send submissions to any address(es). Configure To, Subject, Message body (with the `[all-fields]` shortcode or specific `[field_id]` placeholders), From Name, From Email, and Reply-To.

**Full styling controls** for layout, labels, input fields, the submit button, and success/error messages.

> **⚡ Coming Soon:** Form submission storage — save every submission to your database and view them in the admin dashboard.

---

### 🧩 Widgets

In addition to the Loop Builder and Form Builder, Elemacy ships a growing library of widgets:

* **Site Logo** – Output your site logo with a custom link target.
* **Elemacy Nav Menu** – Fully-featured, responsive navigation menu (details below).
* **Search** – A flexible search form widget.
* **ACF Accordion** – Build accordions from an ACF repeater field. *(requires ACF)*
* **ACF Icon List** – Render an icon list from an ACF repeater field. *(requires ACF)*
* **ACF Gallery** – Display an ACF gallery field. *(requires ACF)*

---

### 🧭 Nav Menu Widget

A fully-featured, highly customizable navigation menu widget built from the ground up for Elementor.

**Layout options:** Horizontal, Vertical, and Stacked (full width).

**Mobile responsiveness:**

* Configurable mobile breakpoint: None, Mobile only, or Tablet & below
* Hamburger/toggle button with custom open and close icons
* Toggle label with show/hide control and full typography/color styling
* Toggle alignment control (start/center/end)

**Styling controls:**

* Main menu: typography, text color (normal/hover/active), background color, item gap, item padding, border, border radius
* Dropdown/submenu: panel background, padding, border, border radius, box shadow, typography, link colors (normal/hover/active), item padding
* Toggle button: color, background, border, typography, padding, border radius (normal/hover)

---

### 🏷️ Dynamic Tags

Make your Elementor designs truly dynamic. Use real data from your WordPress site anywhere a dynamic field is supported.

**Post dynamic tags:**

* Post Title, Post URL, Post Content, Post Excerpt, Post Date (with format control), Post Featured Image, Post Custom Field, Post Terms, Comments Number, Page Title

**Site dynamic tags:**

* Site Title, Site Tagline, Site URL, Site Logo

**Archive & Author dynamic tags:**

* Archive Title, Archive Description, Archive URL
* Author Name, Author Info, Author URL, Author Profile Picture

**Utility dynamic tags:**

* Shortcode, Request Parameter, Current Date/Time, Contact URL (mailto / tel)

**WooCommerce dynamic tags** *(active when WooCommerce is installed):*

* Product Price, Product SKU, Product Stock, Product Rating, Product Short Description, Product Gallery, Add-to-Cart URL

**Advanced Custom Fields (ACF) dynamic tags** *(requires ACF):*

* ACF Field (text), ACF Image, ACF Gallery, ACF Color, ACF Date/Time, ACF Number, ACF URL

> **⚡ Coming Soon:** Even more dynamic tags, including user data.

---

### 🎯 Custom CSS Control

Write custom CSS for any Elementor widget, section, column, or page — right inside the Elementor panel. No child theme required.

* Adds a **Custom CSS** tab to the Advanced panel of every Elementor element
* Use the `selector` keyword to target the element's unique CSS class automatically
* Also available at the **Page Settings** level for page-specific styles
* CSS is compiled into Elementor's generated stylesheet — no render-blocking inline styles

---

### ⚙️ Module Management

Elemacy includes a modern admin dashboard where you can enable or disable individual feature modules. Only load what you need — keeping your site fast and bloat-free.

**Available modules:**

* Theme Builder (with Display Conditions)
* Widgets (Nav Menu, Site Logo, Search, Loop Grid, Loop Carousel, Form Builder, ACF widgets)
* Dynamic Tags
* Custom CSS Controls
* Popups & Floating Elements

---

### ⭐ Elemacy Pro

Elemacy Pro installs on top of the free plugin and unlocks advanced features. Nothing in the free plugin is crippled — Pro purely **adds** capabilities.

* **Animations** – Add scroll, entrance, and on-load animations to any Elementor element with no code. Presets, configurable trigger (On View / On Page Load), duration, delay, easing, viewport threshold, and reduced-motion handling. Powered by GSAP.
* **Advanced Display Conditions** – Front Page, Blog, 404, Search Results, By Author, Specific Post, Child Of, In {Taxonomy}, {Taxonomy} Archive, All Archives, Date Archive, and Author Archive.
* **Advanced Popup Triggers** – Scroll, Exit Intent, Inactivity, Scroll to Element, and On Popup Close.
* **Advanced Popup Rules** – Once Per period, Devices, Schedule, Page Views, Sessions, Referrer, User Roles, and Browser targeting.
* **Popup Analytics** – Track impressions and conversions.
* **License management** – In-dashboard license activation for updates and support.

> 👉 Learn more and upgrade at [elemacy.com](https://elemacy.com).

---

### 🔌 Compatibility

* **WordPress** 6.0+
* **Elementor** (Free) — latest version recommended
* **PHP** 7.4+
* **Advanced Custom Fields (ACF)** — optional, required only for the ACF widgets and ACF dynamic tags
* **WooCommerce** — Theme Builder detects the Shop page when resolving archive templates

---

### 🗺️ Roadmap

We're actively developing Elemacy. Here's what's coming:

* 📥 **Form Submission Storage** – Save all form entries to your database and manage them from the admin
* 🏷️ **More Dynamic Tags** – user & role data, and more
* 🧩 **More Widgets** – New, powerful widgets added regularly
* 🔗 **External Integrations** – Connect form submissions to email marketing tools and CRMs

---

### 👥 Contributing

Elemacy is open source and we welcome contributions from the WordPress and Elementor developer community.

**GitHub Repository:** [https://github.com/Techimium/elemacy](https://github.com/Techimium/elemacy)

Whether you want to submit a bug fix, add a new widget, propose a feature, or improve documentation — we'd love your help.

---

== Installation ==

**Automatic Installation (Recommended)**

1. Log in to your WordPress admin dashboard.
2. Navigate to **Plugins → Add New**.
3. Search for **"Elemacy"**.
4. Click **Install Now**, then **Activate**.

**Manual Installation**

1. Download the plugin ZIP file.
2. Log in to your WordPress admin dashboard.
3. Navigate to **Plugins → Add New → Upload Plugin**.
4. Upload the ZIP file and click **Install Now**.
5. Click **Activate Plugin**.

**After Activation**

1. Make sure **Elementor** is installed and activated.
2. Navigate to **Elemacy** in your WordPress admin menu.
3. Enable the modules you need from the Modules management page.
4. Start building with Elementor — all Elemacy widgets and features will appear automatically.

== Frequently Asked Questions ==

= Does Elemacy require Elementor Pro? =

No. Elemacy works with the **free version of Elementor**. You do not need Elementor Pro to use any of Elemacy's features.

= Is the free version really free? =

Yes. Every feature documented under the free version is available at no cost — no hidden limits, no trial periods. Elemacy is open source software released under the GPLv2 license.

= What's the difference between Elemacy and Elemacy Pro? =

Elemacy (free) gives you the complete core toolkit: Theme Builder, basic display conditions, popups with the essential triggers and rules, the Loop and Form builders, dynamic tags, custom CSS, and the widget library. Elemacy Pro adds the Animations module, advanced display conditions, advanced popup triggers/rules, popup analytics, and license-based updates and support. Pro never replaces free code — it only adds to it.

= Does the Theme Builder work with any WordPress theme? =

Yes. The Theme Builder hooks into the standard WordPress template system to override headers, footers, and content templates theme-agnostically, with dedicated compatibility for many popular themes (Astra, Blocksy, GeneratePress, Genesis, Kadence, Neve, OceanWP, Storefront, Beaver Builder Theme).

= How do display conditions work? =

Each Theme Builder template can have Include ("show on") and Exclude ("hide on") rules. The free version covers Entire Site, Singular Post Type, and Post Type Archive. Elemacy Pro unlocks many more (Front Page, Blog, Search Results, 404, taxonomy and author/date archives, specific posts, and more).

= Does the Loop Builder require any special setup? =

To use the Loop Builder, first create a **Loop template** with the Theme Builder (select "Loop" as the template type). Then assign that template inside the Loop Builder widget. The Loop template designs each individual item (card) in your loop.

= Can I use the ACF widgets and ACF dynamic tags without ACF installed? =

No. The ACF widgets (ACF Accordion, ACF Icon List, ACF Gallery) and the ACF dynamic tags require the **Advanced Custom Fields** plugin (the free version from WordPress.org works fine). If ACF is not installed, those widgets and tags simply won't appear.

= What field types does the Form Builder support? =

Text, Email, Textarea, Telephone, Select (dropdown), Radio buttons, and Checkbox. More field types are planned.

= Does the Form Builder store submissions? =

The current version sends submissions via email. **Form submission storage** (saving entries to the database) is on the roadmap.

= Will Elemacy slow down my website? =

Elemacy is built with performance in mind. It uses a modular architecture — disable any module you don't need — and assets such as the popup engine only load when they're actually required on a page.

= Is Elemacy compatible with WooCommerce? =

Yes. When WooCommerce is active, Elemacy adds a set of WooCommerce **dynamic tags** (Product Price, SKU, Stock, Rating, Short Description, Gallery, Add-to-Cart URL), and the Theme Builder detects the WooCommerce Shop page when resolving archive templates. Dedicated WooCommerce widgets are planned for a future release.

= Can I contribute to Elemacy? =

Absolutely! Elemacy is open source. Visit our GitHub repository (link in the plugin description) to report issues, submit pull requests, or propose new features.

= Where can I get support? =

Use the official WordPress.org support forum for the free plugin. For bug reports and feature requests you can also open an issue on our GitHub repository. Elemacy Pro customers receive priority support through elemacy.com.

== Screenshots ==

1. **Elemacy Dashboard** – The clean, modern admin dashboard where you manage all modules.
2. **Theme Builder** – Create and manage custom theme templates for every part of your site.
3. **Display Conditions** – Assign templates to specific pages, post types, and archives.
4. **Popups & Floating Elements** – Build popups, bars, banners, and floating elements with triggers and rules.
5. **Loop Builder Widget** – Configure dynamic post queries and select your custom loop template.
6. **Form Builder Widget** – Drag-and-drop form field configuration in the Elementor panel.
7. **Dynamic Tags** – Use real WordPress and ACF data anywhere in your Elementor designs.
8. **Custom CSS Control** – Write per-element custom CSS directly inside the Elementor Advanced tab.
9. **Nav Menu Widget** – Fully styled, mobile-responsive navigation menu with dropdown support.

== Changelog ==

= 1.0.0 =
* Initial release
* Theme Builder: Header, Footer, Single, Archive, Post Archive, 404, Search Results, Loop, and Custom Post Type templates, with multi-theme compatibility
* Display Conditions: Entire Site, Singular Post Type, and Post Type Archive (advanced conditions available in Elemacy Pro)
* Popups & Floating Elements: popups, top/bottom bars, banners, and floating elements designed in Elementor, with display conditions, Page Load/Click triggers, and Frequency Cap/Logged-in Users rules
* Loop Builder Grid widget with AJAX pagination, query builder, and responsive columns
* Loop Carousel widget
* Form Builder widget with Text, Email, Textarea, Tel, Select, Radio, and Checkbox fields; Email action with full configuration
* Nav Menu widget with horizontal/vertical/stacked layouts, mobile toggle, dropdown submenus, and comprehensive styling controls
* Site Logo and Search widgets
* ACF widgets: ACF Accordion, ACF Icon List, ACF Gallery (require ACF)
* Custom CSS control added to the Advanced tab of all Elementor elements and to page settings
* Dynamic Tags: Post Title, Post URL, Post Content, Post Excerpt, Post Date, Post Featured Image, Post Custom Field, Post Terms, Comments Number, Page Title, Site Title, Site Tagline, Site URL, Site Logo, Archive Title/Description/URL, Author Name/Info/URL/Profile Picture, Shortcode, Request Parameter, Current Date/Time, Contact URL
* Dynamic Tags (WooCommerce, when active): Product Price, SKU, Stock, Rating, Short Description, Gallery, Add-to-Cart URL
* Dynamic Tags (ACF): ACF Field, ACF Image, ACF Gallery, ACF Color, ACF DateTime, ACF Number, ACF URL
* Module management admin page — enable or disable individual feature modules

== Upgrade Notice ==

= 1.0.0 =
Initial release of Elemacy. Install and activate to get started with the free, all-in-one Elementor addon.
