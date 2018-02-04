<?php

namespace StarCorp\Lemp\Settings;

class JsonSettings extends ServerSettings
{
    /**
     * Create an instance from a file.
     *
     * @param  string  $filename
     * @return static
     */
    public static function fromFile($filename)
    {
        return new static(json_decode(file_get_contents($filename), true));
    }

    /**
     * Save the server settings.
     *
     * @param  string  $filename
     * @return void
     */
    public function save($filename)
    {
        file_put_contents($filename, json_encode($this->attributes, JSON_PRETTY_PRINT));
    }
}
