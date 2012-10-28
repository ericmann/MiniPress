MiniPress
=========

Automatically concatenates and minifies all enqueued scripts upon pageload.

Description
-----------

Concatenation merges multiple JavaScript files into one. This means the browser downloads 1 file instead of 5, 10, or 20.

Minification removes all unnecessary space from a file. This means you only download the content you actually need.

To speed up your site, most developers will recommend you do both.  But it can be time-consuming and frustrating to set this up - particularly as you install new plugins on your site.

Rather than forcing you to merge and minify files manually, this plugin will do it for you automatically.  No configuration is needed, just activate and go.

This is very much a **beta version** of the plugin and is not expected to be stable. Please report any and all bugs to https://github.com/ericmann/MiniPress/issues

Installation
------------

**Easy Installation**

Search for "MiniPress" in the WordPress 'Plugins' menu.

**Manual Installation**

1. Upload the entire `/minipress` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

Frequently Asked Questions
--------------------------

**How does SCRIPT_DEBUG work for developers?**

If `SCRIPT_DEBUG` is set to true in the configuration file, then nothing will happen to your scripts.  They will not be concatenated or minified.

**What about stylesheets?**

For now they're left alone. This will (hopefully) come in a later version.

Changelog
---------

**0.2**

- Handle script dependencies with concatenation.

**0.1**

- Initial release

Upgrade Notice
--------------

**0.1**

Initial release

Known Issues
------------

**0.3**
- If two scripts declare the same dependencies, that dependency will be included twice in the concatenated file.

**0.2**

- If two scripts declare the same dependencies, that dependency will be included twice in the concatenated file.

**0.1**

- Script dependencies are not automatically added to the concatenated script - they must be explicitly enqueued.

Additional Information
----------------------

**Contributors:** ericmann

**Donate link:** http://jumping-duck.com/wordpress/plugins/

**Tags:** javascript, minify, concatenate

**Requires at least:** 3.4.2

**Tested up to:** 3.5

**Stable tag:** 0.3

**License:** GPLv2 or later

**License URI:** http://www.gnu.org/licenses/gpl-2.0.html