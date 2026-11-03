<?php

namespace jiujiude\WechatEcommerce;

/** 微信支付 APIv3 平台证书及请求配置。 */
class Config
{
    /** SDK 配置项。 */
    public static $config = [];

    /** 获取当前 SDK 配置。 */
    public static function getConfig()
    {
        return self::$config;
    }

    /**
     * 设置 SDK 配置。
     * @param array $config 配置数组，包含商户号、证书路径及 APIv3 密钥等信息
     */
    public static function setConfig($config = [])
    {
        if (!is_array($config)) {
            throw new WxPayv3Exception('配置必须为数组');
        }
        self::$config = $config;
    }

    /** 通过属性方式读取配置项；未配置时返回空字符串。 */
    public function __get($name)
    {
        return self::$config[$name] ?? '';
    }
}
