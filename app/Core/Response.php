<?php

namespace App\Core;

class Response
{
    public const TYPE_REDIRECT = 'redirect';
    public const TYPE_JSON = 'json';
    public const TYPE_NOT_FOUND = 'not_found';
    public const TYPE_FORBIDDEN = 'forbidden';
    public const TYPE_BAD_REQUEST = 'bad_request';
    public const TYPE_CSV = 'csv';

    protected $type;

    protected $data;

    protected $statusCode;

    public static function redirect($url, $statusCode = 302)
    {
        $response = new self();
        $response->type = self::TYPE_REDIRECT;
        $response->data = $url;
        $response->statusCode = $statusCode;

        return $response;
    }

    public static function json($data, $statusCode = 200)
    {
        $response = new self();
        $response->type = self::TYPE_JSON;
        $response->data = $data;
        $response->statusCode = $statusCode;

        return $response;
    }

    public static function notFound($message = 'Page not found.')
    {
        $response = new self();
        $response->type = self::TYPE_NOT_FOUND;
        $response->data = $message;
        $response->statusCode = 404;

        return $response;
    }

    public static function forbidden($message = "You don't have permission to access this page.")
    {
        $response = new self();
        $response->type = self::TYPE_FORBIDDEN;
        $response->data = $message;
        $response->statusCode = 403;

        return $response;
    }

    public static function badRequest($message = 'Bad request.')
    {
        $response = new self();
        $response->type = self::TYPE_BAD_REQUEST;
        $response->data = $message;
        $response->statusCode = 400;

        return $response;
    }

    public static function csv($content, $filename)
    {
        $response = new self();
        $response->type = self::TYPE_CSV;
        $response->data = [
            'content' => $content,
            'filename' => $filename,
        ];
        $response->statusCode = 200;

        return $response;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
