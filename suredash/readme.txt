=== SureDash ===
Contributors: brainstormforce
Tags: dashboard, customer, user dashboard
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.7.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SureDash makes WordPress a community hub with unified login, custom dashboard, and total control over your data.

== Description ==

SureDash transforms your WordPress site into a vibrant community hub. Create a unified login and dashboard experience to boost user engagement—all within your WordPress environment.

Unlike standalone platforms like Circle, SureDash integrates seamlessly with your existing WordPress setup. Keep full control over your data and customize the experience to fit your brand.

[youtube https://www.youtube.com/watch?v=7syWO6epxnE]

<a href="https://app.zipwp.com/blueprint/suredash-demo-t6s" target="_blank" rel="">Try the live demo of SureDash.</a>

== Key Benefits ==
* **Seamless Integration:** Enhance your WordPress site with community features.
* **Customizable Design:** Tailor the dashboard and features to match your brand’s look and feel.
* **Scalable Solution:** Add new spaces as needed.

== Free Features ==
* Custom login and registration forms
* Visual customizer for dashboard layout
* User profiles
* Activity feeds for updates and engagement
* Discussion forums with threaded replies (only site admin can post, but all members can comment)
* Multiple discussions for focused sub-communities
* Giphy integration for animated GIFs

== SureDash Premium ==
* Learning management system course builder
* Enable users to post in discussion feeds
* Private discussion feeds
* Resource library
* Events
* Support from our team

== Perfect For ==
* Membership sites
* Online courses
* Customer support hubs
* Niche communities

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/suredash` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **SureDash > Settings** to configure your dashboard.

== Links ==
* Visit [SureDash](https://suredash.com)
* Get [Support](https://suredash.com/support)
* Try [Test Drive](https://zipwp.org/plugins/suredash/)

== Frequently Asked Questions ==

= How can I report a security bug? =

We take plugin security extremely seriously. If you discover a security vulnerability, please report it in a safe and responsible manner.

You can report the issue through our [Bug Bounty Program](https://brainstormforce.com/bug-bounty-program/).

== Changelog ==
= 2026-03-27 - version 1.7.1 =
* Fix: Resolved issue where multi-paragraph comments displayed incorrectly in a horizontal layout.
* Fix: Addressed 'headers already sent' warning impacting portal login page.

= 2026-03-25 - version 1.7.0 =
* New: Introduced sorting options for Discussions and Feeds to help users organize content more efficiently.
* New: Added a List view layout for Discussions and Feeds, providing an alternative and more structured browsing experience.
* New: Added customization options for Login and Registration block titles.
* New: Introduced new action hooks 'suredash_before_file_delete' and 'suredash_after_file_upload', allowing developers to execute custom logic before file deletion and after file upload.
* Improvement: Enhanced UI for post comments along with overall UI enhancements.
* Improvement: Repositioned Bookmark and Post action menu to the top with UI/UX enhancements.
* Improvement: Responsive CSS improvements.
* Improvement: Re-generate fonts on Site URL or Home URL change.
* Improvement: The “Copy URL” option is now hidden from the post action menu if the Share Button is disabled.
* Improvement: Space groups will no longer be displayed if they are empty or if all spaces inside them are hidden.
* Improvement: Added pre-compatibility support for SureMembers Core plugin.
* Improvement: Added WebP support for profile photo uploads.
* Fix: Feed and user profile posts were not being restricted when the Portal was restricted via SureMembers.
* Fix: Fixed sidebar height CSS issue in the classic layout to ensure proper alignment and full-height rendering.
* Fix: Fixed an issue in Safari where the cursor position was lost when opening the emoji picker popup.

= 2026-03-12 - version 1.6.3 =
* Improvement: Hardened the security of the plugin.
* Fix: Fixed an issue where email notifications were not being saved correctly.

= 2026-02-24 - version 1.6.2 =
* Improvement: Sidebar can now be used with all container width options. Previously, enabling the sidebar forced the layout to full width.
* Improvement: Feeds now display the discussion space from which each post originates for better context and clarity.
* Fix: Resolved an issue where the unboxed container style was being applied incorrectly to discussion spaces.
* Fix: Reduced Google OAuth scopes to only request necessary permissions (openid, email, profile).
* Fix: Prevent false password mismatch error from browser autofill.

= 1.6.1 - 12th February 2026 =
* New: Added 'suredash_identity_block_url' filter to customize the identity block redirect URL.
* Fix: Kadence theme styles incorrectly loading on auth pages, leading to layout issues.
* Fix: Reverted the default template assignment to the portal layout for newly created pages.

= 1.6.0 - 9th February 2026 =
* New: Introduced Portal Layout template for posts and custom post types to ensure a consistent portal experience across all pages. (https://suredash.com/docs/use-portal-layout-template-for-site-wide-pages/)
* New: Added a Space Sidebar feature that provides quick access to relevant sections within a space. (https://suredash.com/docs/space-sidebar-layout/)
* New: Unread post count is now displayed for Discussion Post spaces in the portal.
* New: SureMembers Compatibility – Configure SureMembers memberships to send notifications to targeted users within selected memberships.
* New: SureMembers Compatibility – Added an Access Group selection option in Integration settings to automatically assign newly registered users to the selected group.
* New: Introduced an option to reset the cover image under Edit Profile settings.
* Improvement: Fixed ACSS-related CSS compatibility issues on Portal pages.
* Improvement: Separated Profile and Cover Image upload limits from the general Assets Upload Limit for better control.
* Improvement: UI tweaks and refinements for an improved user experience.
* Improvement: Portal notifications for post and comment replies now redirect directly to the relevant comment.
* Fix: Removed the visual flicker on the frontend where the site briefly appeared in dark mode before switching to light mode on page load.
* Fix: Resolved social login button spacing issues and fixed the social button position option in the login form.
* Fix: Fixed an issue where the emoji selector was not working on the portal frontend.

= 1.5.4 - 29th December 2025 =
* New: Added 'suredash_post_content' filter to modify post content before it's returned.
* Improvement: Showing Post Edit and Delete options for Discussion posts only.
* Improvement: Clicking on a comment author now redirects to the author’s profile.
* Improvement: Disabled rendering of the SureCart floating cart icon on Portal pages.
* Fix: Resolved translation word order issue for relative time stamps (e.g., "5 days ago") in non-English languages like French.
* Fix: Resolved a compatibility issue that prevented SureCart onboarding from completing successfully.
* Fix: Resolved WordPress import errors caused by a missing SureDash method.
* Fix: Resolved block editor console errors caused by the 'Back to SureDash' button.
* Fix: Fixed Notification & Profile menu drawer position for RTL languages.
* Fix: Resolved an issue where the Turnstile captcha was not refreshing after a failed login attempt.
* Fix: Fixed special characters (š, č, ž, etc.) appearing bold by including extended Latin subset in Google Fonts loading.
* Fix: Corrected the “Lost Password” link to redirect to the portal’s password reset screen instead of the default WordPress page.

= 1.5.3 - 1st December 2025 =
* Fix: Added compatibility for WordPress 6.9.
* Fix: Updated Portal 'Remember Me' to store cookies for 14 days instead of session-only.
* Fix: Fixed issue where email notification status was not updating.
* Fix: Resolved missing first/last name in Portal Google registration and login flow clearing user names.
* Fix: Fixed Quick View modal content height issue in Zen Browser.
* Fix: Improved Breakdance compatibility for the Space Editor.
= 1.5.2 - 24th November 2025 =
* New: Added "Preserve HTML in Excerpts" option in Community Settings to maintain formatting (bold, italic, links, line breaks) in post excerpts. Enabled by default for new installations. ( https://suredash.com/docs/preserve-html-formatting-in-excerpts/ )
* Improvement: Post likes available with tooltip support in Quick View post modal.
* Fix: Post author badges displaying twice in the Quick View post modal.
* Fix: SureMembers Compatibility - The protected media URL from SureMembers was redirecting to a discussion space in some edge cases.
= 1.5.1 - 19th November 2025 =
* Fix: Quick View post modal was not working for SureDash User role users.
* Fix: Duplicated More options menu visible on single post view.
* Fix: Reverted back "Preserve HTML in Excerpts" option due to compatibility issues, until next update.
= 1.5.0 - 19th November 2025 =
* New: Introducing Notifications for portal users for various purposes. ( https://suredash.com/docs/notifications-system-in-suredash/ )
* New: Introduced the Badges system for community gamification.
* New: Administrator users can also be listed from Users > User Roles filter, for assigning badges to admin portal managers.
* New: Introducing emoji support for space icons to enhance visual communication and express emotions more effectively.
* New: Introducing "Reset" ability for Light-Dark color palettes to get back to the default configuration.
* New: Added "Preserve HTML in Excerpts" option in Community Settings to maintain formatting (bold, italic, links, line breaks) in post excerpts. Enabled by default for new installations. ( https://suredash.com/docs/preserve-html-formatting-in-excerpts/ )
* New: Introduced "Website" & "Headline" fields under user profile settings.
* New: Introducing Social links for the user profile to showcase on the user view page. ( https://suredash.com/docs/add-social-links-to-your-profile/ )
* New: Introduced a "Thumbnail Image" option for link space for showcasing on the home page view.
* Improvement: Improved the design UI for like-comment-share trigger buttons under spaces and posts for better responsive handling and user interaction.
* Fix: Private community now redirects users back to the originally requested portal space URL after login.
* Fix: Log out from the portal now redirects to the portal login page instead of the main site URL.
* Fix: Flushing rewrite rule after post type registration, causing 404 for portal posts.
* Tweak: The  Comment box with a big-sized image now has a scroll to reach the end.
* Tweak: The Quick View post modal no longer has a fixed height.
* Tweak: Allowed all space types to be selected in SureCart integration.
* Tweak: Previously, a large-sized image upload in the comment box was causing a scrolling UI glitch.
* Tweak: When navigating from a space edit screen back to the spaces list, the view now automatically scrolls to the recently edited space instead of staying at the top.
* Tweak: All portal templates adjusted for better mobile responsiveness. ( https://suredash.com/docs/suredash-portal-template-patterns/ )
