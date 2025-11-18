<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%documents}}".
 *
 * @property int $id
 * @property string $title
 * @property string $source_id
 * @property string $content
 * @property string|null $file_path
 *
 * @property Chunk[] $chunks
 */
class Document extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%documents}}';
    }

    public function rules()
    {
        return [
            [['title', 'source_id', 'content'], 'required'],
            [['content'], 'string'],
            [['title', 'source_id'], 'string', 'max' => 255],
            [['file_path'], 'string', 'max' => 500],
            [['source_id'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'source_id' => 'Source ID',
            'content' => 'Content',
            'file_path' => 'File Path',
        ];
    }

    public function getChunks()
    {
        return $this->hasMany(Chunk::class, ['document_id' => 'id'])->inverseOf('document');
    }
}
