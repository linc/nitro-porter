<?php

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class VanillaTest extends TestCase
{
    protected function setUp(): void
    {
        // @see https://book.cakephp.org/phinx/0/en/commands.html
        // "PDOException: SQLSTATE[42S02]: Base table or view not found" = `truncate table phinxlog`
        $configArray = require('phinx.php');
        $config = new Config($configArray);
        $manager = new Manager($config, new StringInput(' '), new NullOutput());
        $manager->migrate('test');
        $manager->seed('test');
    }

    public function testItSeedsDatabaseLol(): bool
    {
        $this->markTestIncomplete('Not written yet.');
    }
}
