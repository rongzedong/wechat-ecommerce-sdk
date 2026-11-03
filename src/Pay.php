<?php

namespace jiujiude\WechatEcommerce;

class Pay
{
    private function buildSubOrders($sub_orders)
    {
        $orders = [];
        foreach ($sub_orders as $order) {
            $subOrder = [
                'mchid' => Config::$config['MCHID'],
                'out_trade_no' => $order['out_trade_no'] ?? '',
                'sub_mchid' => (string) ($order['sub_mchid'] ?? ''),
                'description' => $order['description'] ?? '',
                'amount' => [
                    'total_amount' => $order['total_amount'] ?? 0,
                    'currency' => $order['currency'] ?? 'CNY',
                ],
            ];
            if (isset($order['attach']) && $order['attach'] !== '') {
                $subOrder['attach'] = $order['attach'];
            }
            if (isset($order['profit_sharing_settle'])) {
                $subOrder['settle_info'] = ['profit_sharing' => (bool) $order['profit_sharing_settle']];
            }
            if (isset($order['detail'])) {
                $subOrder['detail'] = $order['detail'];
            }
            $orders[] = $subOrder;
        }
        return $orders;
    }

    public function closingOrderJsapi($combine_out_trade_no, $sub_orders, $openid)
    {
        $paramData = [
            'combine_appid' => Config::$config['PUB']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'],
            'combine_mchid' => Config::$config['COMBINE_MCHID'],
            'combine_out_trade_no' => $combine_out_trade_no,
            'sub_orders' => $this->buildSubOrders($sub_orders),
            'notify_url' => Config::$config['PAY_NOTIFY_URL'],
        ];
        if ($openid !== '') {
            $paramData['combine_payer_info'] = ['openid' => $openid];
        }
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/combine-transactions/jsapi',
            json_encode($paramData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function closingOrderApp($combine_out_trade_no, $sub_orders)
    {
        $paramData = [
            'combine_appid' => Config::$config['APP']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'],
            'combine_mchid' => Config::$config['COMBINE_MCHID'],
            'combine_out_trade_no' => $combine_out_trade_no,
            'sub_orders' => $this->buildSubOrders($sub_orders),
            'notify_url' => Config::$config['PAY_NOTIFY_URL'],
        ];
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/combine-transactions/app',
            json_encode($paramData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function appPay($prepay_id)
    {
        return Signs::_PayAppJson(Config::$config['APP']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'], $prepay_id);
    }

    public function jsPay($prepay_id)
    {
        return Signs::_PayJson(Config::$config['PUB']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'], $prepay_id);
    }

    public function xcxPay($prepay_id)
    {
        return Signs::_PayJson(Config::$config['XCX']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'], $prepay_id);
    }

    public function findorder($combine_out_trade_no)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/out-trade-no/' . rawurlencode((string) $combine_out_trade_no);
        return Signs::_Getresponse($url);
    }

    public function closeorder($combine_out_trade_no, $orders)
    {
        $data = [
            'combine_appid' => Config::$config['COMBINE_APPID'],
            'sub_orders' => $orders,
        ];
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/out-trade-no/' . rawurlencode((string) $combine_out_trade_no) . '/close';
        return Signs::_Postresponse($url, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function applyRefund($sub_mchid, $out_refund_no, $transaction_id, $refund_fee, $total_fee, $reason = '', $out_trade_no = '')
    {
        $post = [
            'sub_mchid' => (string) $sub_mchid,
            'sp_appid' => Config::$config['COMBINE_APPID'],
            'transaction_id' => $transaction_id,
            'out_refund_no' => (string) $out_refund_no,
            'amount' => [
                'refund' => (int) $refund_fee,
                'total' => (int) $total_fee,
                'currency' => 'CNY',
            ],
            'notify_url' => Config::$config['REFUND_NOTIFY_URL'],
        ];
        if ($out_trade_no !== '') {
            $post['out_trade_no'] = $out_trade_no;
        }
        if ($reason !== '') {
            $post['reason'] = $reason;
        }
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/refunds/apply',
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function findRefund($sub_mchid, $refund_id = '', $out_refund_no = '')
    {
        $identifier = $refund_id !== '' ? 'id/' . rawurlencode((string) $refund_id) : 'out-refund-no/' . rawurlencode((string) $out_refund_no);
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/refunds/' . $identifier . '?sub_mchid=' . rawurlencode((string) $sub_mchid);
        return Signs::_Getresponse($url);
    }
}
