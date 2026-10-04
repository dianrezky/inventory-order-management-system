<?php

namespace App\Core;

class ViewModel
{
    protected $template;

    protected $data = [];

    protected $layout = 'layouts/main';

    protected $statusCode = 200;

    public function __construct($template, $data = [], $layout = 'layouts/main')
    {
        $this->template = $template;
        $this->data = $data;
        $this->layout = $layout;
    }

    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setLayout($layout)
    {
        $this->layout = $layout;

        return $this;
    }

    public function getLayout()
    {
        return $this->layout;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setVariable($key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function getVariable($key)
    {
        return $this->data[$key] ?? null;
    }

    public function isTerminal()
    {
        return $this->layout === null;
    }
}
