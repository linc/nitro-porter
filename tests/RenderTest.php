<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RenderTest extends TestCase
{
    /**
     * @covers ::pageFooter
     */
    public function testCanBeUsedAsString(): void
    {
        ob_start();
        pageFooter();
        $output = ob_get_clean();
        $this->assertStringContainsString('</html>', $output);
    }
}
