<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A plain-language help page for the people who actually use the Add/Edit
 * Asset form day to day — not developers. Every sentence here is meant to
 * be readable at roughly a 5th-grade level: short sentences, common words,
 * one idea at a time. Uses native <details>/<summary> for the FAQ items
 * (no JavaScript needed, and screen readers announce them correctly on
 * their own) rather than a custom accordion widget.
 */
class RAPM_Help {

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'Help & FAQ', 'rapm' ),
			__( 'Help & FAQ', 'rapm' ),
			'edit_posts',
			'rapm-help',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		?>
		<div class="wrap rapm-help">
			<h1><?php esc_html_e( 'Help & FAQ', 'rapm' ); ?></h1>
			<p class="rapm-help-intro"><?php esc_html_e( 'This page answers the questions people ask most when adding a new promotion. Read the steps below, then check the questions and answers if you get stuck.', 'rapm' ); ?></p>

			<style>
				.rapm-help { max-width: 800px; font-size: 15px; line-height: 1.7; }
				.rapm-help h2 { margin-top: 2em; }
				.rapm-help-intro { font-size: 16px; }
				.rapm-help ol, .rapm-help ul { padding-left: 1.4em; }
				.rapm-help li { margin-bottom: 0.6em; }
				.rapm-help details { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; margin-bottom: 10px; padding: 4px 16px; }
				.rapm-help summary { cursor: pointer; font-weight: 600; padding: 10px 0; }
				.rapm-help details p, .rapm-help details ul, .rapm-help details ol { margin-top: 0; padding-bottom: 10px; }
				.rapm-help .rapm-help-note { background: #f0f6fc; border-left: 4px solid #72aee6; padding: 10px 14px; margin: 1em 0; }
			</style>

			<h2><?php esc_html_e( 'Adding a New Promotion — Step by Step', 'rapm' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'On the left menu, click "Add New Asset."', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Pick the type of promotion: Hero (the big slider) or Fold Banner (a shorter strip).', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Type a name for your own records. Visitors will never see this name.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Upload your desktop picture and your phone picture. The page will tell you the exact size each one needs to be.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Answer the question about sale text: does your picture already show the price or sale? Pick Yes or No.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'If you picked No, type your headline, a smaller line under it, and your button words (like "Shop Now").', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Look at the preview box. It shows exactly what visitors will see.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Choose where people go when they click it — see "Where It Goes When Clicked" below for help.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Choose a start date and an end date, or leave them blank.', 'rapm' ); ?></li>
				<li><?php esc_html_e( 'Click the button at the bottom to save.', 'rapm' ); ?></li>
			</ol>

			<div class="rapm-help-note"><?php esc_html_e( 'Tip: You can always come back and change anything later. Just find your promotion in "All Assets" and click Edit.', 'rapm' ); ?></div>

			<h2><?php esc_html_e( 'Types of Promotions', 'rapm' ); ?></h2>
			<details>
				<summary><?php esc_html_e( 'What\'s the difference between Hero, Fold Banner, Coupon, and Marquee?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Hero is the big slider at the top of a page. Fold Banner is a shorter strip meant to sit lower down. Coupon is a small card shown in a horizontal scrolling row alongside other coupons. Marquee is scrolling text only — no picture at all — good for a short announcement like "Free delivery this week."', 'rapm' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'How do I show a calendar of upcoming promotions?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Ask whoever manages the website to add [rapm_promotions_calendar] to a page. It automatically shows every promotion that has both a start date and an end date set — nothing else to configure. This is a calendar of your own promotions, separate from any community events calendar the site might also have.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Pictures — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'Why did my picture get rejected?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Each spot on the website needs a picture that is an exact size. The page tells you the size you need, in pixels, before you upload. If your picture is a different size, crop it or export it again at the right size, then try uploading it a second time.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What is a "pixel"?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'A pixel is just a unit of measurement for pictures, like an inch is a unit of measurement for a piece of wood. Most photo editing tools (Canva, Photoshop, even your phone\'s Photos app) show you the size in pixels when you crop or resize a picture.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What kind of picture file can I upload?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Any common kind works — JPG, PNG, or whatever your phone, camera, or design tool saves by default. You do not need to convert it to anything yourself. The tool does that for you automatically.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Do I need both a desktop picture and a phone picture?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'The desktop picture is required — without it, your promotion will not show up anywhere. The phone picture is a good idea too, since it makes sure your promotion looks its best on phones, but you can add it later if you need to.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Linking to an Outside Picture — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'What does "Use a link" mean?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Instead of uploading a file from your computer, you paste in a web address that points straight to a picture — for example, one a brand partner keeps on their own marketing site, or a Google Drive file. We check it every hour and update the picture on your site automatically if it changes, so you never have to remember to re-upload it.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to a Google Drive file?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'In Google Drive, right-click the file, choose Share, and change the setting to "Anyone with the link." Then copy that link and paste it into the box. If the setting is left more restricted than that, the link won\'t work.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What happens if the link stops working?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Nothing breaks on your site — the last picture that worked keeps showing. You\'ll get an email, and you\'ll also see a red "Link issue" note next to that promotion in "All Assets," so you know to check it.', 'rapm' ); ?></p>
			</details>

			<div class="rapm-help-note"><?php esc_html_e( 'A linked picture still has to be the exact right size, just like an uploaded one — if a brand\'s picture is the wrong size, it will be turned down with a note explaining why, the same as an upload would be.', 'rapm' ); ?></div>

			<h2><?php esc_html_e( 'Sale Text — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'Do I have to type in a headline, sale text, or button words?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'No. These are only needed if your picture does not already show the price or sale. If your picture already has that text on it, pick "Yes" on the sale text question and leave the text boxes empty.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'What happens if I fill in the text boxes AND my picture already has text on it?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'The two would show up on top of each other, which looks messy. Always check the preview box before saving — if you see text twice, either clear the text boxes or answer "Yes" to the sale text question.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Where It Goes When Clicked — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'What should I pick if I\'m not sure?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick "A specific link" and paste in the full web address. It starts with https:// — the same kind of address you see at the top of your browser.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to one specific product, category, or brand?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick that option from the dropdown, then start typing the name in the box that appears — for example, "leather sofa" or "Ashley Furniture." A list of matches will show up under the box. Click the right one. You never need to know an ID number.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to search results for certain words?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick "Search results for some words" and type the words a visitor would type into the site\'s own search box, like "sectional" or "outdoor dining."', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'How do I link to a specific list of SKUs?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Pick "A hand-picked list of products." Type or paste your SKU numbers into the box, one per line. Those exact products will show first, in the order you typed them. If you want more products to fill in the rest of the page automatically, choose search words, a category, or a brand under "Then fill in the rest of the page with." If you don\'t want anything else added, leave that set to "Nothing else."', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'I typed a name but nothing shows up in the list. What do I do?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Try typing fewer words, or check your spelling — the search looks for an exact match on the name as it appears on the website. If you still can\'t find it after a few tries, ask whoever manages the website to check.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Scheduling — Questions and Answers', 'rapm' ); ?></h2>

			<details>
				<summary><?php esc_html_e( 'What happens if I leave the start and end dates blank?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Leaving the start date blank means it starts showing right away. Leaving the end date blank means it keeps showing until you come back and turn it off yourself.', 'rapm' ); ?></p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Can I trust the end date to actually turn it off on time?', 'rapm' ); ?></summary>
				<p><?php esc_html_e( 'Yes. It will turn off exactly when you set it to, automatically, with nothing more for you to do.', 'rapm' ); ?></p>
			</details>

			<h2><?php esc_html_e( 'Still Stuck?', 'rapm' ); ?></h2>
			<p><?php esc_html_e( 'If something still isn\'t working the way you expect, reach out to whoever manages the website for your company. Let them know what you were trying to do and what happened instead — that helps them fix it faster.', 'rapm' ); ?></p>
		</div>
		<?php
	}
}
