<?php

namespace app\commands;

use app\components\rag\Document;
use app\components\rag\RagRepository;
use app\components\rag\WordChunkStrategy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class RagController extends Controller
{
    private RagRepository $repository;

    public function __construct($id, $module, RagRepository $repository, $config = [])
    {
        $this->repository = $repository;
        parent::__construct($id, $module, $config);
    }

    /**
     * Import all .md files (recursively) from a directory alias into the documents table.
     *
     * Usage:
     * php yii rag/import-wiki @app/runtime/wiki
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
            $document = $this->createDocumentFromFile($basePath, $filePath);

            if ($document === null) {
                continue;
            }

            $this->repository->insertDocument($document);
            $this->stdout("Imported: {$document->getSourceId()}\n");
            $imported++;
        }

        $this->stdout("Done. Imported $imported documents.\n");

        return ExitCode::OK;
    }

    /**
     * Delete all chunks and re-chunk all documents using a chunk strategy.
     *
     * Usage:
     * php yii rag/build-chunks
     */
    public function actionBuildChunks(): int
    {
        $this->stdout("Deleting existing chunks...\n");
        $deleted = $this->repository->deleteAllChunks();
        $this->stdout("Deleted $deleted chunks.\n");

        $documents = $this->repository->findAllDocuments();
        if (empty($documents)) {
            $this->stdout("No documents found. Run rag/import-wiki first.\n");
            return ExitCode::OK;
        }

        // Choose the chunking strategy (can be swapped later)
        $strategy = new WordChunkStrategy(250, 50);

        $this->stdout("Chunking " . count($documents) . " documents...\n");

        $totalChunks = 0;

        foreach ($documents as $row) {
            $docId = (int)$row['id'];
            $content = (string)$row['content'];

            $chunks = $strategy->chunk($content);
            if (empty($chunks)) {
                $this->stdout("Document #$docId has no content to chunk.\n");
                continue;
            }

            foreach ($chunks as $index => $chunkText) {
                $this->repository->insertChunk($docId, $index, $chunkText, '[]');
                $totalChunks++;
            }

            $this->stdout("Document #$docId: created " . count($chunks) . " chunks.\n");
        }

        $this->stdout("Done. Total chunks created: $totalChunks.\n");

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
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
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

    /**
     * Build a Document value object from a file path.
     */
    private function createDocumentFromFile(string $basePath, string $filePath): ?Document
    {
        $content = file_get_contents($filePath);

        if ($content === false || $content === '') {
            $this->stderr("Skipped empty or unreadable file: $filePath\n");
            return null;
        }

        // relative path (for source_id), normalised to forward slashes
        $relative = substr($filePath, strlen(rtrim($basePath, DIRECTORY_SEPARATOR)) + 1);
        $relative = str_replace('\\', '/', $relative);

        $sourceId = $relative; // guaranteed unique binnen deze import
        $fileNameWithoutExt = pathinfo($relative, PATHINFO_FILENAME);

        // Simple title: bestandsnaam zonder extensie, mappen/underscores netjes maken
        $title = ucwords(str_replace(['/', '_', '-'], ' ', $fileNameWithoutExt));

        return new Document(
            $sourceId,
            $title,
            $content,
            $filePath
        );
    }
}
