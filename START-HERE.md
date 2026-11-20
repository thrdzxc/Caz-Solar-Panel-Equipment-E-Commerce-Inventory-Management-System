# CAZ Solar & CCTV system — updated October 14, 2026

## Latest sidebar, accounts and order update
- Customer and admin navigation now uses a collapsible teal left sidebar with local SVG icons. On mobile, use the menu button. Account, customer cart and search are inside the sidebar.
- Existing customer profile, photo, address and logout functions are retained. Password display/editing and Forgot Password are removed; old reset links cannot change credentials.
- New registration names use letters and spaces only. Contact and contact-person phone numbers require exactly 11 digits beginning 09. New passwords require at least 8 characters and a special character. Existing login credentials remain unchanged.
- Order alerts update automatically every five seconds while the page is visible. Admin Orders shows a red bell with pending purchases. Customers see a red bell for unread order updates in Cart & orders, checkout confirmation, and new status notices. Opening the cart marks the already received updates read.
- Product deletion now requires an admin confirmation and protected POST. Products in an order cannot be deleted, preserving order history; set their stock to 0 to stop purchases. New/unordered products can be removed with their cart/promotion entries atomically.

## Database recovery on October 13
The original cctv_solar_db no longer had registered tables. The verified October 6 backup was restored into cctv_solar_recovered_20261013, and db_conn.php now uses that database by default. The original database files were preserved. Records added or changed after October 6 are not included in this recovery. CAZ_DB_NAME can override the configured database name.

## Latest registration and verification update
- Registration Province and ID-type dropdown menus match the teal form, with readable light text and native keyboard selection.
- Removed the Call contact person button and calling links for verification contacts. Names and phone numbers remain readable.
- Admin navigation shows a red bell and pending-registration count under Verification. It checks every five seconds while the page is visible and checks again when returning to the tab.
- The Verification list updates automatically for new registrations, replacement IDs and admin decisions. Updates wait while an admin has an unfinished note or review confirmation, so background checks do not erase that work. The count still updates immediately at the next check.
- No additional API key or database import is required for these changes on the existing installation.

## Run the system
1. Start Apache and MySQL in the XAMPP Control Panel. Keep both running while using the website.
2. On the original computer, open http://localhost:8080/caztesting_fixed/.
3. Choose Customer or Admin and sign in with your existing account. The two portals use separate sessions.
4. Press Ctrl+F5 if your browser displays an earlier design.

The active folder on the original computer is C:\xamppp\htdocs\caztesting_fixed (three p's in xamppp).

## Install the complete ZIP on another computer
1. Install XAMPP with PHP 8.2 and MariaDB/MySQL. Enable mysqli, GD, fileinfo and session support. Set upload_max_filesize to at least 8M and post_max_size to at least 12M. The supplied local image processor can also use XAMPP's PHP command-line GD extension when Apache has GD disabled.
2. Copy the included caztesting_fixed folder into your XAMPP htdocs folder.
3. Start Apache and MySQL. Open phpMyAdmin, create an EMPTY database named cctv_solar_recovered_20261013 using utf8mb4, select it, and import database/cctv_solar_db.sql from the ZIP. The SQL filename does not have to match the selected database name.
4. The default connection is localhost, port 3306, user root, blank password. If your local settings differ, configure CAZ_DB_HOST, CAZ_DB_PORT, CAZ_DB_NAME, CAZ_DB_USER and CAZ_DB_PASSWORD in your server environment, or edit db_conn.php.
5. Open http://localhost/caztesting_fixed/ if Apache uses port 80, or http://localhost:8080/caztesting_fixed/ if it uses port 8080.
6. Use the existing accounts from the included database. The export preserves password hashes; it does not reset passwords.

The SQL contains the full application database, including products, orders, inventory, accounts, messages, promotions, carts, delivery pins and profile photos. Import it into an empty database for a new installation; do not import over the working original database. QA/test accounts are not included in the export.

## Latest update
- Manage Order, Add Product and Edit Product share the readable teal design. Product creation and stock edits are validated and saved together with inventory.
- Product details use a balanced image area with features below. Only original product photos are included, as requested. Added genuine photos appear side by side with labels, and PNG/WebP uploads keep transparent backgrounds.
- Chatbot answer bubbles are labeled CAZ · Chatbot, with larger 16px answer text, clearer spacing and a wider responsive panel. The ten-question guide and login requirement for contacting admin remain.

## Included features
- Customer and admin portals, product details, search, glass filter menus and inventory management.
- Persistent customer carts, checkout notifications, stock checks and order management.
- Customer/admin messages with read receipts, CAZ admin branding, and a small red bell notification with an unread count in the navigation. Messages fills the available screen, with separate scrolling for history and inbox and a visible message composer. The badge refreshes automatically while the page is visible and clears as incoming messages are read.
- Public promotions from the portal and both login pages, with admin-only management. Signed-in customers use the lower-right Promotions shortcut; on Messages it is in the heading so it never covers the composer.
- Smooth image zoom within clipped frames on products, promotions and vendor logos. About service photos fill frames matched to their portrait proportions, keeping the whole image visible with a gentle lift and border glow. Shared footers use the available desktop width and stack neatly on smaller screens.
- Aligned cart tabs; compact teal customer search; single-frame inventory search; teal admin product details and promotion management. Product gallery controls accept genuine alternate product photos. Generic installation photos are no longer displayed under products.
- Profile-photo upload and account editing. Customer photos also appear in the admin inbox, conversation header, customer message bubbles and recipient picker, with automatic refresh after upload, replacement or removal.
- Draggable delivery map and an admin Google Maps directions link.
- Admin-managed store entrance pin and customer Google Maps directions from the Contact page.
- Compact admin account panel and responsive transparent product details.
- Supplied teal wallpaper throughout, matching transparent panels, larger responsive navigation, and a visible red camera lens light with a slow moving laser on the portal and customer home page. The beam extends from the lens and fades gradually without a hard cutoff. Reduced-motion settings are respected.
- Customer login and registration CAPTCHA with a refreshable six-character code, five-minute expiry and single-use server validation. Works locally without an API key.

Replace images/caz-background.jpg to change the wallpaper. Effects and its teal tint are in css/background-theme.css. Panel styles are in css/glass-panels.css, and customer filter styles are in css/catalog-filters.css.

## Free public promotions chatbot
Open Promotions and choose **CAZ quick help**. Ten suggested questions cover the business, products, active offers, promotion conditions, installation, ordering, account verification, delivery addresses, store directions and contacting admin. Answers are prepared from the application's information; active offers and the store address/pin are read from the database when asked.

The built-in PHP API needs no subscription, external chatbot service or API key. It is a guided FAQ chatbot, not a general-purpose AI model. The server allows ten answers per session; reloads and customer login preserve the count, and retries do not consume another answer. Closing help preserves the conversation. Ending the PHP session starts a new help session; this limit is not a permanent account quota.

Visitors must log in before using **Message admin**. The login returns them to Messages, even after a failed attempt. Signed-in customers go directly to their conversation. Chatbot answers never automatically send a message to admin. Only prepared public information is returned; the chatbot cannot read customer orders, identities or private messages. Prepared text can be maintained in includes/promotion-assistant.php.

## Set the store's exact location
1. Sign in as Admin and open Store location in the navigation or Account panel.
2. Move the map to the actual store entrance at P-7 Mendoza Street, Balintocatoc, Santiago City. Click the entrance to place a pin, or use Place pin at map center. Drag the pin to adjust it. You can also enter known latitude and longitude.
3. If you are physically at the store, Use my current location can suggest a position after you allow location access. Check the pin against the entrance because GPS may be approximate.
4. Check the written address and press Save store location. The pin is stored in the database and appears on the customer Contact page.
5. Customers can click the location card or Get directions in Google Maps. Google Maps uses the saved coordinates as the destination and lets customers use their current location or enter a starting point to see a route, distance and travel time.

An exact store pin is deliberately not invented from the barangay name. Until an administrator saves it, the map shows the Balintocatoc area, clearly marked as awaiting confirmation, and the directions link uses the written address. Only authenticated admins can publish or change the pin. The store pin is separate from each customer's delivery pin. Internet is required for map tiles and Google Maps directions.

## Customer human verification
Enter the six characters exactly as shown before signing in or creating an account. Each code contains uppercase letters, lowercase letters and numbers and is case-sensitive. Choose New code if the image is unclear or expired. After a failed submission a fresh code is shown; registration keeps ordinary details but asks for the password and ID file again. This local image CAPTCHA is a basic anti-bot check, not an advanced anti-abuse service. Admin login remains separate.

## Customer ID review and order verification
New customer registrations require a valid photo ID and a contact person's name and mobile number. Driver's license, National ID (PhilSys), passport, UMID, postal, PRC, senior citizen, PWD, student and other photo ID options are available. JPG, PNG and WebP images up to 8 MB are accepted, with a minimum size of 800 × 500 pixels in either orientation and a 24-megapixel maximum. The upload preview lets the customer select a clearer replacement.

Local image processing checks for obvious blur, low contrast, darkness and excessive glare, then strips metadata and saves a resized JPEG below 600 KB. This avoids the original registration failure on a 1 MB MySQL packet limit. It does not send identity photos to an external service. Rejected images show a reason and can be replaced. All image-quality checks also run on the server.

Sign in as Admin and open **Verification**. Inspect the submitted photo privately, compare its details, and contact the customer/contact person when necessary. Approve a clear, consistent submission or use **Request a new photo** with a reason. Customers see the status and feedback through **Verification** and can resubmit a replacement. Only approved new accounts can place orders; this rule is enforced by checkout on the server. Browsing, messages and carts remain available while review is pending. Existing accounts keep their previous ordering access unless they submit themselves for review.

Image-quality checks cannot establish that an ID is genuine or that every detail is legible. A sharp background can coexist with unreadable ID text; human admin review is still required. This feature is not an official identity-authentication or fraud-detection service.

ID photos are stored privately in `caz_identity_verifications` in the database, never in public image folders. Only the owner and authenticated administrators can retrieve them. Images are removed on approval; review status and order contact details remain. Pending/rejected images are replaced by a new submission. Admin order details show the contact person's phone number. Treat database exports as private: they include account data and any pending ID submissions. Store backups securely and do not publish them.

## Actual product angles
In Admin Inventory, open Edit product and then Manage product photos. Upload a genuine photograph of that exact model and label the angle, such as Side view or Rear view. The customer product page displays labeled side-by-side photo tiles when additional photos exist. Select a tile to see it in the main image area. Up to eight additional photos are supported per product. JPEG uploads are normalized to safe JPEG files; PNG and WebP uploads are normalized to safe PNG files, preserving transparent backgrounds.

The current catalog contains generic product names without exact manufacturer model numbers. Additional angles have not been invented or borrowed from similar-looking models. The original 24 product images remain; matching photos can be uploaded through the new gallery controls. Gallery photos and their metadata live in images/product-gallery and must be copied along with the whole website when transferring the system.

## Database connection troubleshooting
If the store cannot reach its database, it now displays a clear recovery message instead of a PHP fatal error. Start MySQL in XAMPP, wait for Running, then return to sign in. If MySQL will not stay running, review its XAMPP error log before changing any database files. Keep a backup before any repair.

This ZIP contains a verified SQL export of the application tables; it does not contain or replace XAMPP's internal database files.

## Internet-dependent features
Map tiles, Google Maps directions, social links, CDN icons and report libraries require internet access. The draggable map uses OpenStreetMap/Leaflet; Google Maps opens for directions and does not need an embedded Google Maps API key.

See PACKAGE_VERIFICATION.txt in the complete ZIP for the checks performed on this release.
