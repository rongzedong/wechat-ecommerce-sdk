<?php
/**
 * Offline smoke tests for the SDK's configuration and input validation.
 *
 * Run from any working directory with: php tests/index.php
 * These tests deliberately avoid live WeChat Pay requests and real credentials.
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Autoloader not found. Run composer install before executing tests.\n");
    exit(1);
}
require_once $autoload;

use jiujiude\WechatEcommerce\Account;
use jiujiude\WechatEcommerce\Apply;
use jiujiude\WechatEcommerce\Bill;
use jiujiude\WechatEcommerce\Cert;
use jiujiude\WechatEcommerce\Config;
use jiujiude\WechatEcommerce\Notify;
use jiujiude\WechatEcommerce\Signs;
use jiujiude\WechatEcommerce\Uploads;
use jiujiude\WechatEcommerce\WxPayv3Exception;

$passed = 0;
$failed = 0;

$test = function ($name, callable $callback) use (&$passed, &$failed) {
    try {
        $callback();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (\Throwable $exception) {
        $failed++;
        fwrite(STDERR, "[FAIL] {$name}: {$exception->getMessage()}\n");
    }
};

$assertSame = function ($expected, $actual, $message = 'Values are not identical') {
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertThrows = function (callable $callback, $expectedMessage = null) {
    try {
        $callback();
    } catch (WxPayv3Exception $exception) {
        if ($expectedMessage !== null && $exception->getMessage() !== $expectedMessage) {
            throw new RuntimeException('Unexpected exception message: ' . $exception->getMessage());
        }
        return $exception;
    }
    throw new RuntimeException('Expected WxPayv3Exception was not thrown');
};

$originalConfig = Config::getConfig();

try {
    $test('Config stores and exposes values', function () use ($assertSame) {
        $config = ['MCHID' => 'test-merchant', 'APPKERV3' => str_repeat('a', 32)];
        Config::setConfig($config);
        $assertSame($config, Config::getConfig());
        $assertSame('test-merchant', (new Config())->MCHID);
        $assertSame('', (new Config())->UNKNOWN_OPTION);
    });

    $test('Config rejects a non-array value', function () use ($assertThrows) {
        $assertThrows(function () {
            Config::setConfig('invalid');
        }, '配置必须为数组');
    });

    $test('SDK exception exposes its message', function () use ($assertSame) {
        $exception = new WxPayv3Exception('test error');
        $assertSame('test error', $exception->errorMessage());
    });

    $test('Apply validates sub-merchant IDs before making requests', function () use ($assertThrows) {
        $apply = new Apply();
        $assertThrows(function () use ($apply) {
            $apply->modifySettlement(['sub_mchid' => '123']);
        }, '特约商户号:长度最小8个字节');
        $assertThrows(function () use ($apply) {
            $apply->getSettlement('123');
        }, '特约商户号:长度最小8个字节');
        $assertThrows(function () use ($apply) {
            $apply->modifySettlementTY([]);
        }, 'sub_mchid不能为空');
    });

    $test('Bill rejects unsupported account, balance, and merchant inputs', function () use ($assertThrows) {
        $bill = new Bill();
        $assertThrows(function () use ($bill) {
            $bill->getComBalance(1, 'INVALID');
        }, '查询的账户类型不存在');
        $assertThrows(function () use ($bill) {
            $bill->getComBalance(3, 'BASIC');
        }, '请求路径错误');
        $assertThrows(function () use ($bill) {
            $bill->getShopBalance('');
        }, '查询的二级商户不能为空');
        $assertThrows(function () use ($bill) {
            $bill->getShopBalance('sub-merchant', 3);
        }, '请求路径错误');
    });

    $test('Account requires an identifier when querying a return order', function () use ($assertThrows) {
        $account = new Account();
        $assertThrows(function () use ($account) {
            $account->findreturnAccount('sub-merchant');
        }, 'order_id 和 out_order_no 必须提供其中一个');
    });

    $test('Notify rejects empty, malformed, and mismatched notifications', function () use ($assertThrows) {
        $notify = new Notify();
        $assertThrows(function () use ($notify) {
            $notify->notifyPay('');
        }, '数据为空');
        $assertThrows(function () use ($notify) {
            $notify->notifyPay('{');
        }, '通知JSON格式错误');
        $assertThrows(function () use ($notify) {
            $notify->notifyPay(json_encode(['event_type' => 'REFUND.SUCCESS']));
        }, '通知的类型错误');
        $assertThrows(function () use ($notify) {
            $notify->notifyPay(json_encode([
                'event_type' => 'TRANSACTION.SUCCESS',
                'resource_type' => 'plain',
                'resource' => [],
            ]));
        }, '通知的数据类型错误');
        $assertThrows(function () use ($notify) {
            $notify->Verify('{}', []);
        }, '回调请求缺少微信支付验签头');
    });

    $test('APIv3 decryption rejects a key with the wrong length', function () use ($assertThrows) {
        Config::setConfig(['APPKERV3' => 'too-short']);
        $assertThrows(function () {
            Signs::decryptToString('', 'nonce', 'ciphertext');
        }, '无效的ApiV3Key，长度应为32个字节');
    });

    $test('Certificate lookup requires configured cache paths', function () use ($assertThrows) {
        Config::setConfig([]);
        $assertThrows(function () {
            Cert::certificates();
        }, '请配置平台证书缓存路径');
    });

    $test('Uploads rejects a missing file before making a request', function () use ($assertThrows) {
        $uploads = new Uploads();
        $path = dirname(__DIR__) . '/tests/this-file-does-not-exist.png';
        $assertThrows(function () use ($uploads, $path) {
            $uploads->upload($path);
        }, '上传文件不存在或不可读：' . $path);
    });
} finally {
    Config::setConfig($originalConfig);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
