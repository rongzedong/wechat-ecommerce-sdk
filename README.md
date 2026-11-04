# wechat-ecommerce-sdk

_PHP 微信支付电商收付通 V3 版本 SDK_

## 安装方法

```bash
composer require jiujiude/wechat-ecommerce-sdk
```

本 SDK 依赖微信支付官方 PHP SDK `wechatpay/wechatpay:^1.5`，RSA/AES-GCM 密码操作和 APIv3 请求签名格式使用官方实现。PHP 最低版本为 7.2；部署环境需启用 cURL 和 OpenSSL 扩展。

## 配置

在业务入口加载 Composer 自动加载文件，并调用 `Config::setConfig()` 初始化。下面示例包含请求签名、个人分账接收方加密和回调验签/解密所需配置：

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use jiujiude\WechatEcommerce\Config;

Config::setConfig([
    // 商户 API 请求签名
    'MCHID' => '你的商户号',
    'SSLKEY_PATH' => __DIR__ . '/cert/apiclient_key.pem',
    'SERIAL_NO' => '商户API证书序列号',

    // 电商收付通分账使用的 AppID
    'COMBINE_APPID' => '你的AppID',

    // 回调通知资源解密需要，必须是32字节的APIv3密钥。
    // 配置项名称沿用当前 SDK 的 APPKERV3。
    'APPKERV3' => '替换为32字节的APIv3密钥',

    // 微信支付公钥模式：从商户平台 API 安全中取得公钥 ID 和对应 PEM 公钥文件。
    // 不是平台证书序列号；ID 必须与该公钥文件对应。
    'PLATFORM_PUBLIC_KEY_ID' => '微信支付公钥ID',
    'PLATFORM_PUBLIC_KEY_PATH' => __DIR__ . '/cert/wechatpay_public_key.pem',
]);
```

请妥善保管商户私钥和 APIv3 密钥，不要提交真实密钥或证书到公开仓库。公钥文件应与商户平台中配置的微信支付公钥 ID 相匹配。

### 微信支付公钥模式说明

个人分账接收方姓名加密和回调通知验签使用配置的微信支付公钥，不要求 SDK 下载或缓存平台证书。请求/回调头中的 `Wechatpay-Serial` 使用 `PLATFORM_PUBLIC_KEY_ID`，即微信支付公钥 ID。回调通知资源仍使用 APIv3 密钥解密，所以接收并解密通知时仍需要配置 `APPKERV3`。

`src/Cert.php` 的平台证书下载/缓存能力仍保留给其他继续采用平台证书的调用场景；它不是下面个人接收方姓名加密和回调验签示例的依赖。

## 使用示例

### 分账：添加个人接收方

```php
use jiujiude\WechatEcommerce\Account;

$account = new Account();
$response = $account->addReceivers(
    'PERSONAL_OPENID',
    '接收方OpenID',
    '接收方真实姓名',
    'SERVICE_PROVIDER'
);

// $response 是微信支付接口返回的 JSON 字符串；请根据业务处理接口结果。
echo $response;
```

该调用会用配置的微信支付公钥加密姓名，并在请求头中附带对应的 `Wechatpay-Serial` 公钥 ID。商户类型接收方可使用 `MERCHANT_ID`；其名称按接口要求明文提交，无需个人姓名加密。

### 回调：验签并解密支付通知

```php
use jiujiude\WechatEcommerce\Notify;

$notify = new Notify();
$rawBody = file_get_contents('php://input');
$headers = $notify->em_getallheaders();

try {
    // 返回解密后的通知资源 JSON 字符串。
    $resource = $notify->notifyPay($rawBody, $headers);
    // TODO: 解析 $resource，并按业务需要响应微信支付通知。
    echo $resource;
} catch (\jiujiude\WechatEcommerce\WxPayv3Exception $exception) {
    // 验签失败、通知格式错误或解密失败时处理异常。
    http_response_code(400);
    echo $exception->errorMessage();
}
```

退款成功通知可使用 `$notify->notifyRefund($rawBody, $headers)`。生产环境中应根据微信支付通知协议返回规定格式的成功/失败响应，并做好幂等处理。

## 类功能说明

| 文件 | 功能 |
| --- | --- |
| `src/Account.php` | 电商收付通分账：添加/删除分账接收方、请求/查询分账、完结分账和分账回退。个人接收方姓名通过微信支付公钥加密。 |
| `src/Apply.php` | 二级商户进件、特约商户进件 |
| `src/Bill.php` | 查询余额、账单下载 |
| `src/Cert.php` | 下载、解密并缓存微信支付 APIv3 平台证书（证书模式的可选能力） |
| `src/Config.php` | 初始化 SDK 配置；公钥模式使用 `PLATFORM_PUBLIC_KEY_ID`、`PLATFORM_PUBLIC_KEY_PATH` |
| `src/Notify.php` | 处理支付/退款回调，使用微信支付公钥验签并解密通知资源 |
| `src/Pay.php` | 合单支付、订单查询、退款 |
| `src/Signs.php` | APIv3 请求签名、HTTP 请求、RSA/AES-GCM 加密解密及支付参数签名 |
| `src/Uploads.php` | 图片上传 |
| `src/Withdraw.php` | 商户提现功能 |
| `src/Banks.php` | 银行组件能力 |
| `src/WxPayv3Exception.php` | SDK 异常类 |

## 最新更新

2026-11-04：分账个人接收方加密和回调验签支持微信支付公钥模式。
