=== Elemacy – Theme Builder, Popups, Forms & Loops for Elementor ===
Contributors: techimium, mdashraful
Tags: elementor, theme builder, popup builder, form builder, loop builder
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
Requires Plugins: elementor
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build headers, popups, forms and dynamic content with one free Elementor addon. Includes Theme Builder, Loop Grid, ACF tools and Custom CSS.

== Description ==

**More building. Less plugin juggling.**

You have a page you love. Now you need a header that matches, a contact form, and a way to show your latest work. Each next step seems to mean another plugin, another settings screen, and another design to keep consistent.

**Elemacy brings those everyday building jobs into one Elementor addon.** Design the site around your pages, turn your content into reusable layouts, and give visitors an easy next step. Keep working in the Elementor editor you already know.

**Start free with:**

* **Theme Builder** — create headers, footers, single post layouts, archives, search results and a custom 404 page.
* **Popup Builder** — design popups, top or bottom bars, banners and floating elements, with page-load and click triggers.
* **Form Builder** — build contact and inquiry forms with 14 field types and email notifications.
* **Loop Grid and Loop Carousel** — design one card, then fill it with posts, categories or users. Add AJAX pagination, Load More or Infinite Scroll to grids.
* **Dynamic Tags and ACF widgets** — connect designs to WordPress content and custom fields instead of retyping the same information.
* **Navigation, search and finishing touches** — add a responsive nav menu, site logo, search form and Custom CSS.

**Works with the free version of Elementor. Elementor Pro is not required.** ACF tools need Advanced Custom Fields; repeater and gallery features need those field types, such as those provided by ACF Pro. Elemacy Pro is an optional, separate addon for advanced targeting, popup analytics and custom ACF repeater loops.

= Make the whole site feel like yours: Elementor Theme Builder =

Start with the parts visitors see on every page. Build a header with your logo and navigation, create a useful footer, and carry the same design into your blog and other content.

Elemacy's free Theme Builder includes:

* Headers and footers.
* Single post and page layouts, including custom post types.
* Archive layouts for your blog and post types.
* Search results templates.
* Custom 404 pages.
* Preview settings for single, archive and search templates, so you can design with real content.

Use **display conditions** to choose where each template belongs. Free includes Entire Site, Singular Post Type, Post Type Archive and Search Results conditions, with include and exclude rules. A new template starts as a draft; the published 404 template applies to missing pages without a separate condition.

The template list shows where designs apply and flags missing include rules or templates overridden by a newer design. Keep reusable sections and Loop Item templates in the **Template Library**, ready for the next layout.

Classic and block themes are supported, with dedicated compatibility for Hello Elementor, Astra, Blocksy, GeneratePress, Kadence, Neve, OceanWP, Storefront, Genesis and Beaver Builder Theme.

= Design one card. Let your content do the rest: Loop Builder =

Your latest stories should appear without rebuilding a row of cards. Your team directory should use the same design for every person. Create a Loop Item template once, then choose what fills it.

**Free loop data sources:**

* **Posts** — display posts, pages or public custom post types. Set the item count, order, offset and whether to exclude the current post.
* **Current Query** — follow the content WordPress is already showing on an archive, search results, blog or shop page.
* **Terms** — create grids of categories, tags or other public taxonomy terms, with ordering and an option to hide empty terms.
* **Users** — build author or team directories, filter by role, set the order and optionally exclude the current user.

The **Loop Grid** gives you responsive columns and spacing. Choose page numbers, Previous/Next links, or both, with optional AJAX loading. Use a **Load More** button or **Infinite Scroll** when you want the next items to appear in the same grid.

The **Loop Carousel** presents your loop design as a swipeable slider. Set slides per view, slides to scroll, equal height, autoplay, pause on hover, infinite looping, transition speed, arrows and pagination dots.

Nested loops let a layout contain another loop. With **Elemacy Pro**, an ACF repeater can also supply the rows, using your own Loop Item design.

= A form that belongs in your design: Form Builder =

When someone is ready to get in touch, keep that conversation on your page. Drop in the Form Builder, choose the fields you need, and style the form alongside the rest of the design.

**Included field types:** Text, Email, Textarea, URL, Telephone, Number, Date, Time, Select, Radio, Checkbox, Acceptance, Hidden and HTML.

Arrange fields, set column widths, add labels and placeholders where applicable, and mark fields as required. Use acceptance checkboxes for an agreement, hidden fields for extra context, and HTML fields for helpful text between inputs. Style the labels, inputs, submit button and response messages.

The built-in **Email** action sends submissions to your chosen recipients. Set the subject, sender, Reply-To and message. Use `[all-fields]` to include submitted values or `[field id="email"]` to insert one field by its ID.

Forms include server-side field validation, a honeypot and submission rate limiting. Email delivery uses your WordPress mail configuration. **Submission storage, file uploads, payments and built-in CRM or email-marketing integrations are not included in this release.**

= Give the right message its own moment: Popup Builder =

A small announcement can be enough. A contact form might need more room. Use the same visual editor for both, then decide how the message appears.

Choose from four formats:

* **Popup** — a modal with an overlay.
* **Top / Bottom Bar** — a notice at the top or bottom of the screen.
* **Banner** — a dismissible, positionable message.
* **Floating Element** — a design pinned near an edge or corner.

Adjust width, position, overlay color and opacity, background blur, entrance and exit animation, close button, auto-close and scroll behavior in the popup document settings.

**Free triggers:** open on page load, with a configurable delay, or from a click. **Free rules:** limit how many times a popup appears and hide it from logged-in visitors. Popup display conditions use the same free and Pro condition choices as theme templates.

To open a popup from a button, set its link to `#elemacy-popup-42`, replacing `42` with the popup ID. A link to `#elemacy-popup-close` closes it from inside the popup.

= Keep the content connected: Dynamic Tags and ACF =

Write a post title once. Use it in the post layout, its archive card and other dynamic elements. When the content changes, the design reads the updated value.

Elemacy's free dynamic tags include:

* **Post data:** title, content, excerpt, URL, date, featured image, custom fields, terms and comment count, plus Page Title.
* **Site data:** site title, tagline, URL and logo.
* **Archive and taxonomy data:** archive title, description and URL; term name, description, URL, count and custom fields.
* **Author and user data:** names, biographies, profile links and avatars, plus user login, email and post count. User tags can read a Users loop item or the author archive being viewed.
* **Useful values:** shortcodes, request parameters, current date/time and contact URLs.
* **ACF data:** field values, images, galleries, colors, dates/times, numbers and URLs. These need ACF and the relevant field type.
* **WooCommerce data:** product price, SKU, stock, rating, short description, gallery and add-to-cart URL when WooCommerce is active.

Three free **ACF widgets** turn existing fields into finished content:

* **ACF Accordion** maps repeater questions and answers into an FAQ or other accordion, with an optional FAQ Schema setting.
* **ACF Icon List** displays repeater text with a shared icon and consistent styling.
* **ACF Gallery** displays images from a gallery field in a styled grid.

The widgets are included in Elemacy Free. Repeater and gallery fields are separate ACF capabilities, usually supplied by **ACF Pro**. An **Elemacy Pro** license does not include ACF Pro.

= Help people find their way: Navigation, search and site identity =

Use the **Elemacy Nav Menu** widget for horizontal, vertical or dropdown navigation. Choose pointers and hover animations, style submenus, and set the breakpoint for a responsive hamburger menu. Full-width dropdowns, custom toggle icons, dividers and normal/hover/active styling help the menu fit your design. Submenus support mouse, touch and keyboard interaction.

The **Search** widget offers Classic, Minimal and Full Screen layouts. Customize the placeholder, icon or button text and styling, and optionally limit searches to selected public post types.

The **Site Logo** widget uses your Customizer logo or a custom image, with homepage, image-file, custom-URL or no-link options.

For the last few details, add **Custom CSS** to supported Elementor widgets, containers, sections, columns or page settings. The `selector` keyword targets the current element. CSS knowledge is only needed when you choose to use this tool.

= Keep the toolkit comfortable to work with =

Enable the modules you need from the Elemacy dashboard and switch individual widgets on or off. Theme Builder, Widgets, Dynamic Tags, Custom CSS and Popups are managed together, so you have one place to shape your toolkit.

Popup runtime assets load when a popup matches the page. Widget assets are registered as dependencies of the widgets that use them. Your theme, content and other plugins still affect overall site performance.

= When you need finer control: Elemacy Pro =

Elemacy Free includes the builders described above. **Elemacy Pro is a separate paid addon** that extends them with:

* **Advanced display conditions** — front page, blog, 404 targeting, search results by post type, specific posts, posts by author, child pages, taxonomy conditions, and date or author archives.
* **Advanced popup triggers** — scroll percentage, exit intent, inactivity, reaching an element and closing another popup.
* **Advanced popup rules** — repeat intervals, devices, scheduling, page views, sessions, referrer, user roles and browser targeting.
* **Popup analytics** — review impression and conversion counts for your campaigns.
* **ACF Repeater loops** — use repeater rows in Loop Grid and Loop Carousel with a custom Loop Item template. Read from the current post, a specific post/page, an ACF options page or a parent repeater row for nested layouts. ACF repeater support is required; options-page sources also require that ACF capability.
* **Pro updates and priority support** with an active license. Pro features remain available when the Pro plugin is active; licensing controls access to updates and support.

Keep Elemacy Free installed and active when using Elemacy Pro. The free download does not include the Pro addon or its features. [Explore Elemacy Pro](https://elemacy.com).

= Start with one thing you want to improve =

Replace a header. Give a contact form some care. Make one card and let a loop fill the rest. You can enable more of the toolkit as your site grows.

**Install Elemacy, open its dashboard, and start with the part your site needs next.**

== Installation ==

1. Install and activate **Elementor 3.35.9 or newer**.
2. In WordPress, go to **Plugins → Add New**, search for **Elemacy**, then install and activate it. You can also upload the Elemacy plugin ZIP from **Plugins → Add New → Upload Plugin**.
3. Open **Elemacy** in the admin menu and enable the modules and widgets you want to use.
4. Edit a page with Elementor, or create a template from the Theme Builder or Template Library.
5. For ACF features, activate Advanced Custom Fields and create the required fields. Repeater and gallery tools need the matching field-type support, such as ACF Pro.

Requires WordPress 6.0+, PHP 7.4+ and Elementor 3.35.9+. Elementor Pro is optional. Elemacy Pro requires Elemacy Free 1.0.0 or newer.

== Frequently Asked Questions ==

= Do I need Elementor Pro? =

No. Elemacy's builders and widgets work with the free version of Elementor, version 3.35.9 or newer. ACF and WooCommerce features need those plugins and their relevant capabilities.

= What is free, and what needs Elemacy Pro? =

Free includes Theme Builder, the basic display conditions, Popup Builder with page-load/click triggers and the two free visibility rules, Form Builder, Loop Grid, Loop Carousel, all nine Elemacy widgets, Dynamic Tags, Custom CSS and the Template Library. Pro adds advanced conditions, popup triggers and rules, popup analytics, and ACF Repeater loop sources. The Pro addon is a separate purchase.

= Can I build a header and footer with free Elementor? =

Yes. Create Header and Footer templates in Elemacy's Theme Builder, design them in Elementor, set their display conditions and publish them. Add Site Logo and Elemacy Nav Menu widgets to complete your header.

= How do I build a post grid or carousel? =

Create a Loop Item in the Template Library and design one card with dynamic tags. Add Loop Builder or Loop Carousel to a page, choose that template and select a data source. Posts, Current Query, Terms and Users are included free. ACF Repeater rows require Elemacy Pro and ACF repeater support.

= How do display conditions work? =

Include rules choose where a template or popup appears; any matching exclude rule prevents it from appearing. Free includes Entire Site, Singular Post Type, Post Type Archive and Search Results. Other conditions shown as Pro options need Elemacy Pro. Theme templates normally need a matching include rule. The 404 template is a location-based exception: once published, it applies to missing pages without separate rules.

= Does it work with my theme or alongside Elementor Pro? =

Elemacy supports classic and block themes, with dedicated adapters for the themes listed above. Preview the result with your own theme before publishing. If an Elementor Pro Theme Builder template already applies to a header, footer, single or archive location, Elemacy leaves that location to Elementor Pro.

= Do the ACF widgets require ACF Pro or Elemacy Pro? =

ACF Accordion, ACF Icon List and ACF Gallery are Elemacy Free widgets. They need ACF plus the relevant repeater or gallery field type; these field types are supplied by ACF Pro or compatible field-type support. Elemacy Pro is needed when you want a custom Loop Item template for ACF repeater rows. ACF Pro and Elemacy Pro are separate products.

= What form fields and actions are available? =

The current release includes Text, Email, Textarea, URL, Telephone, Number, Date, Time, Select, Radio, Checkbox, Acceptance, Hidden and HTML. The built-in action is Email. File uploads, payments, saved submission entries and built-in CRM or mailing-list integrations are not included.

= Why am I not receiving form emails? =

Check the Email action's recipient and sender settings, then check your spam folder and your site's WordPress email delivery. Elemacy uses `wp_mail()`, so delivery depends on the site's mail configuration. An SMTP or transactional-mail plugin may help if your host does not deliver WordPress mail reliably.

= Can I open a popup from a button or link? =

Yes. Link the button to `#elemacy-popup-42`, replacing `42` with the popup's ID. Inside a popup, a link to `#elemacy-popup-close` or the CSS class `elemacy-popup-close` closes it. Developers can also open a popup with `window.elemacyPopups.open(42)`.

= Can I use WooCommerce products in my designs? =

Yes. When WooCommerce is active, Elemacy provides the product dynamic tags listed above. The Theme Builder recognizes the shop archive, and loops can display public product posts or follow the shop's Current Query. This release does not include dedicated cart or checkout widgets.

= Do I need an Elemacy account for the free features? =

No. The free toolkit runs on your WordPress site without an Elemacy account. Optional Elemacy Pro license and update checks connect to Elemacy's licensing service. Form emails use your site's configured mail service.

= What happens if I deactivate or delete the plugin? =

Deactivation keeps your saved content, but Elemacy templates and widgets need the plugin to render their functionality. Deleting Elemacy also keeps its templates, popups and settings by default. To remove owned data on deletion, first add `define( 'ELEMACY_REMOVE_ALL_DATA', true );` to `wp-config.php`. Pro follows the same opt-in rule for popup analytics and its remaining settings; its license and temporary data are removed on deletion.

= Where can I get help or contribute? =

Use the WordPress.org support forum for Elemacy Free. Report bugs or contribute at [GitHub](https://github.com/Techimium/elemacy). Elemacy Pro customers with an active license receive priority support through [elemacy.com](https://elemacy.com).

== Screenshots ==

1. Modules — enable the Theme Builder, Dynamic Tags, Widgets, Custom CSS, and Popups tools you need.
2. Theme Builder — manage headers, footers, single posts, archives, search results, and other site templates.
3. Display Conditions — assign theme templates using include and exclude rules. Advanced conditions are available with Elemacy Pro.
4. Template Library — organize reusable page templates and loop item designs.
5. Form Builder — design a studio contact form in Elementor with flexible fields, column widths, and email notifications.
6. Popup Editor — design a newsletter popup and configure its width, position, overlay, animation, and close behavior in Elementor.
7. Popup Formats — choose a popup, top or bottom bar, banner, or floating element.
8. Popup Display Conditions — control where a popup appears. Advanced conditions are available with Elemacy Pro.
9. Popup Triggers — configure when a popup appears. Page-load and click triggers are included free; advanced triggers require Elemacy Pro.
10. Elemacy Nav Menu — style a WordPress menu with pointers, submenus, and responsive dropdown settings.
11. Loop Builder — display WordPress posts in a responsive grid using your own loop item template.
12. Loop Carousel — present dynamic content in a carousel with arrows, pagination, and behavior controls.
13. Dynamic Tags — connect Elementor designs to WordPress post, site, archive, author, and ACF data.
14. Custom CSS — add per-element styling with the selector keyword directly in the Elementor Advanced panel.
15. Search — design a search form with a custom placeholder, button text, and layout.
16. Site Logo — place a site logo or custom image in your Elementor layout.
17. ACF Accordion — map repeater questions and answers to an accordion. Requires ACF repeater field support, such as ACF Pro.
18. ACF Icon List — display repeater values with a consistent icon and styling. Requires ACF repeater field support, such as ACF Pro.
19. ACF Gallery — display a styled image grid from an ACF gallery field. Requires gallery field support, such as ACF Pro.
20. Widget Library — browse and enable Elemacy widgets, including navigation, forms, loops, search, and ACF widgets.
21. Loop Pagination — choose AJAX page links, Load More, or Infinite Scroll for your content grid.
22. Elemacy Pro: Advanced Popup Rules — refine campaign visibility with advanced visitor and timing rules.
23. Elemacy Pro: Popup Analytics — review impressions and conversions for your campaigns. Counts shown are illustrative demo data.
24. Elemacy Pro: ACF Repeater Loops — display repeater rows with a custom loop item template. Requires ACF repeater field support.

== Changelog ==

= 1.0.0 =
* Initial release
* Theme Builder: Header, Footer, Single, Archive, 404 and Search Results templates for classic and block themes, with support for public custom post types and blog archives; reusable sections and Loop Items in the Template Library
* Header and Footer templates edit on their own Elementor document types, showing only the settings that apply to site chrome
* Display Conditions: Entire Site, Singular Post Type, Post Type Archive, and Search Results for Search templates (advanced conditions, including Search Results by post type, available in Elemacy Pro); when several templates match a page, the most specific one is used
* Popups & Floating Elements: popups, top/bottom bars, banners, and floating elements designed in Elementor, with display conditions, Page Load/Click triggers, and Frequency Cap/Logged-in Users rules
* Loop Builder Grid widget with AJAX pagination (up to 100 items per page), query builder, and responsive columns; a "Current Query" data source lists whatever the page itself queries (archives, search, shop, blog, single pages) and paginates through the page's own URLs, with AJAX updating the address bar and history; Loop Grid also offers Load More and Infinite Scroll pagination for every data source
* The Archive Title dynamic tag shows "Search Results for: term" in a Search Results template; an Archive template can be previewed as a category, tag, taxonomy term, author or month, and the term, author and Loop Item preview pickers search as you type
* Loop Grid and Loop Carousel data sources: Posts, Current Query, taxonomy Terms and Users; nested loops with context-aware dynamic tags
* Loop Carousel widget
* Form Builder widget with Text, Email, Textarea, URL, Tel, Number, Date, Time, Select, Radio, Checkbox, Acceptance, Hidden and HTML fields; Email action with [all-fields] and [field id="..."] placeholders, validation, honeypot and rate limiting
* Nav Menu widget matching the Elementor Pro Nav Menu: horizontal, vertical and dropdown layouts, six pointers with their animations, breakpoint-based mobile dropdown with a hamburger toggle, full-width dropdown, dividers, and the same styling controls
* Site Logo and Search widgets
* ACF widgets: ACF Accordion, ACF Icon List, ACF Gallery (require ACF)
* Custom CSS control added to the Advanced tab of all Elementor elements and to page settings
* Dynamic Tags: Post Title, Post URL, Post Content, Post Excerpt, Post Date, Post Featured Image, Post Custom Field, Post Terms, Comments Number, Page Title, Site Title, Site Tagline, Site URL, Site Logo, Archive Title/Description/URL, Author Name/Info/URL/Profile Picture, Shortcode, Request Parameter, Current Date/Time, Contact URL
* Dynamic Tags (Taxonomy): Term Name, Description, URL, Count and Custom Field; Dynamic Tags (User): Name, Description, Avatar, URL, Login, Email and Post Count
* Dynamic Tags (WooCommerce, when active): Product Price, SKU, Stock, Rating, Short Description, Gallery, Add-to-Cart URL
* Dynamic Tags (ACF): ACF Field, ACF Image, ACF Gallery, ACF Color, ACF DateTime, ACF Number, ACF URL
* Module management admin page — enable or disable individual feature modules

== Upgrade Notice ==

= 1.0.0 =
Initial release of Elemacy. Install and activate to get started with the free, all-in-one Elementor addon.
