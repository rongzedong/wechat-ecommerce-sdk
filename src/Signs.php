<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/**
 * 微信支付 APIv3 请求签名、HTTP 请求以及 RSA/AES-GCM 加解密工具。
 * 本类使用 wechatpay/wechatpay 相关组件完成请求格式化及密码学操作。
 */
class Signs
{
    /**
     * 发送已签名的 GET 请求。
     * GET 请求的请求体不参与签名；签名所用商户证书序列号由 Authorization 参数携带。
     *
     * @param string $url  请求的完整 URL。
     * @param string $body 可选请求体；GET 签名时不会使用该内容。
     * @return string 微信支付接口返回的响应内容。
     */
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

        // 请求签名中的商户证书序列号已包含在 Authorization 中，此处不发送平台证书序列号。
        return self::_requestGet($url, $body, $headers);
    }

    /**
     * 发送已签名的 POST 请求。
     * 数组请求体会先编码为 JSON；编码失败时抛出 SDK 异常。
     *
     * @param string|array $url  请求 URL（参数保留为兼容现有调用方式）。
     * @param string|array $body 请求正文；数组会编码为 JSON。
     * @return string 微信支付接口返回的响应内容。
     */
    public static function _Postresponse($url, $body)
    {
        if (is_array($body)) {
            // 保留中文和斜杠，生成与实际发送内容一致的签名报文。
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new WxPayv3Exception('请求参数JSON编码失败');
            }
        }
        $body = (string) $body;
        $timestamp = time();
        $nonce = Formatter::nonce();
        // 签名必须基于即将发送的原始请求正文。
        $authorization = self::sign($url, 'POST', $timestamp, $nonce, $body);
        $headers = [
            'User-Agent: wechat-ecommerce-sdk',
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization,
        ];

        return self::_requestPost($url, $body, $headers);
    }

    /**
     * 发送 GET 请求的底层 cURL 方法。
     * 保留为 public，以兼容上传等直接使用该方法的调用方。
     *
     * @param string $url     请求 URL。
     * @param string $meta    GET 请求附带的内容（当前方法不设置请求正文，保留兼容参数）。
     * @param array  $header  HTTP 请求头列表。
     * @param string $referer 可选 Referer 请求头。
     * @param int    $timeout 超时时间，单位为秒。
     * @return string 请求响应正文。
     */
    public static function _requestGet($url, $meta = '', $header = [], $referer = '', $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        // 校验服务端 TLS 证书及主机名，避免关闭 HTTPS 证书验证。
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($referer !== '') {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
        return self::executeRequest($ch);
    }

    /**
     * 发送 POST 请求的底层 cURL 方法。
     * 保留为 public，以兼容图片上传等直接使用该方法的调用方。
     *
     * @param string $url     请求 URL。
     * @param string $data    请求正文。
     * @param array  $header  HTTP 请求头列表。
     * @param string $referer 可选 Referer 请求头。
     * @param int    $timeout 超时时间，单位为秒。
     * @return string 请求响应正文。
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
        // 校验服务端 TLS 证书及主机名，确保 HTTPS 连接安全。
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($referer !== '') {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
        return self::executeRequest($ch);
    }

    /**
     * 执行已配置好的 cURL 请求，并统一处理传输错误及资源释放。
     *
     * @param resource|\\CurlHandle $ch 已初始化并配置完成的 cURL 句柄。
     * @return string 请求响应正文。
     */
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

    /**
     * 生成微信支付 APIv3 请求的 Authorization 参数。
     * 返回值不包含 Authorization 头部的认证方案前缀。
     *
     * @param string $url         请求完整 URL，路径和查询字符串会作为签名的一部分。
     * @param string $http_method HTTP 方法，例如 GET、POST。
     * @param int    $timestamp   请求时间戳。
     * @param string $nonce       本次请求使用的随机字符串。
     * @param string $body        请求正文；GET 方法签名时按空字符串处理。
     * @return string Authorization 头部参数内容。
     */
    public static function sign($url, $http_method, $timestamp, $nonce, $body = '')
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['path'])) {
            throw new WxPayv3Exception('请求URL格式错误');
        }
        // 按 APIv3 规范，签名 URI 由路径及（如有）查询字符串组成。
        $uri = $parts['path'] . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '');
        $method = strtoupper($http_method);
        // 按微信支付请求签名格式拼接待签名内容；GET 请求正文为空。
        $message = Formatter::request($method, $uri, (string) $timestamp, $nonce, $method === 'GET' ? '' : (string) $body);
        $privateKey = self::getPrivateKey(Config::$config['SSLKEY_PATH']);
        $authorization = Formatter::authorization(
            Config::$config['MCHID'], // 发起请求的商户号。
            $nonce,
            Rsa::sign($message, $privateKey), // 使用商户私钥对请求内容进行 RSA 签名。
            (string) $timestamp,
            Config::$config['SERIAL_NO'] // 商户 API 证书序列号。
        );
        // 调用方会自行添加 Authorization: WECHATPAY2-SHA256-RSA2048 前缀。
        return substr($authorization, strlen('WECHATPAY2-SHA256-RSA2048 '));
    }

    /**
     * 生成公众号 JSAPI 或小程序支付所需的客户端参数。
     *
     * @param string $appid     公众号或小程序的 AppID。
     * @param string $prepay_id 合单下单接口返回的预支付交易会话标识。
     * @return string 可供客户端使用的支付参数 JSON 字符串。
     */
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
            // 支付签名覆盖 AppID、时间戳、随机串及 package 内容。
            'paySign' => self::paySign($appid, $timestamp, $nonce, $package),
        ]);
    }

    /**
     * 生成微信 App 支付所需的客户端参数。
     *
     * @param string $appid     App 的 AppID。
     * @param string $prepay_id 合单下单接口返回的预支付交易会话标识。
     * @return string 可供客户端使用的 App 支付参数 JSON 字符串。
     */
    public static function _PayAppJson($appid, $prepay_id)
    {
        $timestamp = (string) time();
        $nonce = Formatter::nonce();
        return json_encode([
            'appid' => $appid,
            // 微信支付商户号。
            'partnerid' => Config::$config['MCHID'],
            'prepayid' => $prepay_id,
            'package' => 'Sign=WXPay',
            'noncestr' => $nonce,
            'timestamp' => $timestamp,
            // App 支付签名以 prepay_id 作为签名主体内容。
            'sign' => self::paySign($appid, $timestamp, $nonce, $prepay_id),
        ]);
    }

    /**
     * 计算客户端调起支付使用的 RSA 签名。
     *
     * @param string $appid     应用 AppID。
     * @param string $timestamp 时间戳。
     * @param string $nonce     随机字符串。
     * @param string $body      签名主体，JSAPI 通常为 package，App 支付为 prepay_id。
     * @return string Base64 编码的 RSA 签名。
     */
    private static function paySign($appid, $timestamp, $nonce, $body)
    {
        return Rsa::sign(Formatter::joinedByLineFeed($appid, $timestamp, $nonce, $body), self::getPrivateKey(Config::$config['SSLKEY_PATH']));
    }

    /**
     * 从配置路径加载商户 API 私钥。
     * 已包含 stream scheme 的路径会直接使用，否则按本地文件路径补上 file://。
     *
     * @param string $filepath 商户私钥文件路径。
     * @return mixed 加载后的私钥对象。
     */
    private static function getPrivateKey($filepath)
    {
        $keyPath = strpos($filepath, '://') === false ? 'file://' . $filepath : $filepath;
        return Rsa::from($keyPath, Rsa::KEY_TYPE_PRIVATE);
    }

    /**
     * 读取并解析 X.509 证书。
     *
     * @param string $filepath 证书文件路径。
     * @return resource|\\OpenSSLCertificate 解析后的证书对象。
     * @throws WxPayv3Exception 证书无法读取或解析时抛出。
     */
    public static function getCertificate($filepath)
    {
        $content = file_get_contents($filepath);
        $certificate = $content === false ? false : openssl_x509_read($content);
        if ($certificate === false) {
            throw new WxPayv3Exception('读取证书失败');
        }
        return $certificate;
    }

    /**
     * 使用当前平台证书的公钥加密 APIv3 敏感信息。
     *
     * @param string $str 待加密的敏感信息。
     * @return string 加密后由密码学组件返回的密文。
     */
    public static function getEncrypt($str)
    {
        // 确保平台证书已准备好，再读取配置中的平台证书公钥。
        Cert::certificates();
        $path = Config::$config['PLATFORM_CERT_PATH'];
        return Rsa::encrypt($str, Rsa::from('file://' . $path, Rsa::KEY_TYPE_PUBLIC));
    }

    /**
     * 使用商户 API 私钥解密敏感信息。
     *
     * @param string $str 待解密的密文。
     * @return string 解密后的明文。
     */
    public static function getPrivateEncrypt($str)
    {
        return Rsa::decrypt($str, self::getPrivateKey(Config::$config['SSLKEY_PATH']));
    }

    /**
     * 使用 APIv3 密钥解密 AES-256-GCM 加密的数据。
     *
     * @param string $associatedData 与密文关联的附加认证数据。
     * @param string $nonce          加密时使用的随机字符串。
     * @param string $ciphertext     微信支付返回的 Base64 编码密文。
     * @return string 解密后的明文。
     * @throws WxPayv3Exception APIv3 密钥长度错误或解密失败时抛出。
     */
    public static function decryptToString($associatedData, $nonce, $ciphertext)
    {
        // APIv3 密钥必须恰好为 32 字节，用于 AES-256-GCM。
        $aesKey = Config::$config['APPKERV3'] ?? '';
        if (strlen($aesKey) !== 32) {
            throw new WxPayv3Exception('无效的ApiV3Key，长度应为32个字节');
        }
        try {
            return AesGcm::decrypt($ciphertext, $aesKey, $nonce, $associatedData);
        } catch (\Throwable $exception) {
            // 将底层解密错误包装为 SDK 异常，保留原始错误信息便于排查。
            throw new WxPayv3Exception('APIv3 AES-GCM解密失败：' . $exception->getMessage());
        }
    }
}
