<?php

namespace jiujiude\WechatEcommerce;

/** PHP微信支付APIv3 SDK transport exception. */
class WxPayv3Exception extends \RuntimeException
{
    public function errorMessage()
    {
        return $this->getMessage();
    }
}
