<?php

namespace jiujiude\WechatEcommerce;

/** 微信支付 APIv3 平台公钥/证书及请求配置。 */
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
     *
     * 常用配置项包括：MCHID、SSLKEY_PATH、SERIAL_NO、COMBINE_APPID、APPKERV3。
     * 使用微信支付公钥模式验签或加密敏感信息时，还需配置
     * PLATFORM_PUBLIC_KEY_ID（商户平台中的微信支付公钥 ID）和
     * PLATFORM_PUBLIC_KEY_PATH（对应的 PEM 公钥文件路径）。
     *
     * @param array $config SDK 配置数组
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
