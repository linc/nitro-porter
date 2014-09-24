Vanilla Porter
==============

Vanilla Porter is a nifty tool for exporting your old & busted forum into a fresh Vanilla Forum. It will create a zipped text file that can be imported directly to Vanilla via the Dashboard.

**Do NOT user Vanilla Porter for UPGRADING**. It is for _migrating_ from other forums, including the incompatible Vanilla 1.x series.

### Requirements

Porter requires PHP 5.3+ and a connection to your existing database. That's it! 

### Getting started

Please use the [official release](http://vanillaforums.org/addon/porter-core
), which is a single file. View the [official documentation](http://docs.vanillaforums.com/developers/importing) for important usage notes.

If you have a PHP-based forum (vBulletin, phpBB, etc) you can likely drop this right on your server and run it from there. For more complicated arrangements (like jForum, i.e. a Java environment), we suggest exporting your database to a PHP/MySQL server and running it there.

### Roll your own!

To support a new forum source, copy "class.skeleton.php", rename it to your platform, put it in the `packages` folder, and follow its inline documentation. 

You can run Vanilla Porter via `index.php` which will use the source files rather than the single-file official release. This makes it easier to keep it up-to-date and debug problems.

Send us a pull request when it's ready, and sign our [contributor's agreement](http://vanillaforums.org/contributors) (requires a vanillaforums.org forum account).

### Command line support

Porter can run via the command line. Execute the `index.php` file with the `--help` flag for a full list of options. 

For developers with very large databases or ones still in production, we recommend running your export from the command line on your localhost environment with a copy of the database. It just makes life easier.

### Building a release

Run `make.php`, which will build a single file named `vanilla2export.php`. Easy peasy.