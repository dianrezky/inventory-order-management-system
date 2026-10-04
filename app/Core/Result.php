<?php

namespace App\Core;

// Result contract (AGENT.md §10.6): assignment MUST use explicit if/else, never a ternary; dependency checks MUST test `code != CODE_SUCCESS || data == null`.
class Result
{
    public const CODE_SUCCESS = 0;
    public const CODE_VALIDATION = 1;
    public const CODE_INTERNAL = 2;

    public const MESSAGE_FAILED_FUNCTION = 'Something went wrong on our side. Please try again later.';

    public $code;
    public $info;
    public $data;

    public function __construct()
    {
        $this->code = self::CODE_SUCCESS;
        $this->info = '';
        $this->data = null;
    }
}
