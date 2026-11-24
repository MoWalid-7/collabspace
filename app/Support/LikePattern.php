<?php

namespace App\Support;

class LikePattern
{
    public static function contains(string $term): string
    {
        return '%'.mb_strtolower(addcslashes($term, '\\%_')).'%';
    }
}
