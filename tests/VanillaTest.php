<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class VanillaTest extends TestCase
{
    public function setUp(): void
    {
        $app = new Phinx\Console\PhinxApplication();
        $app->setAutoExit(false);
        $app->run(new StringInput('migrate'), new NullOutput());
        $app->run(new StringInput('seed:run'), new NullOutput());
    }
}
