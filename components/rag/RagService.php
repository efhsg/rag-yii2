<?php

namespace app\components\rag;

use app\models\Chunk;
use app\models\Document;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;
use Yii;

/**
 * RagService centralizes embedding generation and retrieval-augmented answering.
 * Controllers depend on this service so the RAG behaviour stays consistent everywhere.
 */
class RagService
{
    public function __construct(private MistralClient $mistralClient)
    {
    }

    /**
     * @throws Throwable
     */
    public function buildEmbeddings(int $limit = 500): int
    {
        $query = Chunk::find()
            ->where([
                'or',
                ['embedding_json' => '[]'],
                ['embedding_json' => ''],
                ['embedding_json' => null],
            ])
            ->orderBy(['id' => SORT_ASC])
            ->limit($limit);

        /** @var Chunk[] $chunks */
        $chunks = $query->all();

        if ($chunks === []) {
            return 0;
        }

        $processed = 0;

        foreach ($chunks as $chunk) {
            try {
                $vector = $this->mistralClient->generateEmbedding($chunk->text);
                $normalized = $this->normalizeVector($vector);
                $chunk->embedding_json = json_encode($normalized);

                if (!$chunk->save(false, ['embedding_json'])) {
                    Yii::error([
                        'message' => 'Failed to save embedding.',
                        'chunkId' => $chunk->id,
                        'errors' => $chunk->getErrors(),
                    ]);
                    continue;
                }

                $processed++;
            } catch (Throwable $exception) {
                Yii::error([
                    'message' => 'Error generating embedding.',
                    'chunkId' => $chunk->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function answerQuestion(string $question, int $topK = 5): array
    {
        $question = trim($question);

        if ($question === '') {
            return [
                'answer' => null,
                'contextItems' => [],
                'error' => 'Question cannot be empty.',
            ];
        }

        $questionEmbedding = $this->mistralClient->generateEmbedding($question);
        $questionEmbedding = $this->normalizeVector($questionEmbedding);

        /** @var Chunk[] $chunks */
        $chunks = Chunk::find()
            ->where(['not', ['embedding_json' => null]])
            ->andWhere(['!=', 'embedding_json', ''])
            ->andWhere(['!=', 'embedding_json', '[]'])
            ->all();

        if ($chunks === []) {
            return [
                'answer' => null,
                'contextItems' => [],
                'error' => 'No chunks with embeddings found. Run the console commands to import, chunk and embed first.',
            ];
        }

        $scoredChunks = [];

        foreach ($chunks as $chunk) {
            $vector = json_decode($chunk->embedding_json, true);

            if (!is_array($vector) || $vector === []) {
                continue;
            }

            $score = $this->cosineSimilarity($questionEmbedding, $vector);

            $scoredChunks[] = [
                'chunk' => $chunk,
                'score' => $score,
            ];
        }

        if ($scoredChunks === []) {
            return [
                'answer' => null,
                'contextItems' => [],
                'error' => 'No valid embeddings found to score against.',
            ];
        }

        usort(
            $scoredChunks,
            static function (array $a, array $b): int {
                return $b['score'] <=> $a['score'];
            }
        );

        $topK = max(1, $topK);
        $topChunks = array_slice($scoredChunks, 0, $topK);

        $contextForModel = [];
        $contextItems = [];

        foreach ($topChunks as $index => $item) {
            /** @var Chunk $chunk */
            $chunk = $item['chunk'];
            $score = $item['score'];
            /** @var Document|null $document */
            $document = $chunk->document;
            $title = $document ? $document->title : 'Unknown document';
            $sourceId = $document ? $document->source_id : 'n/a';

            $contextForModel[] = [
                'content' => $chunk->text,
            ];

            $contextItems[] = [
                'rank' => $index + 1,
                'title' => $title,
                'sourceId' => $sourceId,
                'score' => $score,
                'preview' => mb_substr($chunk->text, 0, 200),
            ];
        }

        $answer = $this->mistralClient->generateChatResponse($question, $contextForModel);

        return [
            'answer' => $answer,
            'contextItems' => $contextItems,
            'error' => null,
        ];
    }

    private function normalizeVector(array $vector): array
    {
        $sumSq = 0.0;

        foreach ($vector as $value) {
            $sumSq += $value * $value;
        }

        if ($sumSq <= 0.0) {
            return $vector;
        }

        $norm = sqrt($sumSq);

        foreach ($vector as $index => $value) {
            $vector[$index] = $value / $norm;
        }

        return $vector;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $lengthA = count($a);
        $lengthB = count($b);

        if ($lengthA === 0 || $lengthB === 0 || $lengthA !== $lengthB) {
            return 0.0;
        }

        $dot = 0.0;

        for ($i = 0; $i < $lengthA; $i++) {
            $dot += $a[$i] * $b[$i];
        }

        return $dot;
    }
}
