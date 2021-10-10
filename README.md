Nitro Porter
============

Nitro Porter is a nifty tool for exporting your community forum into an easy-to-import format.

Products that can import Nitro Porter exports:

1. Vanilla Forums
2. Discourse

### Requirements

PHP 7.3+ and a connection to your existing database.

### Getting started

If you have a PHP-based forum (vBulletin, phpBB, etc) you can likely drop this right on your server and run it from there. For more complicated arrangements (like jForum, i.e. a Java environment), we suggest exporting your database to a PHP/MySQL server and running it there.

### Add support for a new or custom software

To support a new forum source, copy "sample_package.php", rename it to your platform, put it in the `packages` folder, and follow its inline documentation.

You can run Nitro Porter via `index.php` (drop it on your web server and view it in a browser).

Send us a pull request when it's ready.

### Command line (CLI) support

Porter can run via the command line. Execute the `index.php` file with the `--help` flag for a full list of options. 

```
cd /path/to/porter
php index.php --help
```

For developers with very large databases or ones still in production, we recommend running your export from the command line on your localhost environment with a copy of the database. It just makes life easier.
