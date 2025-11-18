<?php

namespace app\commands;

use app\components\rag\MistralClient;
use app\components\rag\WordChunkStrategy;
use app\models\Chunk;
use app\models\Document;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Exception;
use yii\di\NotInstantiableException;

class RagController extends Controller
{
    /**
     * Import all .md files (recursively) from a directory into the documents table.
     *
     * Usage:
     * php yii rag/import-wiki @app/runtime/wiki
     * php yii rag/import-wiki C:\www\cc\rag-yii2\runtime\wiki
     * @throws Exception
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
     * php yii rag/build-chunks
     * @throws Exception
     */
    public function actionBuildChunks(): int
    {
        $this->stdout("Deleting all chunks...\n");
        $deleted = Chunk::deleteAll();
        $this->stdout("Deleted $deleted chunks.\n");

        $documents = Document::find()->all();
        if (empty($documents)) {
            $this->stdout("No documents found. Run rag/import-wiki first.\n");
            return ExitCode::OK;
        }

        $strategy = new WordChunkStrategy(250, 50);

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

    /**
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    public function actionBuildEmbeddings(int $limit = 200): int
    {
        // Haal MistralClient uit de DI-container (singleton)
        /** @var MistralClient $mistral */
        $mistral = Yii::$container->get(MistralClient::class);

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

        if (empty($chunks)) {
            $this->stdout("No chunks without embeddings found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Generating embeddings for " . count($chunks) . " chunks...\n");

        $processed = 0;

        foreach ($chunks as $chunk) {
            $text = $chunk->text;

            try {
                $vector = $mistral->generateEmbedding($text);
                $normalized = $this->normalizeVector($vector);

                $chunk->embedding_json = json_encode($normalized);

                // alleen embedding_json opslaan, geen validation nodig
                if (!$chunk->save(false, ['embedding_json'])) {
                    $this->stderr("Failed to save embedding for chunk #$chunk->id\n");
                    $this->stderr(print_r($chunk->getErrors(), true));
                    continue;
                }

                $processed++;
                $this->stdout("Chunk #$chunk->id: embedding saved.\n");
            } catch (Throwable $e) {
                $this->stderr("Error embedding chunk #$chunk->id: {$e->getMessage()}\n");
            }
        }

        $this->stdout("Done. Embeddings generated for $processed chunks.\n");

        return ExitCode::OK;
    }

    /**
     * Normalize a vector to unit length (for cosine similarity later).
     *
     * @param float[] $vector
     * @return float[]
     */
    private function normalizeVector(array $vector): array
    {
        $sumSq = 0.0;
        foreach ($vector as $v) {
            $sumSq += $v * $v;
        }

        if ($sumSq <= 0.0) {
            return $vector;
        }

        $norm = sqrt($sumSq);
        foreach ($vector as $i => $v) {
            $vector[$i] = $v / $norm;
        }

        return $vector;
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
