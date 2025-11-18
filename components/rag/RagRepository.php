<?php

namespace app\components\rag;

use yii\db\Connection;

class RagRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function insertDocument(Document $document): int
    {
        $this->db->createCommand()->upsert(
            'documents',
            [
                'title' => $document->getTitle(),
                'source_id' => $document->getSourceId(),
                'content' => $document->getContent(),
                'file_path' => $document->getFilePath(),
            ],
            false
        )->execute();

        return (int) $this->db->getLastInsertID();
    }

    /**
     * Delete all chunks.
     */
    public function deleteAllChunks(): int
    {
        return $this->db->createCommand()
            ->delete('chunks')
            ->execute();
    }

    /**
     * @return array[] rows with keys: id, title, source_id, content, file_path
     */
    public function findAllDocuments(): array
    {
        return $this->db->createCommand(
            'SELECT id, title, source_id, content, file_path FROM documents ORDER BY id'
        )->queryAll();
    }

    /**
     * Insert a single chunk row.
     */
    public function insertChunk(int $documentId, int $chunkIndex, string $text, string $embeddingJson = '[]'): int
    {
        $this->db->createCommand()->insert('chunks', [
            'document_id' => $documentId,
            'chunk_index' => $chunkIndex,
            'text' => $text,
            'embedding_json' => $embeddingJson,
        ])->execute();

        return (int) $this->db->getLastInsertID();
    }
}
