Please use the [official release](http://vanillaforums.org/addon/porter-core
), which is a single file. View the [official documentation](http://docs.vanillaforums.com/developers/importing) for important usage notes. **This repository is for developers**.

**DO NOT USE PORTER FOR UPGRADING VANILLA**. It is for _migrating_ from other forum platforms.

You can alternatively upload this entire folder to your web server if you want to use the latest bleeding-edge version or develop your own format. To do so, access it via `index.php`.

### Roll your own

To create a new export format, copy "class.skeleton.php", rename it to your platform, and follow its inline documentation. 

Send us a pull request when it's ready, and sign our [contributor's agreement](http://vanillaforums.org/contributors) (requires a vanillaforums.org forum account).

### Command line support

Porter can run via the command line. Execute the `index.php` file with the `--help` flag for a full list of options. 

For developers with very large databases or ones still in production, we recommend running your export from the command line on your localhost environment with a copy of the database.
