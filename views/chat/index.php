<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var string $question */
/** @var string|null $answer */
/** @var array $contextItems */
/** @var string|null $error */

$this->title = 'Wiki RAG Chat';
?>
<div class="site-chat">
    <div class="jumbotron text-center bg-light border rounded p-4 mb-4">
        <h1 class="display-5 mb-3">
            Wiki RAG Chat
        </h1>
        <p class="lead mb-1">
            Ask questions about your imported wiki pages.
        </p>
        <p class="text-muted mb-0">
            The assistant will retrieve relevant chunks and answer based on that context.
        </p>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <strong>Your question</strong>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= Url::to(['chat/index']) ?>">
                        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>

                        <div class="form-group mb-3">
                            <label for="question-input" class="form-label">
                                Ask anything about the wiki content
                            </label>
                            <textarea
                                    id="question-input"
                                    name="question"
                                    rows="4"
                                    class="form-control"
                                    placeholder="Example: What is retrieval-augmented generation?"
                            ><?= Html::encode($question) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <button type="submit" class="btn btn-primary">
                                Ask
                            </button>
                            <small class="text-muted">
                                Powered by Mistral embeddings + chat
                            </small>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            if ($error): ?>
                <div class="alert alert-danger">
                    <?= Html::encode($error) ?>
                </div>
            <?php
            endif; ?>

            <?php
            if ($answer !== null && !$error): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <strong>Answer</strong>
                    </div>
                    <div class="card-body">
                        <div class="chat-answer">
                            <?= nl2br(Html::encode($answer)) ?>
                        </div>
                    </div>
                </div>
            <?php
            endif; ?>
        </div>

        <div class="col-md-5">
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <strong>Context used</strong>
                </div>
                <div class="card-body">
                    <?php
                    if (!$contextItems): ?>
                        <p class="text-muted mb-0">
                            No context yet. Ask a question to see which wiki chunks are used.
                        </p>
                    <?php
                    else: ?>
                        <ol class="list-unstyled mb-0">
                            <?php
                            foreach ($contextItems as $item): ?>
                                <li class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong>
                                            #<?= (int)$item['rank'] ?>
                                            <?= Html::encode($item['title']) ?>
                                        </strong>
                                        <span class="text-muted small">
                                            score <?= number_format((float)$item['score'], 4) ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <?= Html::encode($item['sourceId']) ?>
                                    </div>
                                    <div class="small">
                                        <?= Html::encode($item['preview']) ?>…
                                    </div>
                                </li>
                            <?php
                            endforeach; ?>
                        </ol>
                    <?php
                    endif; ?>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-body text-muted small">
                    <strong>How this works</strong>
                    <ul class="mt-2 mb-0">
                        <li>Wiki markdown files are imported as documents.</li>
                        <li>Documents are split into overlapping chunks.</li>
                        <li>Each chunk is embedded with Mistral.</li>
                        <li>Your question is embedded and matched via cosine similarity.</li>
                        <li>Top chunks are sent as context to the chat model.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
