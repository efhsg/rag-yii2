<?php

namespace app\commands;

use app\components\rag\ChunkStrategyInterface;
use app\components\rag\RagService;
use app\components\rag\SemanticChunkStrategy;
use app\components\rag\WordChunkStrategy;
use app\models\Chunk;
use app\models\Document;
use Exception;
use FilesystemIterator;
use GuzzleHttp\Exception\GuzzleException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Exception as DbException;
use yii\di\NotInstantiableException;

class RagController extends Controller
{
    /**
     * Import all .md files (recursively) from a directory into the documents table.
     *
     * Usage:
     * php yii rag/import-wiki @app/runtime/wiki
     * php yii rag/import-wiki C:\www\cc\rag-yii2\runtime\wiki
     * @throws DbException
     */
    public function actionImportWiki(string $dirAlias): int
    {
        $basePath = Yii::getAlias($dirAlias);

        if (!is_dir($basePath)) {
            $this->stderr("Directory not found: $basePath\n");
            return ExitCode::NOINPUT;
        }

        $this->stdout("Scanning for .md files in $basePath ...\n");

        $files = $this->findMarkdownFiles($basePath);

        if (empty($files)) {
            $this->stdout("No .md files found.\n");
            return ExitCode::OK;
        }

        $this->stdout('Found ' . count($files) . " .md files.\n");

        $imported = 0;

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            if ($content === false || $content === '') {
                $this->stderr("Skipped empty or unreadable file: $filePath\n");
                continue;
            }

            // relative path for source_id
            $relative = substr($filePath, strlen(rtrim($basePath, DIRECTORY_SEPARATOR)) + 1);
            $relative = str_replace('\\', '/', $relative);

            $sourceId = $relative;
            $fileNameWithoutExt = pathinfo($relative, PATHINFO_FILENAME);
            $title = ucwords(str_replace(['/', '_', '-'], ' ', $fileNameWithoutExt));

            // Upsert-like behaviour via AR: find by source_id
            $document = Document::findOne(['source_id' => $sourceId]);
            if ($document === null) {
                $document = new Document();
            }

            $document->title = $title;
            $document->source_id = $sourceId;
            $document->content = $content;
            $document->file_path = $filePath;

            if (!$document->save()) {
                $this->stderr("Failed to save document for file: $filePath\n");
                $this->stderr(print_r($document->getErrors(), true));
                continue;
            }

            $this->stdout("Imported: $document->source_id (ID: $document->id)\n");
            $imported++;
        }

        $this->stdout("Done. Imported/updated $imported documents.\n");

        return ExitCode::OK;
    }

    /**
     * Delete all chunks and re-chunk all documents using a chunk strategy.
     *
     * Usage:
     * php yii rag/build-chunks word
     * php yii rag/build-chunks semantic
     * @throws DbException
     */
    public function actionBuildChunks(string $mode = 'word'): int
    {
        $this->stdout("Deleting all chunks...\n");
        $deleted = Chunk::deleteAll();
        $this->stdout("Deleted $deleted chunks.\n");

        $documents = Document::find()->all();
        if (empty($documents)) {
            $this->stdout("No documents found. Run rag/import-wiki first.\n");
            return ExitCode::OK;
        }

        $strategy = $this->resolveChunkStrategy($mode);
        if ($strategy === null) {
            $this->stdout("Unknown mode '$mode'. Use 'word' or 'semantic'.\n");
            return ExitCode::OK;
        }

        $this->stdout("Chunking " . count($documents) . " documents...\n");

        $totalChunks = 0;

        /** @var Document $document */
        foreach ($documents as $document) {
            $chunks = $strategy->chunk($document->content);
            if (empty($chunks)) {
                $this->stdout("Document #$document->id has no content to chunk.\n");
                continue;
            }

            foreach ($chunks as $index => $chunkText) {
                $chunk = new Chunk();
                $chunk->document_id = $document->id;
                $chunk->chunk_index = $index;
                $chunk->text = $chunkText;
                $chunk->embedding_json = '[]';

                if (!$chunk->save()) {
                    $this->stderr("Failed to save chunk for document #$document->id, index $index\n");
                    $this->stderr(print_r($chunk->getErrors(), true));
                    continue;
                }

                $totalChunks++;
            }

            $this->stdout("Document #$document->id: created " . count($chunks) . " chunks.\n");
        }

        $this->stdout("Done. Total chunks created: $totalChunks.\n");

        return ExitCode::OK;
    }

    private function resolveChunkStrategy(string $mode): ?ChunkStrategyInterface
    {
        $mode = strtolower($mode);
        if ($mode === 'semantic') {
            return new SemanticChunkStrategy(250, 50);
        }

        if ($mode === 'word') {
            return new WordChunkStrategy(250, 50);
        }

        return null;
    }

    /**
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    public function actionBuildEmbeddings(int $limit = 500): int
    {
        /** @var RagService $ragService */
        $ragService = Yii::$container->get(RagService::class);

        $this->stdout("Processing chunks without embeddings...\n");

        $processed = $ragService->buildEmbeddings($limit);

        if ($processed === 0) {
            $this->stdout("No chunks without embeddings found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Done. Embeddings generated for $processed chunks.\n");

        return ExitCode::OK;
    }

    /**
     * @throws NotInstantiableException
     * @throws GuzzleException
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionAsk(string $question, int $topK = 5): int
    {
        /** @var RagService $ragService */
        $ragService = Yii::$container->get(RagService::class);

        $this->stdout("Embedding question and retrieving context...\n");

        $result = $ragService->answerQuestion($question, $topK);

        if ($result['error'] !== null) {
            $this->stdout($result['error'] . PHP_EOL);
            return ExitCode::OK;
        }

        $answer = $result['answer'];
        $contextItems = $result['contextItems'];

        $this->stdout(PHP_EOL . "=== ANSWER ===" . PHP_EOL);
        $this->stdout($answer . PHP_EOL . PHP_EOL);

        $this->stdout("=== CONTEXT USED ===" . PHP_EOL);

        foreach ($contextItems as $item) {
            $rank = $item['rank'];
            $score = number_format($item['score'], 4);
            $title = $item['title'];
            $sourceId = $item['sourceId'];
            $preview = str_replace(PHP_EOL, ' ', $item['preview']);

            $this->stdout(
                "#$rank [score $score] $title ($sourceId)" . PHP_EOL .
                "    $preview..." . PHP_EOL
            );
        }

        return ExitCode::OK;
    }

    /**
     * Recursively find all .md files under a base path.
     *
     * @return string[]
     */
    private function findMarkdownFiles(string $basePath): array
    {
        $result = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $basePath,
                FilesystemIterator::SKIP_DOTS
            )
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (strtolower($fileInfo->getExtension()) !== 'md') {
                continue;
            }

            $result[] = $fileInfo->getPathname();
        }

        sort($result);

        return $result;
    }
}
