<?php

namespace App;

class SimpleState
{
    const FILENAME =  __DIR__ . '/../../data/state.json';

    public $state = [
        'id' => null
    ];

    function __construct()
    {
        $this->load();
    }

    function load()
    {
        $this->state = (array)json_decode(file_get_contents(self::FILENAME));
    }

    public function save()
    {
        file_put_contents(self::FILENAME, json_encode($this->state));
    }

    public function setAttr($attr, $value)
    {
        $this->state[$attr] = $value;
        $this->save();
    }
    public function getAttr($attr)
    {
        return $this->state[$attr] ?? null;
    }

}
