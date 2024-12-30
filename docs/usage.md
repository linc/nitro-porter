# Quick Start

## Requirements

* PHP 8.0+
* PHP's PDO driver for your data sources (probably MySQL or PostgreSQL)
* 256MB of memory allocated to PHP

::: tip
Nitro Porter will set PHP's memory limit to 256MB. If it's unable to do so, it may suffer performance issues or generate errors. For small forums, you may be able to safely reconfigure it to 128MB or lower.
:::

## Installation

Install Nitro Porter as a global [Composer](https://getcomposer.org) package:

```bash
composer global require linc/nitro-porter
```

This will make the `porter` command-line utility available system-wide.

## Configuration

Set up a new Porter project by running the `init` command:

```bash
porter init
```

This will create a new `porter.config.php` file in the current directory. Open this file and configure the database connections for your source database and your target database.

```php
return [
    'connections' => [
        'source' => [
            'type' => 'database',
            'driver' => 'mysql',
            'database' => 'database',
            'username' => 'root',
            'password' => 'password',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ],
        ],
        
        'target' => [
            'type' => 'database',
            'driver' => 'mysql',
            'database' => 'database',
            'username' => 'root',
            'password' => 'password',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ],
        ],
    ],
];
```

## Running a Migration

To run a migration, use the `run` command:

```bash
porter run
```

You will be asked to select the source and target platforms. Porter will then run the migration and output its progress to the console.

## Cleaning Up

Porter uses `PORT_` as the prefix for its intermediary work storage. You can safely delete the `PORT_` tables after the migration.
