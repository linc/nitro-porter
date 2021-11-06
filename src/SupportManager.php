<?php

namespace NitroPorter;

class SupportManager
{
    public const SUPPORTED_META = [
        'name',
        'prefix',
        'features',
        'CommandLine',
    ];

    public const SUPPORTED_FEATURES = [
        'Users',
        'Passwords',
        'Categories',
        'Discussions',
        'Comments',
        'Polls',
        'Roles',
        'Avatars',
        'PrivateMessages',
        'Signatures',
        'Attachments',
        'Bookmarks',
        'Permissions',
        'Badges',
        'UserNotes',
        'Ranks',
        'Groups',
        'Tags',
        'Reactions',
        'Articles',
    ];

    public const CLI_OPTIONS = array(
        // Used shortcodes: t, n, u, p, h, x, a, c, f, d, o, s, b
        'type' => array(
            'Type of forum we\'re freeing you from.',
            'Req' => true,
            'Sx' => ':',
            'Field' => 'type',
            'Short' => 't',
        ),
        'dbname' => array(
            'Database name.',
            'Req' => true,
            'Sx' => ':',
            'Field' => 'dbname',
            'Short' => 'n',
        ),
        'user' => array(
            'Database connection username.',
            'Req' => true,
            'Sx' => ':',
            'Field' => 'dbuser',
            'Short' => 'u',
        ),
        'password' => array(
            'Database connection password.',
            'Sx' => '::',
            'Field' => 'dbpass',
            'Short' => 'p',
            'Default' => '',
        ),
        'host' => array(
            'IP address or hostname to connect to. Default is 127.0.0.1.',
            'Sx' => ':',
            'Field' => 'dbhost',
            'Short' => 'o',
            'Default' => '127.0.0.1',
        ),
        'prefix' => array(
            'The table prefix in the database.',
            'Field' => 'prefix',
            'Sx' => ':',
            'Short' => 'x',
            'Default' => 'PACKAGE_DEFAULT',
        ),
        'avatars' => array(
            'Enables exporting avatars from the database if supported.',
            'Sx' => '::',
            'Field' => 'avatars',
            'Short' => 'a',
            'Default' => false,
        ),
        'cdn' => array(
            'Prefix to be applied to file paths.',
            'Field' => 'cdn',
            'Sx' => ':',
            'Short' => 'c',
            'Default' => '',
        ),
        'files' => array(
            'Enables exporting attachments from database if supported.',
            'Sx' => '::',
            'Short' => 'f',
            'Default' => false,
        ),
        'destpath' => array(
            'Define destination path for the export file.',
            'Sx' => '::',
            'Short' => 'd',
            'Default' => './',
        ),
        'spawn' => array(
            'Create a new package with this name.',
            'Sx' => '::',
            'Short' => 's',
            'Default' => '',
        ),
        'help' => array(
            'Show this help, duh.',
            'Short' => 'h',
        ),
        'tables' => array(
            'Selective export, limited to specified tables, if provided',
            'Sx' => ':',
        )
    );

    private static $instance = null;

    private $packages = [];

    private function __construct()
    {
        // Do nothing.
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new SupportManager();
        }

        return self::$instance;
    }

    public function registerSupport(string $name, array $meta)
    {
        $this->packages[$name] = $meta;
    }

    public function getSupport(): array
    {
        return $this->packages;
    }

    public function getOptions(): array
    {
        $options = self::CLI_OPTIONS;
        $options['type']['Values'] = array_keys($this->getSupport());
        return $options;
    }

    public function getSupportList()
    {
        $packages = loadManifest();
        foreach ($packages as $name) {
            $classname = '\NitroPorter\Package\\' . $name;
            $classname::registerSupport();
        }
        return \NitroPorter\SupportManager::getInstance()->getSupport();
    }

    /**
     * Get the data support status for a single platform feature.
     *
     * @param  $platform
     * @param  $feature
     * @return string
     */
    public function featureStatus($platform, $feature, $notes = true)
    {
        $supported = $this->getSupport();

        if (!isset($supported[$platform]['features'])) {
            return '<span class="No">No</span>';
        }

        $available = $supported[$platform]['features'];

        // Calculate feature availability.
        $status = '<span class="No">&#x2717;</span>';
        if (isset($available[$feature])) {
            if ($available[$feature] === 1) {
                $status = '<span class="Yes">&#x2713;</span>';
            } elseif ($available[$feature]) {
                if ($notes) {
                    // Send the text of the note
                    $status = $available[$feature];
                } else {
                    // Say 'yes' for table shorthand
                    $status = '<span class="Yes">&#x2713;</span>';
                }
            }
        }

        return $status;
    }

    /**
     * Define what data can be successfully ported to Vanilla.
     *
     * First array key is where the data is stored.
     * Second array key is the feature name, and value is one of:
     *    - 0 if unsupported
     *    - 1 if supported
     *    - string if supported, with notes or caveats
     *
     * @return array
     */
    public function vanillaFeatures()
    {
        return [
            'Comments' => 0,
            'Discussions' => 0,
            'Users' => 0,
            'Categories' => 0,
            'Roles' => 0,
            'Passwords' => 0,
            'Avatars' => 0,
            'PrivateMessages' => 0,
            'Signatures' => 0,
            'Attachments' => 0,
            'Bookmarks' => 0,
            'Permissions' => 0,
            'Polls' => 0,
            'Tags' => 0,
            'UserNotes' => 0,
            'Badges' => 0,
            'Ranks' => 0,
            'Groups' => 0,
            'Emoji' => 0,
            'UserWall' => 0,
        ];
    }
}
