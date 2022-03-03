<?php

namespace App\Dto;

use Illuminate\View\Compilers\ComponentTagCompiler;

class Element
{
    public function __construct(
        public string $name,
        public string $full,
        public int $startsAt,
        public int $endsAt
    ) {
    }

    public function getUsedArguments(): array
    {
        $tagCompiler = new ComponentTagCompiler();
        return array_keys(invade($tagCompiler)->getAttributesFromAttributeString($this->full));
    }
}