<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Formatter;
use WeChatPay\Util\MediaUtil;

/** 微信支付商户媒体上传接口封装。 */
class Uploads
{
    /**
     * 上传商户图片并获取微信支付返回的媒体文件标识。
     * 请求使用 APIv3 multipart/form-data 格式，文件元数据及文件流由 MediaUtil 生成。
     *
     * @param string $imgpath 图片在本地服务器上的物理路径。
     * @return string 微信支付媒体上传接口返回的响应内容（通常包含 media_id）。
     * @throws WxPayv3Exception 文件不存在、不可读或上传请求失败时抛出异常。
     */
    public function upload($imgpath)
    {
        // 上传前校验文件状态，避免对无效路径生成元数据或发起请求。
        if (!is_file($imgpath) || !is_readable($imgpath)) {
            throw new WxPayv3Exception('上传文件不存在或不可读：' . $imgpath);
        }
        $url = 'https://api.mch.weixin.qq.com/v3/merchant/media/upload';
        // MediaUtil 负责读取图片信息并生成 APIv3 所需的元数据和 multipart 文件流。
        $media = new MediaUtil($imgpath);
        $meta = $media->getMeta();
        $timestamp = (string) time();
        $nonce = Formatter::nonce();
        // 图片上传签名内容使用媒体元数据，而不是文件二进制内容。
        $authorization = Signs::sign($url, 'POST', $timestamp, $nonce, $meta);
        $headers = [
            'User-Agent: wechat-ecommerce-sdk',
            'Accept: application/json',
            'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization,
            // Content-Type 中包含 multipart/form-data 的 boundary 信息。
            'Content-Type: ' . $media->getContentType(),
        ];
        // 发送 MediaUtil 生成的 multipart 请求流，并返回微信支付响应。
        return Signs::_requestPost($url, $media->getStream(), $headers);
    }
}
