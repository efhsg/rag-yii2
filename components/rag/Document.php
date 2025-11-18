<?php

namespace app\components\rag;

class Document
{
    private string $sourceId;
    private string $title;
    private string $content;
    private ?string $filePath;

    public function __construct(string $sourceId, string $title, string $content, ?string $filePath = null)
    {
        $this->sourceId = $sourceId;
        $this->title = $title;
        $this->content = $content;
        $this->filePath = $filePath;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}
