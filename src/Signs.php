<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/** APIv3 request signing, transport, RSA and AES-GCM helpers. */
class Signs
{
    public static function _Getresponse($url, $body = '')
    {
        $timestamp = time();
        $nonce = Formatter::nonce();
        $authorization = self::sign($url, 'GET', $timestamp, $nonce);
        $headers = [
            'User-Agent: wechat-ecommerce-sdk',
            'Accept: application/json',
            'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization,
        ];

        // The API request is authenticated by the merchant certificate serial in
        // Authorization. Do not send the platform certificate serial here.
        return self::_requestGet($url, $body, $headers);
    }

    public static function _Postresponse($url, $body)
    {
        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new WxPayv3Exception('请求参数JSON编码失败');
            }
        }
        $body = (string) $body;
        $timestamp = time();
        $nonce = Formatter::nonce();
        $authorization = self::sign($url, 'POST', $timestamp, $nonce, $body);
        $headers = [
            'User-Agent: wechat-ecommerce-sdk',
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization,
        ];

        return self::_requestPost($url, $body, $headers);
    }

    /** Keep the legacy public method used by media upload callers. */
    public static function _requestGet($url, $meta = '', $header = [], $referer = '', $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($referer !== '') {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
        return self::executeRequest($ch);
    }

    /** Keep the legacy public method used by media upload callers. */
    public static function _requestPost($url, $data, $header = [], $referer = '', $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
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
            throw new WxPayv3Exception('微信支付请求失败：' . $error);
        }
        curl_close($ch);
        return $response;
    }

    /** Return the Authorization header parameters (without the scheme prefix). */
    public static function sign($url, $http_method, $timestamp, $nonce, $body = '')
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['path'])) {
            throw new WxPayv3Exception('请求URL格式错误');
        }
        $uri = $parts['path'] . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '');
        $method = strtoupper($http_method);
        $message = Formatter::request($method, $uri, (string) $timestamp, $nonce, $method === 'GET' ? '' : (string) $body);
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

    public static function _PayJson($appid, $prepay_id)
    {
        $timestamp = (string) time();
        $nonce = Formatter::nonce();
        $package = 'prepay_id=' . $prepay_id;
        return json_encode([
            'appId' => $appid,
            'timeStamp' => $timestamp,
            'nonceStr' => $nonce,
            'package' => $package,
            'signType' => 'RSA',
            'paySign' => self::paySign($appid, $timestamp, $nonce, $package),
        ]);
    }

    public static function _PayAppJson($appid, $prepay_id)
    {
        $timestamp = (string) time();
        $nonce = Formatter::nonce();
        return json_encode([
            'appid' => $appid,
            'partnerid' => Config::$config['MCHID'],
            'prepayid' => $prepay_id,
            'package' => 'Sign=WXPay',
            'noncestr' => $nonce,
            'timestamp' => $timestamp,
            'sign' => self::paySign($appid, $timestamp, $nonce, $prepay_id),
        ]);
    }

    private static function paySign($appid, $timestamp, $nonce, $body)
    {
        return Rsa::sign(Formatter::joinedByLineFeed($appid, $timestamp, $nonce, $body), self::getPrivateKey(Config::$config['SSLKEY_PATH']));
    }

    private static function getPrivateKey($filepath)
    {
        $keyPath = strpos($filepath, '://') === false ? 'file://' . $filepath : $filepath;
        return Rsa::from($keyPath, Rsa::KEY_TYPE_PRIVATE);
    }

    public static function getCertificate($filepath)
    {
        $content = file_get_contents($filepath);
        $certificate = $content === false ? false : openssl_x509_read($content);
        if ($certificate === false) {
            throw new WxPayv3Exception('读取证书失败');
        }
        return $certificate;
    }

    /** Encrypt sensitive APIv3 data with the active platform certificate. */
    public static function getEncrypt($str)
    {
        Cert::certificates();
        $path = Config::$config['PLATFORM_CERT_PATH'];
        return Rsa::encrypt($str, Rsa::from('file://' . $path, Rsa::KEY_TYPE_PUBLIC));
    }

    public static function getPrivateEncrypt($str)
    {
        return Rsa::decrypt($str, self::getPrivateKey(Config::$config['SSLKEY_PATH']));
    }

    public static function decryptToString($associatedData, $nonce, $ciphertext)
    {
        $aesKey = Config::$config['APPKERV3'] ?? '';
        if (strlen($aesKey) !== 32) {
            throw new WxPayv3Exception('无效的ApiV3Key，长度应为32个字节');
        }
        try {
            return AesGcm::decrypt($ciphertext, $aesKey, $nonce, $associatedData);
        } catch (\Throwable $exception) {
            throw new WxPayv3Exception('APIv3 AES-GCM解密失败：' . $exception->getMessage());
        }
    }
}
