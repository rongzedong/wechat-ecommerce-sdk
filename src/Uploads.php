<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Formatter;
use WeChatPay\Util\MediaUtil;

/** Upload merchant media with the APIv3 multipart format. */
class Uploads
{
    /**
     * @param string $imgpath Local file path.
     * @return string WeChat Pay media upload response.
     */
    public function upload($imgpath)
    {
        if (!is_file($imgpath) || !is_readable($imgpath)) {
            throw new WxPayv3Exception('上传文件不存在或不可读：' . $imgpath);
        }
        $url = 'https://api.mch.weixin.qq.com/v3/merchant/media/upload';
        $media = new MediaUtil($imgpath);
        $meta = $media->getMeta();
        $timestamp = (string) time();
        $nonce = Formatter::nonce();
        $authorization = Signs::sign($url, 'POST', $timestamp, $nonce, $meta);
        $headers = [
            'User-Agent: wechat-ecommerce-sdk',
            'Accept: application/json',
            'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization,
            'Content-Type: ' . $media->getContentType(),
        ];
        return Signs::_requestPost($url, $media->getStream(), $headers);
    }
}
