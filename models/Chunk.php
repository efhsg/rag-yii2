<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%chunks}}".
 *
 * @property int $id
 * @property int $document_id
 * @property int $chunk_index
 * @property string $text
 * @property string $embedding_json
 *
 * @property Document $document
 */
class Chunk extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%chunks}}';
    }

    public function rules()
    {
        return [
            [['document_id', 'chunk_index', 'text', 'embedding_json'], 'required'],
            [['document_id', 'chunk_index'], 'integer'],
            [['text', 'embedding_json'], 'string'],
            [
                ['document_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Document::class,
                'targetAttribute' => ['document_id' => 'id'],
            ],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'document_id' => 'Document ID',
            'chunk_index' => 'Chunk Index',
            'text' => 'Text',
            'embedding_json' => 'Embedding JSON',
        ];
    }

    public function getDocument()
    {
        return $this->hasOne(Document::class, ['id' => 'document_id'])->inverseOf('chunks');
    }
}
