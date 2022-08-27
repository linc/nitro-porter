<?php

namespace PorterTest;

use PHPUnit\Framework\TestCase;

final class RenderTest extends TestCase
{
    /**
     * @covers Render::pageFooter
     */
    public function testCanBeUsedAsString(): void
    {
        ob_start();
        \Porter\Render::pageFooter();
        $output = ob_get_clean();
        $this->assertStringContainsString('</html>', $output);
    }
}
