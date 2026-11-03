<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/**
 * 验证并解密微信支付 APIv3 回调通知。
 * 处理支付成功通知、退款成功通知等加密回调消息，并在校验签名后解密资源内容。
 */
class Notify
{
    /**
     * 处理支付成功回调通知。
     * @param string $jsonData 原始回调通知 JSON 字符串
     * @param array $headers HTTP 请求头信息
     * @return string 解密后的通知资源内容
     */
    public function notifyPay($jsonData, $headers = [])
    {
        return $this->notify($jsonData, $headers, 'TRANSACTION.SUCCESS');
    }

    /**
     * 处理退款成功回调通知。
     * @param string $jsonData 原始回调通知 JSON 字符串
     * @param array $headers HTTP 请求头信息
     * @return string 解密后的通知资源内容
     */
    public function notifyRefund($jsonData, $headers = [])
    {
        return $this->notify($jsonData, $headers, 'REFUND.SUCCESS');
    }

    /**
     * 通用回调处理入口，校验通知类型、签名及加密字段，并解密资源内容。
     * @param string $jsonData 原始回调通知 JSON 字符串
     * @param array $headers HTTP 请求头信息
     * @param string $eventType 期望事件类型（如 TRANSACTION.SUCCESS / REFUND.SUCCESS）
     * @return string 解密后的资源内容
     */
    private function notify($jsonData, $headers, $eventType)
    {
        if (!is_string($jsonData) || $jsonData === '') {
            throw new WxPayv3Exception('数据为空');
        }
        $data = json_decode($jsonData, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new WxPayv3Exception('通知JSON格式错误');
        }
        if (($data['event_type'] ?? null) !== $eventType) {
            throw new WxPayv3Exception('通知的类型错误');
        }
        if (($data['resource_type'] ?? null) !== 'encrypt-resource' || empty($data['resource']) || !is_array($data['resource'])) {
            throw new WxPayv3Exception('通知的数据类型错误');
        }
        if (!$this->Verify($jsonData, $headers)) {
            throw new WxPayv3Exception('验证签名失败');
        }
        $resource = $data['resource'];
        foreach (['associated_data', 'nonce', 'ciphertext'] as $field) {
            if (!isset($resource[$field])) {
                throw new WxPayv3Exception('通知资源缺少字段：' . $field);
            }
        }
        return Signs::decryptToString($resource['associated_data'], $resource['nonce'], $resource['ciphertext']);
    }

    /**
     * 验证微信支付回调签名，确保通知未被篡改、时间戳有效且使用正确平台证书。
     * 同时兼容 PHP $_SERVER 风格头部名和标准 HTTP 头部名。
     * @param string $body 原始回调请求体
     * @param array $headers HTTP 请求头信息
     * @return bool 签名校验是否通过
     */
    public function Verify($body, $headers = [])
    {
        $headers = $this->normalizeHeaders($headers);
        $timestamp = $headers['wechatpay-timestamp'] ?? '';
        $nonce = $headers['wechatpay-nonce'] ?? '';
        $signature = $headers['wechatpay-signature'] ?? '';
        $serial = $headers['wechatpay-serial'] ?? '';
        if ($timestamp === '' || $nonce === '' || $signature === '' || $serial === '') {
            throw new WxPayv3Exception('回调请求缺少微信支付验签头');
        }
        if (!ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > 300) {
            throw new WxPayv3Exception('回调时间戳无效或已过期');
        }
        $certificate = Cert::certificateForSerial($serial);
        $message = Formatter::response((string) $timestamp, $nonce, (string) $body);
        try {
            return Rsa::verify($message, $signature, $certificate);
        } catch (\Throwable $exception) {
            throw new WxPayv3Exception('回调签名验证异常：' . $exception->getMessage());
        }
    }

    /**
     * 标准化回调头信息，兼容 PHP 的 $_SERVER 头名和标准 HTTP 头名。
     * @param array $headers 原始头信息
     * @return array 标准化后的头信息
     */
    private function normalizeHeaders($headers)
    {
        $normalized = [];
        foreach ((array) $headers as $value) {
            $name = strtolower(str_replace('_', '-', preg_replace('/^HTTP_/i', '', (string) $value)));
            $normalized[$name] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        return $normalized;
    }

    /**
     * 返回当前请求的 HTTP 头信息，保留标准命名风格。
     * @return array 头信息数组
     */
    public function em_getallheaders()
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return $headers;
            }
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
