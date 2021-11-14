Nitro Porter
============

Nitro Porter is a nifty tool for exporting your community forum into an easy-to-import format.

## Requirements

* PHP 7.3+
* Installed PHP support for your data source (like a db extension for MySQL or PostgreSQL).

Some specific data transfers may have additional requirements.

## Getting Started

Nitro Porter can run via the web browser or the command line. 

### Web browser

Drop the latest release in a web-enabled directory and navigate to the folder in your web browser.

### CLI

Run `index.php` with the `--help` flag for a full list of options. 

```
cd /path/to/porter
php index.php --help
```

### Optional Tips

1. Run migrations locally instead of on a remote webserver.
2. Prefer the CLI if you're comfortable doing so.

## Contribute

### Data!

We greatly appreciate donated data from existing forums to improve the migration and its testing (using partial, anonymized records). A complete database dump is best way to do this. We protect privacy, but you're welcome to anonymize personally-identifiable information first. Willing to sign an extremely narrow NDA for the purpose if necessary. Contact lincoln@icrontic.com.

### Document a bug

[Start a discussion](https://github.com/linc/nitro-porter/discussions/new) if you've found a reproducible defect. Please include expected vs actual outcome and full steps to reproduce it reliably. We don't currently maintain an issue tracker.

### Repair a bug

Basically doing the above, but attached to a pull request with a proposed fix! It's greatly appreciated.

### Add support for a new source

To support a new forum source, copy "sample_package.php", rename it to your platform, put it in the `packages` folder, and follow its inline documentation. Document and test it thoroughly, including noting shortcuts taken or potential future improvements. Send us a pull request when it's ready!
