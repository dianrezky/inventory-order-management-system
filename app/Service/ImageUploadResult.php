<?php

namespace App\Service;

// Value object returned by ImageUploadService::process(): path relative to products.image_path plus final dimensions.
class ImageUploadResult
{
    public $path;
    public $width;
    public $height;

    public function __construct($path, $width, $height)
    {
        $this->path = $path;
        $this->width = $width;
        $this->height = $height;
    }
}
