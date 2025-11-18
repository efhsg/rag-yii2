<?php

namespace tests\unit\components\rag;

use app\components\rag\WordChunkStrategy;
use Codeception\Test\Unit;

/**
 * Unit tests for the WordChunkStrategy class.
 *
 * Verifies that text is split into overlapping word chunks according to
 * maximum token count and overlap configuration.
 */
class WordChunkStrategyTest extends Unit
{
    public function testEmptyInputReturnsEmptyArray(): void
    {
        $strategy = new WordChunkStrategy(10, 2);

        $chunks = (array) $strategy->chunk("   \n\t  ");
        /** @var string[] $chunks */
        /** @var mixed $chunks */

        /** @var mixed $expected */
        $expected = [];

        $this->assertSame($expected, $chunks);
    }

    public function testChunksRespectMaxTokensAndOverlap(): void
    {
        $strategy = new WordChunkStrategy(5, 2);
        $text = implode(' ', array_map(static fn ($i) => "word{$i}", range(1, 12)));

        $chunks = (array) $strategy->chunk($text);
        /** @var string[] $chunks */
        /** @var mixed $chunks */

        /** @var mixed $expected */
        $expected = [
            'word1 word2 word3 word4 word5',
            'word4 word5 word6 word7 word8',
            'word7 word8 word9 word10 word11',
            'word10 word11 word12',
        ];

        $this->assertSame($expected, $chunks);
    }

    public function testSingleChunkWhenTextShorterThanMaxTokens(): void
    {
        $strategy = new WordChunkStrategy(10, 3);

        $chunks = (array) $strategy->chunk('one two three four');
        /** @var string[] $chunks */
        /** @var mixed $chunks */

        /** @var mixed $expected */
        $expected = ['one two three four'];

        $this->assertSame($expected, $chunks);
    }

    public function testExactMultipleOfMaxTokens(): void
    {
        $strategy = new WordChunkStrategy(3, 1);
        $text = 'a b c d e f';

        $chunks = (array) $strategy->chunk($text);
        /** @var string[] $chunks */
        /** @var mixed $chunks */

        /** @var mixed $expected */
        $expected = [
            'a b c',
            'c d e',
            'e f',
        ];

        $this->assertSame($expected, $chunks);
    }

    public function testZeroOverlapProducesNonOverlappingChunks(): void
    {
        $strategy = new WordChunkStrategy(4, 0);
        $text = implode(' ', array_map(static fn ($i) => "token{$i}", range(1, 9)));

        $chunks = (array) $strategy->chunk($text);
        /** @var string[] $chunks */
        /** @var mixed $chunks */

        /** @var mixed $expected */
        $expected = [
            'token1 token2 token3 token4',
            'token5 token6 token7 token8',
            'token9',
        ];

        $this->assertSame($expected, $chunks);
    }

    public function testMultipleWhitespacesAreNormalized(): void
    {
        $strategy = new WordChunkStrategy(3, 1);
        $text = "one\t two\nthree    four";

        $chunks = (array) $strategy->chunk($text);
        /** @var string[] $chunks */
        /** @var mixed $chunks */

        /** @var mixed $expected */
        $expected = [
            'one two three',
            'three four',
        ];

        $this->assertSame($expected, $chunks);
    }
}
