<?php
namespace NitroPorter;

class SupportManager
{
    const SUPPORTED_META = [
        'name',
        'prefix',
        'features',
        'CommandLine',
    ];

    const SUPPORTED_FEATURES = [
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

    private static $instance = null;

    private $packages = [];

    private function __construct()
    {
        // Do nothing.
    }

    public static function getInstance()
    {
        if (self::$instance == null)
        {
            self::$instance = new SupportManager();
        }

        return self::$instance;
    }

    public function registerSupport(string $name, array $meta)
    {
        $this->packages[$name] = $meta;
    }

    public function getSupport() : array
    {
        return $this->packages;
    }
}
