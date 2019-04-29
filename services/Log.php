<?php


namespace Services;


class Log
{
    private static $logDirectory = 'logs';

    public static function write($str)
    {
        file_put_contents(static::$logDirectory . DIRECTORY_SEPARATOR . date('d_m_Y') . '.log', date('[H:i:s] - ') . $str . PHP_EOL, FILE_APPEND);
    }
}