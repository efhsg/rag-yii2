<?php

namespace tests\unit\components\rag;

use app\components\rag\SemanticChunkStrategy;
use Codeception\Test\Unit;

/**
 * Unit tests for the SemanticChunkStrategy class.
 *
 * Ensures semantic block chunking, overlap, and empty input handling behave as expected.
 */
class SemanticChunkStrategyTest extends Unit
{
    public function testEmptyInputReturnsEmptyArray(): void
    {
        $strategy = new SemanticChunkStrategy(50, 10);
        $chunks = $strategy->chunk("   \n\t  ");

        /** @var string[] $expected */
        $expected = [];

        $this->assertSame($expected, $chunks);
    }

    public function testHeadingsAndParagraphsStayTogether(): void
    {
        $strategy = new SemanticChunkStrategy(100, 10);
        $text = <<<'TEXT'
# Intro
First paragraph line one.
Line two continues.

## Details
Another paragraph here.

Final thoughts line.
TEXT;

        $chunks = $strategy->chunk($text);

        /** @var string[] $expected */
        $expected = [
            "# Intro\n\nFirst paragraph line one.\nLine two continues.\n\n## Details\n\nAnother paragraph here.\n\nFinal thoughts line.",
        ];

        $this->assertSame($expected, $chunks);
    }

    public function testOverlapRepeatsLastBlockAcrossChunks(): void
    {
        $strategy = new SemanticChunkStrategy(10, 5);
        $text = <<<'TEXT'
# One
First block text goes here.

## Two
Second block line.

### Three
Third block line.
TEXT;

        $chunks = $strategy->chunk($text);

        /** @var string[] $expected */
        $expected = [
            "# One\n\nFirst block text goes here.\n\n## Two",
            "## Two\n\nSecond block line.\n\n### Three\n\nThird block line.",
        ];

        $this->assertSame($expected, $chunks);
    }
}
