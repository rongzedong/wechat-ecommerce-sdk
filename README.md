# wechat-ecommerce-sdk

_PHP微信支付电商收付通V3版本SDK_ 

### 安装方法
> #### composer require jiujiude/wechat-ecommerce-sdk

本 SDK 依赖微信支付官方 PHP SDK `wechatpay/wechatpay`（`^1.5`），RSA/AES-GCM 密码操作和 APIv3 请求签名格式使用官方实现。PHP 最低版本保持为 7.2；部署时需安装 `ext-curl`、`ext-openssl`，并按官方 SDK 要求安装 Composer 依赖。

### 类功能说明

#### src/Account.php
> #####  分账功能

#### src/Apply.php
> ##### 二级商户进件、特约商户进件

#### src/Bill.php
> ##### 查询余额、账单下载

#### src/Cert.php
> ##### 获取的平台证书

#### src/Config.php
> ##### 初始化配置

#### src/Notify.php
> ##### 处理回调、数据验证、验证签名

#### src/Pay.php
> ##### 合单支付、订单查询、退款

#### src/Signs.php
> ##### 包括签名，验证签名，加密，解密，请求，头部

#### src/Uploads.php
> ##### 图片上传

#### src/Withdraw.php
> ##### 商户提现功能

#### src/Banks.php
> ##### 银行组件能力

#### src/WxPayv3Exception.php
> ##### 异常接收

> ### 最新更新
2026-11-03 更新升级符合 V3 API的要求（未测试）

