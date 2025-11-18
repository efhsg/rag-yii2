<?php


use yii\db\Migration;

class m241118_130000_add_content_and_path_to_documents extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%documents}}', 'content', $this->text()->notNull()->defaultValue(''));
        $this->addColumn('{{%documents}}', 'file_path', $this->string(500)->null());
    }

    public function safeDown()
    {
        $this->dropColumn('{{%documents}}', 'file_path');
        $this->dropColumn('{{%documents}}', 'content');
    }
}
