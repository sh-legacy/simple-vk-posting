<?php


namespace Services;


class ConfigProvider
{
    public static function getConfig()
    {
        return json_decode(file_get_contents('config.json'), true);
    }
}