=== Simply Code ===
Contributors: davidcamejo
Tags: code snippets, php, javascript, css, custom code
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A minimalist plugin to run custom code snippets using JSON files instead of database.

== Description ==

Simply Code is a lightweight WordPress plugin that allows you to add and execute custom code snippets (PHP, JavaScript, and CSS) without using the database. All snippets are stored as JSON files, making them easy to version control and migrate.

Key Features:
- Store snippets as JSON files (no database usage)
- Support for PHP, JavaScript, and CSS
- Tabbed interface for easy editing
- Automatic backup system
- Simple and clean admin interface
- No external dependencies

Perfect for developers who want to add custom functionality without cluttering the database.

== Installation ==

1. Upload the `simply-code` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Simply Code > Add New to create your first snippet

== Frequently Asked Questions ==

= Where are the snippets stored? =

Snippets are stored as JSON files in the `wp-content/plugins/simply-code/storage/snippets/` directory.

= Is this safe? =

The plugin includes basic security measures, but as with any code execution plugin, you should only use trusted code snippets.

= Can I version control my snippets? =

Yes! Since snippets are stored as files, you can easily add them to your version control system.

== Screenshots ==

1. Snippets list interface
2. Tabbed editor for PHP, JS, and CSS
3. Backup system overview

== Changelog ==

= 1.2.1 =
* Fixed snippet listing issues
* Improved error handling
* Added JS and CSS backup functionality
* Enhanced security measures

= 1.2.0 =
* Added support for JavaScript and CSS snippets
* Implemented tabbed interface
* Added backup system
* Improved file structure

= 1.1.0 =
* Initial release
* Basic PHP snippet functionality
