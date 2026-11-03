<?php

namespace jiujiude\WechatEcommerce;

/** Platform certificate and request configuration. */
class Config
{
    public static $config = [];

    public static function getConfig()
    {
        return self::$config;
    }

    public static function setConfig($config = [])
    {
        if (!is_array($config)) {
            throw new WxPayv3Exception('配置必须为数组');
        }
        self::$config = $config;
    }

    public function __get($name)
    {
        return self::$config[$name] ?? '';
    }
}
