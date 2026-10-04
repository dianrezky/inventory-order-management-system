<?php

namespace App\Repository\Fake;

use App\Core\Result;
use App\Repository\Interface\FileValidationRepositoryInterface;

class FileValidationFakeRepository implements FileValidationRepositoryInterface
{
    private $rules;

    // $rules: ['jpg' => ['extension' => 'jpg', 'mime_type' => 'image/jpeg',
    //          'header_hex' => 'FFD8FF', 'footer_hex' => 'FFD9', 'read_bytes' => 3], ...]
    public function __construct($rules = [])
    {
        $this->rules = $rules;
    }

    public function findActiveRules()
    {
        $result = new Result();
        $result->code = Result::CODE_SUCCESS;
        $result->info = 'Success to find file validation rules';
        $result->data = $this->rules;

        return $result;
    }
}
