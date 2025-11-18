<?php

namespace app\controllers;

use app\components\rag\RagService;
use Throwable;
use Yii;
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
                /** @var RagService $ragService */
                $ragService = Yii::$container->get(RagService::class);
                $result = $ragService->answerQuestion($question, 5);

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
            /** @var RagService $ragService */
            $ragService = Yii::$container->get(RagService::class);
            $result = $ragService->answerQuestion($question, 5);

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
}
