<?php

namespace app\controllers;

use app\components\rag\MistralClient;
use app\models\Chunk;
use app\models\Document;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\NotInstantiableException;
use yii\web\Controller;
use yii\web\Response;

/**
 * ChatController exposes a simple RAG-backed chat UI and
 * a JSON endpoint for question answering over wiki content.
 */
class ChatController extends Controller
{
    public function actionIndex(): string
    {
        $request = Yii::$app->request;
        $question = trim((string)$request->post('question', ''));
        $answer = null;
        $contextItems = [];
        $error = null;

        if ($question !== '') {
            try {
                $result = $this->runRagQuestionAnswering($question);

                $answer = $result['answer'];
                $contextItems = $result['contextItems'];
                $error = $result['error'];
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('index', [
            'question' => $question,
            'answer' => $answer,
            'contextItems' => $contextItems,
            'error' => $error,
        ]);
    }

    public function actionAsk(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $question = trim((string)Yii::$app->request->post('question', ''));

        if ($question === '') {
            return $this->asJson([
                'success' => false,
                'error' => 'Question cannot be empty.',
            ]);
        }

        try {
            $result = $this->runRagQuestionAnswering($question);

            if ($result['error'] !== null) {
                return $this->asJson([
                    'success' => false,
                    'error' => $result['error'],
                ]);
            }

            return $this->asJson([
                'success' => true,
                'question' => $question,
                'answer' => $result['answer'],
                'context' => $result['contextItems'],
            ]);
        } catch (Throwable $exception) {
            return $this->asJson([
                'success' => false,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @throws GuzzleException
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    private function runRagQuestionAnswering(string $question): array
    {
        /** @var MistralClient $mistral */
        $mistral = Yii::$container->get(MistralClient::class);

        $questionEmbedding = $mistral->generateEmbedding($question);
        $questionEmbedding = $this->normalizeVector($questionEmbedding);

        /** @var Chunk[] $chunks */
        $chunks = Chunk::find()
            ->where(['not', ['embedding_json' => null]])
            ->andWhere(['!=', 'embedding_json', ''])
            ->andWhere(['!=', 'embedding_json', '[]'])
            ->all();

        if (!$chunks) {
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

        if (!$scoredChunks) {
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

        $topK = 5;
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

        $answer = $mistral->generateChatResponse($question, $contextForModel);

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
