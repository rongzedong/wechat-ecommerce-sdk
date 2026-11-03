<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

//加签，验签，加密，解密，get提交，post提交
class Signs
{
    /**
     * 微信支付 APIv3 请求签名使用商户 API 私钥，Wechatpay-Serial 请求头使用商户 API 证书序列号。
     */
    public static function _Getresponse($url, $body = '')
    {
        $method = 'GET';
        $timestamp = time();
        $nonce = Formatter::nonce();
        $sign = self::sign($url, $method, $timestamp, $nonce, $body);
        $headers = [
            'User-Agent: wechat-ecommerce-sdk',
            'Accept: application/json',
            'Authorization:WECHATPAY2-SHA256-RSA2048 ' . $sign,
            'Wechatpay-Serial:' . Cert::certificates(),
        ];

        return self::_requestGet($url, $body, $headers);
    }

    public static function _Postresponse($url, $body)
    {
        $method = 'POST';
        $timestamp = time();
        if (is_array($body)) {
            $body = json_encode($body);
        }
        $nonce = Formatter::nonce();
        $sign = self::sign($url, $method, $timestamp, $nonce, $body);
        $headers = [
            'User-Agent: wechat-ecommerce-sdk',
            'Accept: application/json',
            'Content-Type:application/json',
            'Authorization:WECHATPAY2-SHA256-RSA2048 ' . $sign,
            'Wechatpay-Serial:' . Cert::certificates(),
        ];

        return self::_requestPost($url, $body, $headers);
    }

    /**
     * @param string $url Request URL.
     * @param mixed $meta Retained for backwards compatibility; GET requests have no signed body.
     * @param array $header Request headers.
     * @param string $referer
     * @param int $timeout
     * @return string|bool
     */
    private static function _requestGet($url, $meta, $header = [], $referer = '', $timeout = 30)
    {
        return self::request($url, $header, $timeout, $referer);
    }

    /**
     * @param string $url Request URL.
     * @param string $data Request body.
     * @param array $header Request headers.
     * @param string $referer
     * @param int $timeout
     * @return string|bool
     */
    public static function _requestPost($url, $data, $header = [], $referer = '', $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($referer !== '') {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }

        return self::executeRequest($ch);
    }

    private static function request($url, $header, $timeout, $referer)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($referer !== '') {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }

        return self::executeRequest($ch);
    }

    private static function executeRequest($ch)
    {
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new WxPayv3Exception($error);
        }
        curl_close($ch);

        return $response;
    }

    /** Build the APIv3 Authorization parameters using the official SDK crypto and formatter. */
    public static function sign($url, $http_method, $timestamp, $nonce, $body = '')
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['path'])) {
            throw new WxPayv3Exception('请求URL格式错误');
        }
        $uri = $parts['path'] . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '');
        $message = Formatter::request($http_method, $uri, (string) $timestamp, $nonce, $http_method === 'GET' ? '' : $body);
        $privateKey = self::getPrivateKey(Config::$config['SSLKEY_PATH']);
        $authorization = Formatter::authorization(
            Config::$config['MCHID'],
            $nonce,
            Rsa::sign($message, $privateKey),
            (string) $timestamp,
            Config::$config['SERIAL_NO']
        );

        return substr($authorization, strlen('WECHATPAY2-SHA256-RSA2048 '));
    }

    /** WeChat Pay client-payment payload for JSAPI / Mini Program. */
    public static function _PayJson($appid, $prepay_id)
    {
        $timestamp = time();
        $nonceStr = Formatter::nonce();
        $package = 'prepay_id=' . $prepay_id;
        return json_encode([
            'appId' => $appid,
            'timeStamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'package' => $package,
            'signType' => 'RSA',
            'paySign' => self::paySign($appid, $timestamp, $nonceStr, $package),
        ]);
    }

    /** WeChat Pay client-payment payload for Android / iOS. */
    public static function _PayAppJson($appid, $prepay_id)
    {
        $timestamp = time();
        $nonceStr = Formatter::nonce();
        return json_encode([
            'appid' => $appid,
            'partnerid' => Config::$config['MCHID'],
            'prepayid' => $prepay_id,
            'package' => 'Sign=WXPay',
            'noncestr' => $nonceStr,
            'timestamp' => $timestamp,
            'sign' => self::paySign($appid, $timestamp, $nonceStr, $prepay_id),
        ]);
    }

    private static function paySign($appid, $timestamp, $nonceStr, $body)
    {
        $message = Formatter::joinedByLineFeed($appid, $timestamp, $nonceStr, $body);
        return Rsa::sign($message, self::getPrivateKey(Config::$config['SSLKEY_PATH']));
    }

    private static function getPrivateKey($filepath)
    {
        $keyPath = strpos($filepath, '://') === false ? 'file://' . $filepath : $filepath;
        return Rsa::from($keyPath, Rsa::KEY_TYPE_PRIVATE);
    }

    /** Load an X.509 certificate for existing callers. */
    public static function getCertificate($filepath)
    {
        $certificate = openssl_x509_read(file_get_contents($filepath));
        if ($certificate === false) {
            throw new WxPayv3Exception('读取证书失败');
        }
        return $certificate;
    }

    /** Encrypt sensitive APIv3 data with the platform certificate/public key. */
    public static function getEncrypt($str)
    {
        $publicKey = Rsa::from(
            'file://' . Config::$config['PLATFORM_CERT_PATH'],
            Rsa::KEY_TYPE_PUBLIC
        );
        return Rsa::encrypt($str, $publicKey);
    }

    /** Decrypt sensitive data returned by WeChat Pay with the merchant private key. */
    public static function getPrivateEncrypt($str)
    {
        return Rsa::decrypt($str, self::getPrivateKey(Config::$config['SSLKEY_PATH']));
    }

    /** Decrypt APIv3 certificates and callback resources. */
    public static function decryptToString($associatedData, $nonceStr, $ciphertext)
    {
        $aesKey = Config::$config['APPKERV3'];
        if (strlen($aesKey) !== 32) {
            throw new WxPayv3Exception('无效的ApiV3Key，长度应为32个字节');
        }

        try {
            return AesGcm::decrypt($ciphertext, $aesKey, $nonceStr, $associatedData);
        } catch (\Throwable $exception) {
            throw new WxPayv3Exception('解密失败：' . $exception->getMessage());
        }
    }
}
