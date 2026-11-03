<?php

namespace jiujiude\WechatEcommerce;

/**
 * 微信支付电商收付通合单支付、订单查询与退款接口。
 * 提供合单下单及支付参数生成，并支持合单订单和退款单的查询、关闭。
 */
class Pay
{
    /**
     * 根据子单参数组装微信支付 API 所需的子单列表。
     *
     * @param array $sub_orders 子单信息列表，每项包含子商户号、子商户订单号、商品描述和金额等字段。
     * @return array 符合合单下单接口格式的子单列表。
     */
    private function buildSubOrders($sub_orders)
    {
        $orders = [];
        foreach ($sub_orders as $order) {
            $subOrder = [
                // 子单发起方商户号，使用配置中的电商平台商户号。
                'mchid' => Config::$config['MCHID'],
                // 子商户系统内的订单号。
                'out_trade_no' => $order['out_trade_no'] ?? '',
                // 微信支付分配给二级商户的商户号。
                'sub_mchid' => (string) ($order['sub_mchid'] ?? ''),
                // 商品或服务描述。
                'description' => $order['description'] ?? '',
                // 子单金额，单位为分；币种未指定时默认人民币。
                'amount' => [
                    'total_amount' => $order['total_amount'] ?? 0,
                    'currency' => $order['currency'] ?? 'CNY',
                ],
            ];
            // 附加数据会在查询接口及支付通知中原样返回；未提供或为空时不传。
            if (isset($order['attach']) && $order['attach'] !== '') {
                $subOrder['attach'] = $order['attach'];
            }
            // 设置子单是否参与分账，仅在调用方显式传入该字段时加入请求。
            if (isset($order['profit_sharing_settle'])) {
                $subOrder['settle_info'] = ['profit_sharing' => (bool) $order['profit_sharing_settle']];
            }
            // 商品详情为可选信息，按调用方提供的内容透传。
            if (isset($order['detail'])) {
                $subOrder['detail'] = $order['detail'];
            }
            $orders[] = $subOrder;
        }
        return $orders;
    }

    /**
     * 创建合单并使用 JSAPI 支付。
     *
     * @param string $combine_out_trade_no 合单商户订单号。
     * @param array $sub_orders            合单中的子单信息列表。
     * @param string $openid               用户在合单公众号 AppID 下的 OpenID；为空时不传支付者信息。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function closingOrderJsapi($combine_out_trade_no, $sub_orders, $openid)
    {
        $paramData = [
            // 优先使用公众号对应的合单 AppID；未配置时回退到通用合单 AppID。
            'combine_appid' => Config::$config['PUB']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'],
            // 合单发起方的商户号。
            'combine_mchid' => Config::$config['COMBINE_MCHID'],
            // 商户系统生成的合单订单号。
            'combine_out_trade_no' => $combine_out_trade_no,
            // 合单内的各笔子单。
            'sub_orders' => $this->buildSubOrders($sub_orders),
            // 微信支付异步支付通知的接收地址。
            'notify_url' => Config::$config['PAY_NOTIFY_URL'],
        ];
        // JSAPI 支付需要提供用户 OpenID；空字符串时省略支付者信息。
        if ($openid !== '') {
            $paramData['combine_payer_info'] = ['openid' => $openid];
        }
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/combine-transactions/jsapi',
            json_encode($paramData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * 创建合单并使用微信 App 支付。
     *
     * @param string $combine_out_trade_no 合单商户订单号。
     * @param array $sub_orders            合单中的子单信息列表。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function closingOrderApp($combine_out_trade_no, $sub_orders)
    {
        $paramData = [
            // 优先使用 App 对应的合单 AppID；未配置时回退到通用合单 AppID。
            'combine_appid' => Config::$config['APP']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'],
            // 合单发起方的商户号。
            'combine_mchid' => Config::$config['COMBINE_MCHID'],
            // 商户系统生成的合单订单号。
            'combine_out_trade_no' => $combine_out_trade_no,
            // 合单内的各笔子单。
            'sub_orders' => $this->buildSubOrders($sub_orders),
            // 微信支付异步支付通知的接收地址。
            'notify_url' => Config::$config['PAY_NOTIFY_URL'],
        ];
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/combine-transactions/app',
            json_encode($paramData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * 生成微信 App 支付所需的调起参数。
     *
     * @param string $prepay_id 合单下单接口返回的预支付交易会话标识。
     * @return string App 支付参数 JSON 字符串。
     */
    public function appPay($prepay_id)
    {
        return Signs::_PayAppJson(Config::$config['APP']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'], $prepay_id);
    }

    /**
     * 生成公众号 JSAPI 支付所需的调起参数。
     *
     * @param string $prepay_id 合单下单接口返回的预支付交易会话标识。
     * @return string JSAPI 支付参数 JSON 字符串。
     */
    public function jsPay($prepay_id)
    {
        return Signs::_PayJson(Config::$config['PUB']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'], $prepay_id);
    }

    /**
     * 生成微信小程序支付所需的调起参数。
     *
     * @param string $prepay_id 合单下单接口返回的预支付交易会话标识。
     * @return string 小程序支付参数 JSON 字符串。
     */
    public function xcxPay($prepay_id)
    {
        return Signs::_PayJson(Config::$config['XCX']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'], $prepay_id);
    }

    /**
     * 按合单商户订单号查询合单订单。
     *
     * @param string $combine_out_trade_no 商户系统生成的合单订单号。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function findorder($combine_out_trade_no)
    {
        // 对订单号进行 URL 编码，避免特殊字符影响请求路径。
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/out-trade-no/' . rawurlencode((string) $combine_out_trade_no);
        return Signs::_Getresponse($url);
    }

    /**
     * 关闭合单订单。
     * 合单订单应通过合单关单接口关闭，并传入对应的子单信息。
     *
     * @param string $combine_out_trade_no 商户系统生成的合单订单号。
     * @param array $orders                 合单中的子单信息。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function closeorder($combine_out_trade_no, $orders)
    {
        $data = [
            // 合单发起方 AppID（此处按当前 SDK 配置使用通用合单 AppID）。
            'combine_appid' => Config::$config['COMBINE_APPID'],
            // 需要关闭的子单信息。
            'sub_orders' => $orders,
        ];
        // 对合单订单号进行 URL 编码后构造关单接口地址。
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/out-trade-no/' . rawurlencode((string) $combine_out_trade_no) . '/close';
        return Signs::_Postresponse($url, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 申请电商收付通退款。
     * 退款金额和原订单金额均以分为单位，退款通知地址从配置中读取。
     *
     * @param string $sub_mchid      微信支付分配给二级商户的商户号。
     * @param string $out_refund_no  商户退款单号。
     * @param string $transaction_id 微信支付订单号。
     * @param int    $refund_fee     申请退款金额，单位为分。
     * @param int    $total_fee      原订单金额，单位为分。
     * @param string $reason         退款原因；为空时不发送该字段。
     * @param string $out_trade_no   子商户订单号；非空时随请求发送。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function applyRefund($sub_mchid, $out_refund_no, $transaction_id, $refund_fee, $total_fee, $reason = '', $out_trade_no = '')
    {
        $post = [
            // 发起退款的二级商户号。
            'sub_mchid' => (string) $sub_mchid,
            // 电商平台 AppID。
            'sp_appid' => Config::$config['COMBINE_APPID'],
            // 原支付交易对应的微信支付订单号。
            'transaction_id' => $transaction_id,
            // 商户退款单号。
            'out_refund_no' => (string) $out_refund_no,
            // 退款金额、原订单金额及币种。
            'amount' => [
                'refund' => (int) $refund_fee,
                'total' => (int) $total_fee,
                'currency' => 'CNY',
            ],
            // 微信支付退款结果异步通知的接收地址。
            'notify_url' => Config::$config['REFUND_NOTIFY_URL'],
        ];
        // 子商户订单号为可选字段，未提供时不放入请求。
        if ($out_trade_no !== '') {
            $post['out_trade_no'] = $out_trade_no;
        }
        // 退款原因是可选字段，未提供时不放入请求。
        if ($reason !== '') {
            $post['reason'] = $reason;
        }
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/refunds/apply',
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * 查询电商收付通退款状态。
     * 优先按微信退款单号查询；未提供微信退款单号时按商户退款单号查询。
     *
     * @param string $sub_mchid     微信支付分配给二级商户的商户号。
     * @param string $refund_id     微信支付退款单号；非空时按此编号查询。
     * @param string $out_refund_no 商户退款单号；未提供 $refund_id 时使用。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function findRefund($sub_mchid, $refund_id = '', $out_refund_no = '')
    {
        // 根据提供的编号类型选择退款查询接口路径，并编码路径参数。
        $identifier = $refund_id !== '' ? 'id/' . rawurlencode((string) $refund_id) : 'out-refund-no/' . rawurlencode((string) $out_refund_no);
        // 二级商户号通过查询字符串传递并进行 RFC 3986 编码。
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/refunds/' . $identifier . '?sub_mchid=' . rawurlencode((string) $sub_mchid);
        return Signs::_Getresponse($url);
    }
}
