<?php

namespace jiujiude\WechatEcommerce;

class Pay
{
    public function closingOrderJsapi($combine_out_trade_no, $sub_orders, $openid)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/jsapi';
        $orders = [];
        foreach ($sub_orders as $order) {
            $orders[] = [
                'mchid' => Config::$config['MCHID'],
                'attach' => $order['attach'] ?? '',
                'out_trade_no' => $order['out_trade_no'] ?? '',
                'sub_mchid' => strval($order['sub_mchid'] ?? ''),
                'description' => $order['description'] ?? '',
                'amount' => [
                    'total_amount' => $order['total_amount'] ?? 0,
                    'currency' => 'CNY',
                ],
                'settle_info' => [
                    'profit_sharing' => $order['profit_sharing_settle'] ?? false,
                ],
            ];
        }
        $paramData = [
            'combine_appid' => Config::$config['PUB']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'],
            'combine_mchid' => Config::$config['COMBINE_MCHID'],
            'combine_out_trade_no' => $combine_out_trade_no,
            'sub_orders' => $orders,
            'combine_payer_info' => [
                'openid' => $openid,
            ],
            'notify_url' => Config::$config['PAY_NOTIFY_URL'],
        ];
        if (empty($openid)) {
            unset($paramData['combine_payer_info']);
        }
        return Signs::_Postresponse($url, json_encode($paramData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function closingOrderApp($combine_out_trade_no, $sub_orders)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/app';
        $orders = [];
        foreach ($sub_orders as $order) {
            $orders[] = [
                'mchid' => Config::$config['MCHID'],
                'attach' => $order['attach'] ?? '',
                'out_trade_no' => $order['out_trade_no'] ?? '',
                'sub_mchid' => strval($order['sub_mchid'] ?? ''),
                'description' => $order['description'] ?? '',
                'amount' => [
                    'total_amount' => $order['total_amount'] ?? 0,
                    'currency' => 'CNY',
                ],
                'settle_info' => [
                    'profit_sharing' => $order['profit_sharing_settle'] ?? false,
                ],
            ];
        }
        $paramData = [
            'combine_appid' => Config::$config['APP']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'],
            'combine_mchid' => Config::$config['COMBINE_MCHID'],
            'combine_out_trade_no' => $combine_out_trade_no,
            'sub_orders' => $orders,
            'notify_url' => Config::$config['PAY_NOTIFY_URL'],
        ];
        return Signs::_Postresponse($url, json_encode($paramData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function appPay($prepay_id)
    {
        $appid = Config::$config['APP']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'];
        return Signs::_PayAppJson($appid, $prepay_id);
    }

    public function jsPay($prepay_id)
    {
        $appid = Config::$config['PUB']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'];
        return Signs::_PayJson($appid, $prepay_id);
    }

    public function xcxPay($prepay_id)
    {
        $appid = Config::$config['XCX']['COMBINE_APPID'] ?? Config::$config['COMBINE_APPID'];
        return Signs::_PayJson($appid, $prepay_id);
    }

    public function findorder($combine_out_trade_no)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/out-trade-no/' . rawurlencode((string) $combine_out_trade_no);
        return Signs::_Getresponse($url);
    }

    public function closeorder($combine_out_trade_no, $orders)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/combine-transactions/out-trade-no/' . rawurlencode((string) $combine_out_trade_no) . '/close';
        $data = [
            'combine_appid' => Config::$config['COMBINE_APPID'],
            'sub_orders' => $orders,
        ];
        return Signs::_Postresponse($url, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function applyRefund($sub_mchid, $out_refund_no, $transaction_id, $refund_fee, $total_fee, $reason = '', $out_trade_no = '')
    {
        $post = [
            'sub_mchid' => strval($sub_mchid),
            'sp_appid' => Config::$config['COMBINE_APPID'],
            'transaction_id' => $transaction_id,
            'out_trade_no' => $out_trade_no,
            'out_refund_no' => strval($out_refund_no),
            'reason' => $reason,
            'amount' => [
                'refund' => $refund_fee,
                'total' => $total_fee,
                'currency' => 'CNY',
            ],
            'notify_url' => Config::$config['REFUND_NOTIFY_URL'],
        ];
        if ($out_trade_no === '') {
            unset($post['out_trade_no']);
        }
        if ($reason === '') {
            unset($post['reason']);
        }
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/refunds/apply';
        return Signs::_Postresponse($url, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function findRefund($sub_mchid, $refund_id = '', $out_refund_no = '')
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/refunds/';
        if ($refund_id !== '') {
            $url .= 'id/' . rawurlencode((string) $refund_id);
        } else {
            $url .= 'out-refund-no/' . rawurlencode((string) $out_refund_no);
        }
        $url .= '?sub_mchid=' . rawurlencode((string) $sub_mchid);
        return Signs::_Getresponse($url);
    }
}
