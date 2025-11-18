<?php


namespace app\components\rag;

/**
 * Strategy interface for generating chunks from a text.
 */
interface ChunkStrategyInterface
{
    /**
     * @return string[] List of chunk texts in order.
     */
    public function chunk(string $text): array;
}
