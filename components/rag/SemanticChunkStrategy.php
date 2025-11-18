<?php

namespace app\components\rag;

/**
 * Builds semantic chunks based on Markdown headings and paragraphs with overlap.
 * Keeps logical blocks intact and enforces approximate token limits.
 */
class SemanticChunkStrategy implements ChunkStrategyInterface
{
    private int $maxTokens;

    private int $overlap;

    public function __construct(int $maxTokens = 400, int $overlap = 80)
    {
        $this->maxTokens = $maxTokens;
        $this->overlap = $overlap;
    }

    public function chunk(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $blocks = $this->buildBlocks($normalized);
        if (empty($blocks)) {
            return [];
        }

        $chunks = [];
        $currentBlocks = [];
        $currentTokens = 0;
        $lastBlock = null;

        foreach ($blocks as $block) {
            $blockTokens = $this->countApproxTokens($block);
            if ($blockTokens === 0) {
                continue;
            }

            $wouldExceed = $currentBlocks !== [] && $currentTokens + $blockTokens > $this->maxTokens;
            if ($wouldExceed) {
                $lastBlock = end($currentBlocks) ?: null;
                $chunks[] = $this->combineBlocks($currentBlocks);
                $currentBlocks = [];
                $currentTokens = 0;

                if ($lastBlock !== null && $this->shouldOverlap($lastBlock)) {
                    $currentBlocks[] = $lastBlock;
                    $currentTokens = $this->countApproxTokens($lastBlock);
                }
            }

            $currentBlocks[] = $block;
            $currentTokens += $blockTokens;
        }

        if ($currentBlocks !== []) {
            $chunks[] = $this->combineBlocks($currentBlocks);
        }

        return $chunks;
    }

    private function buildBlocks(string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            $isHeading = preg_match('/^#{1,6}\s+/', $line) === 1;
            $isBlank = trim($line) === '';

            if ($isHeading) {
                $this->flushCurrentBlock($blocks, $current);
                $blocks[] = trim($line);
                continue;
            }

            if ($isBlank) {
                $this->flushCurrentBlock($blocks, $current);
                continue;
            }

            $current[] = $line;
        }

        $this->flushCurrentBlock($blocks, $current);

        return $blocks;
    }

    private function flushCurrentBlock(array &$blocks, array &$current): void
    {
        if ($current === []) {
            return;
        }

        $block = trim(implode("\n", $current));
        $current = [];

        if ($block !== '') {
            $blocks[] = $block;
        }
    }

    private function countApproxTokens(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $words !== false ? count($words) : 0;
    }

    private function combineBlocks(array $blocks): string
    {
        return implode("\n\n", $blocks);
    }

    private function shouldOverlap(string $block): bool
    {
        if ($this->overlap <= 0) {
            return false;
        }

        return $this->countApproxTokens($block) <= $this->overlap;
    }
}
