<?php

namespace jiujiude\WechatEcommerce;

/**
 * 微信支付 APIv3 回调及请求异常类。
 * 继承 RuntimeException，便于与 SDK 的其他运行时异常保持一致。
 */
class WxPayv3Exception extends \\RuntimeException
{
    /** 获取异常错误信息。 */
    public function errorMessage()
    {
        return $this->getMessage();
    }
}
