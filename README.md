The [official release](http://vanillaforums.org/addon/porter-core
) is a single file. View the [official documentation](docs.vanillaforums.com/developers/importing) for important usage notes. This repository is for developers.

You can alternatively upload this entire folder to your web server if you want to use the latest bleeding-edge version or develop your own format. To do so, access it via `index.php` and ignore `vanilla2export.php` (the compiled version) entirely.

### Roll your own

To create a new export format, add a new file in the format "class.yournewformatname.php" which will automatically be added to the dropdown list of export options. Copy one of the existing classes as an example / starting place. We recommend using a simpler one for this like SMF.

### Command line support

Porter can run via the command line. Execute the `index.php` file with the `--help` flag for a full list of options. For developers with very large databases or ones still in production, we recommend running your export from the command line on your localhost environment with a copy of the database.