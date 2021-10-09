<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Phinx\Config\Config;
use Phinx\Migration\Manager;

class VanillaTest extends TestCase
{
    public function setUp(): void
    {
        $app = new Phinx\Console\PhinxApplication();
        $app->setAutoExit(false);
        $app->run(new StringInput('migrate'), new NullOutput());
        $app->run(new StringInput('seed:run'), new NullOutput());
    }

    public function seed(): void
    {
        $configArray = require('phinx.php');
        $config = new Config($configArray);
        $manager = new Manager($config, new StringInput(' '), new NullOutput());
        $manager->seed('testing');
    }

    public function testItSeedsDatabaseLol(): bool
    {
        //$this->seed();
        $this->markTestIncomplete('Not written yet.');
    }
}
