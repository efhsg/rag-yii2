<?php

namespace app\components\rag;

class WordChunkStrategy implements ChunkStrategyInterface
{
    private int $maxTokens;
    private int $overlap;

    public function __construct(int $maxTokens = 250, int $overlap = 50)
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

        $words = preg_split('/\s+/', $text) ?: [];
        $chunks = [];
        $total = count($words);

        $start = 0;
        while ($start < $total) {
            $slice = array_slice($words, $start, $this->maxTokens);
            if (empty($slice)) {
                break;
            }

            $chunks[] = implode(' ', $slice);

            $start = $start + $this->maxTokens - $this->overlap;
            if ($start < 0) {
                $start = 0;
            }
        }

        return $chunks;
    }
}
