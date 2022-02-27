<?php

namespace App\Dto;

interface SnippetDto
{
    public function toEntry(): Snippet;
}
