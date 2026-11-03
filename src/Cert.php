<?php

namespace jiujiude\WechatEcommerce;

/** Download, decrypt and cache APIv3 platform certificates. */
class Cert
{
    /**
     * Refresh the local platform certificate cache when necessary.
     * Returns the active certificate serial for backwards compatibility.
     */
    public static function certificates($serialNo = null)
    {
        $cachePath = Config::$config['PLATFORM_SERIAL_NO_PATH'] ?? '';
        $certificatePath = Config::$config['PLATFORM_CERT_PATH'] ?? '';
        if ($cachePath === '' || $certificatePath === '') {
            throw new WxPayv3Exception('请配置平台证书缓存路径');
        }

        $cache = self::readCache($cachePath);
        if (!self::cacheIsUsable($cache)) {
            $cache = self::download($cachePath, $certificatePath);
        }

        if ($serialNo !== null) {
            if (!isset($cache['certificates'][$serialNo])) {
                // A notification can use a certificate rotated since the last cache refresh.
                $cache = self::download($cachePath, $certificatePath);
            }
            if (!isset($cache['certificates'][$serialNo])) {
                throw new WxPayv3Exception('找不到序列号为 ' . $serialNo . ' 的微信支付平台证书');
            }
            return $serialNo;
        }

        if (empty($cache['active_serial'])) {
            throw new WxPayv3Exception('微信支付平台证书列表为空');
        }
        return $cache['active_serial'];
    }

    /** Load the platform certificate matching a webhook Wechatpay-Serial. */
    public static function certificateForSerial($serialNo)
    {
        self::certificates($serialNo);
        $cache = self::readCache(Config::$config['PLATFORM_SERIAL_NO_PATH']);
        $path = $cache['certificates'][$serialNo]['path'];
        return Signs::getCertificate($path);
    }

    private static function readCache($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $cache = json_decode((string) file_get_contents($path), true);
        return is_array($cache) ? $cache : [];
    }

    private static function cacheIsUsable($cache)
    {
        if (empty($cache['certificates']) || empty($cache['expires_at']) || time() >= $cache['expires_at']) {
            return false;
        }
        foreach ($cache['certificates'] as $certificate) {
            if (empty($certificate['path']) || !is_file($certificate['path'])) {
                return false;
            }
        }
        return true;
    }

    private static function download($cachePath, $certificatePath)
    {
        $response = json_decode(Signs::_Getresponse('https://api.mch.weixin.qq.com/v3/certificates'), true);
        if (!is_array($response) || empty($response['data']) || !is_array($response['data'])) {
            $message = is_array($response) ? ($response['message'] ?? '平台证书响应格式错误') : '平台证书响应格式错误';
            throw new WxPayv3Exception($message);
        }

        $certificates = [];
        $earliestExpiry = time() + 86400;
        foreach ($response['data'] as $item) {
            if (empty($item['serial_no']) || empty($item['encrypt_certificate']['nonce']) || !isset($item['encrypt_certificate']['associated_data'], $item['encrypt_certificate']['ciphertext'])) {
                continue;
            }
            $pem = Signs::decryptToString(
                $item['encrypt_certificate']['associated_data'],
                $item['encrypt_certificate']['nonce'],
                $item['encrypt_certificate']['ciphertext']
            );
            $serial = preg_replace('/[^A-Za-z0-9._-]/', '_', $item['serial_no']);
            $path = $certificatePath . '.' . $serial . '.pem';
            self::writeFile($path, $pem);
            // Keep the configured legacy location populated with the active certificate.
            if (empty($certificates)) {
                self::writeFile($certificatePath, $pem);
            }
            $expiresAt = isset($item['expire_time']) ? strtotime($item['expire_time']) : false;
            if ($expiresAt !== false) {
                $earliestExpiry = min($earliestExpiry, $expiresAt - 300);
            }
            $certificates[$item['serial_no']] = ['path' => $path, 'expire_time' => $item['expire_time'] ?? null];
        }

        if (empty($certificates)) {
            throw new WxPayv3Exception('微信支付平台证书解密失败或列表为空');
        }
        $activeSerial = array_key_first($certificates);
        $cache = [
            'active_serial' => $activeSerial,
            'expires_at' => max(time() + 60, min(time() + 86400, $earliestExpiry)),
            'certificates' => $certificates,
        ];
        self::writeFile($cachePath, json_encode($cache, JSON_UNESCAPED_SLASHES));
        return $cache;
    }

    private static function writeFile($path, $contents)
    {
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new WxPayv3Exception('证书缓存目录不可写：' . $directory);
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new WxPayv3Exception('写入证书缓存失败：' . $path);
        }
    }
}
