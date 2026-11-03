<?php

namespace jiujiude\WechatEcommerce;

use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/** Verify and decrypt WeChat Pay APIv3 webhook notifications. */
class Notify
{
    public function notifyPay($jsonData, $headers = [])
    {
        return $this->notify($jsonData, $headers, 'TRANSACTION.SUCCESS');
    }

    public function notifyRefund($jsonData, $headers = [])
    {
        return $this->notify($jsonData, $headers, 'REFUND.SUCCESS');
    }

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
     * Verify the exact raw notification body and reject stale/replayed timestamps.
     * Both PHP $_SERVER names and canonical HTTP header names are accepted.
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

    private function normalizeHeaders($headers)
    {
        $normalized = [];
        foreach ((array) $headers as $name => $value) {
            $name = strtolower(str_replace('_', '-', preg_replace('/^HTTP_/i', '', (string) $name)));
            $normalized[$name] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        return $normalized;
    }

    /** Return request headers in canonical names. */
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
