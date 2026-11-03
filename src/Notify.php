<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/** 验证并解密微信支付 APIv3 回调通知。 */
class Notify
{
    /**
     * 处理支付成功通知。
     * @param string $jsonData 通知原始 JSON 字符串
     * @param array $headers HTTP 头信息
     * @return string 解密后的资源对象内容
     */
    public function notifyPay($jsonData, $headers = [])
    {
        return $this->notify($jsonData, $headers, 'TRANSACTION.SUCCESS');
    }

    /**
     * 处理退款成功通知。
     * @param string $jsonData 通知原始 JSON 字符串
     * @param array $headers HTTP 头信息
     * @return string 解密后的资源对象内容
     */
    public function notifyRefund($jsonData, $headers = [])
    {
        return $this->notify($jsonData, $headers, 'REFUND.SUCCESS');
    }

    /**
     * 统一处理支付/退款通知，校验签名并��密资源字段。
     * @param string $jsonData 通知原始 JSON 字符串
     * @param array $headers HTTP 头信息
     * @param string $eventType 期望的通知事件类型
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
     * 验证回调签名，确保签名、时间戳和证书序列号均匹配。
     * 允许同时处理 PHP $_SERVER 头和标准 HTTP 头名称。
     * @param string $body 原始回调请求体
     * @param array $headers HTTP 头信息
     * @return bool 是否校验通过
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
     * 标准化头信息名称，兼容 PHP $_SERVER 和标准头名称。
     * @param array $headers 原始头信息
     * @return array 标准化后的头信息
     */
    private function normalizeHeaders($headers)
    {
        $normalized = [];
        foreach ((array) $headers as $name => $value) {
            $name = strtolower(str_replace('_', '-', preg_replace('/^HTTP_/i', '', (string) $name)));
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
