Nitro Porter
============

The only multi-platform community migration tool. Free your forum!

Currently exports to: 
* Flarum
* Vanilla

Currently imports from:
* Flarum
* Vanilla
* vBulletin
* XenForo
* phpBB
* IPBoard
* Simple Machines (SMF)
* Drupal
* NodeBB
* FluxBB
* ...and more than a dozen more!

## Requirements

* PHP 8.0+
* PHP's PDO driver for your data sources (probably MySQL or PostgreSQL).

Some specific data transfers may have additional requirements.

Run this locally with a product like MAMP or XAMPP (or your own homebrew'd LAMP stack). It's not safe to run migrations on a public web server and consumes a lot of resources.

## Getting Started

Nitro Porter can run via the web browser or the command line. 

It logs its activity to a `porter.log` file in the root. Open it with your favorite log viewer to follow along.

### Web via Browser

1. Drop the latest release in a web-enabled directory and navigate to the folder in your web browser.
2. Fill out the form and hit the button.

### CLI via Composer

_Beware, this installs the latest development version of Nitro Porter!_

1. [Get Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos).
2. Install Porter with: `composer global require "linc/nitro-porter"`.
3. Go to the install folder: `cd ~/.composer/vendor/linc/nitro-porter`. 
4. Copy the sample config: `cp config-sample.php config.php`.
5. Add connections for your source and output to `config.php`.
6. Run: `composer install`.
7. View Porter's options with: `porter --help`.

## Troubleshooting

### 504 Gateway Error

While Porter does increase PHP's timeout, your webserver may disconnect, resulting in a 504 Gateway Error. 
You must alter your web server's config to increase its timeout. 
To set a 30-min timeout in Apache, set `Timeout 1800` in your httpd.conf and restart.

## Contribute

### Data!

We greatly appreciate donated data from existing forums to improve the migration and its testing (using partial, anonymized records). A complete database dump is best way to do this. We protect privacy, but you're welcome to anonymize personally-identifiable information first. Willing to sign an extremely narrow NDA for the purpose if necessary. Contact lincoln@icrontic.com.

### Document a bug

[Start a discussion](https://github.com/linc/nitro-porter/discussions/new) if you've found a reproducible defect. Please include expected vs actual outcome and full steps to reproduce it reliably. We don't currently maintain an issue tracker.

### Repair a bug

Basically doing the above, but attached to a pull request with a proposed fix! It's greatly appreciated.

### Add support for a new source

To support a new forum source, copy "src/Source/Example.php", rename it to your platform, and follow its inline documentation. Document and test it thoroughly, including noting shortcuts taken or potential future improvements. Send us a pull request when it's ready!
