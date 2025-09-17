=== Simply Code ===
Contributors: davidcamejo
Tags: code snippets, php, javascript, css, custom code
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A minimalist plugin to run custom code snippets using JSON files instead of database.

== Description ==

Simply Code allows you to create and manage custom code snippets with support for PHP, JavaScript, and CSS components. Each snippet can have one or more components, and the plugin will automatically orchestrate them.

**Key Features:**

* **Multi-component Support**: Each snippet can have PHP, JavaScript, and CSS components
* **Easy Management**: Clean admin interface with tabbed editing
* **File-based Storage**: Uses JSON files instead of database storage
* **Real-time Execution**: Automatically executes active snippets
* **Template System**: Built-in templates for quick start
* **Responsive Interface**: Works great on all devices

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/simply-code` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Simply Code menu to create and manage your snippets

== Frequently Asked Questions ==

= Can I use only one component per snippet? =

Yes! You can create snippets with just PHP, just JavaScript, just CSS, or any combination.

= Where are the snippets stored? =

Snippets are stored as files in the `/wp-content/plugins/simply-code/storage/snippets/` directory.

= Is this safe for production? =

While the plugin works well, always test snippets in a development environment first.

== Changelog ==

= 1.2.5 =
* Added multi-component support
* Improved admin interface
* Better file organization
* Enhanced security measures

= 1.0.0 =
* Initial release
