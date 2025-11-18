<?php

use yii\db\Migration;

/**
 * Handles the creation of tables `{{%documents}}` and `{{%chunks}}`.
 */
class m241118_120000_create_rag_tables extends Migration
{
    public function safeUp()
    {
        $supportsForeignKeys = $this->supportsForeignKeys();

        $this->createTable('{{%documents}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'source_id' => $this->string(255)->notNull()->unique(),
        ]);

        $this->createTable('{{%chunks}}', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->notNull(),
            'chunk_index' => $this->integer()->notNull(),
            'text' => $this->text()->notNull(),
            'embedding_json' => $this->text()->notNull(),
        ]);

        $this->createIndex(
            'idx-chunks-document',
            '{{%chunks}}',
            'document_id'
        );

        $this->createIndex(
            'idx-chunks-document_order',
            '{{%chunks}}',
            ['document_id', 'chunk_index']
        );

        if ($supportsForeignKeys) {
            $this->addForeignKey(
                'fk-chunks-document',
                '{{%chunks}}',
                'document_id',
                '{{%documents}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        if ($this->supportsForeignKeys()) {
            $this->dropForeignKey(
                'fk-chunks-document',
                '{{%chunks}}'
            );
        }

        $this->dropIndex(
            'idx-chunks-document_order',
            '{{%chunks}}'
        );

        $this->dropIndex(
            'idx-chunks-document',
            '{{%chunks}}'
        );

        $this->dropTable('{{%chunks}}');
        $this->dropTable('{{%documents}}');
    }

    private function supportsForeignKeys(): bool
    {
        return $this->db->driverName !== 'sqlite';
    }
}
