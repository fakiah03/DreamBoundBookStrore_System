# AI Usage & Disclosure Log

    Entry 1: Authentication System
    AI Tool Used: Gemini
    1. The intent
        I asked Gemini to make the interface for the log in and sign up to be more user friendly and fun to look.
    2. The interaction
        “Make the interface be more interactive and lovelier but don’t change the original coding”
    3. The Output and verification
        It gave me the code that can be change and add to make the interface more interactive. Then, I tested the code, and it worked.

    Entry 2: Redesign and polish the administration workstation dashboard layout
    AI Tool Used: ChatGPT
    1. The Intent
        I prompted ChatGPT to redesign and polish the administration workstation dashboard layout.

    2. The Interaction
        “Analyze the current Admin Workstation Terminal interface layout. Provide suggestions and CSS/HTML template styling to make it more interactive, visually organized, and modern, without changing our functional backend database structure or logic."

    3. The Output and verification
        It will generate updated interface layout structures and designs. I implemented the suggested visual styling adjustments and thoroughly tested all dashboard data bindings and verifying that read queries correctly fetched operational totals (sales, orders, users, items) and that routing integrity remained completely unbroken during data state manipulation.

    Entry 3: Format-Based Pricing (Paperback / Hardcover / E-Book) and Cart Integration
    AI Tool Used: Cloud AI
    1. The Intent
        To add format-specific pricing and selection (Paperback, Hardcover, E-Book) to the book browsing page and ensure the chosen format and its correct price carried through to the cart.

    2. The Interaction
        •	Uploaded the current cust_home.php and described the feature informally, requesting separate prices for paperback, hardcover, and e-book, and a working selection button for each.
        •	The AI first checked the existing books table schema and found it had only a single price column with no format support.
        •	The AI proposed and disclosed a database migration approach (adding new columns automatically and safely via ALTER TABLE ... ADD COLUMN IF NOT EXISTS) before writing any feature code.

    3. The Output and Verification
        •	Added three new columns to the books table (price_paperback, price_hardcover, price_ebook), with automatic fallback pricing (e.g., Hardcover = base price +30%) for books the admin had not manually priced yet.
        •	Added a format column to the cart table to track which format a customer selected per cart entry.
        •	Rebuilt the book details modal in cust_home.php with an interactive format selector that updates price and quantity dynamically.
        •	Updated cust_cart.php to display a colour-coded format badge per item and to calculate the correct price per format using a SQL CASE statement.

    Entry 4: An automated country code selection box
    AI Tool Used: Gemini
    1. The Intent
        To apply an automated country code selection box within the checkout form containing official calling prefixes for all selected countries. Also to make the state and city to dynamically auto-populate based on the selected country.

    2. The Interaction
        "Make the phone number drop-down box list and contain the calling codes for Malaysia, Thailand, Indonesia, Singapore, Brunei, Philippines and Vietnam and make the state and city dynamically auto populate based on the selected country."

    3. The Output and Verification
        It gave me the code with the dropdown that contains all selected country calling codes. Then, I tested the code by changing the countries and it successfully auto populated the correct states and cities for each country

