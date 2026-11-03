<?php

namespace jiujiude\WechatEcommerce;

/**
 * 分账功能
 */
class Account
{
    public function addReceivers($type, $account, $name, $relation_type = 'SERVICE_PROVIDER')
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/receivers/add';
        $post = [
            'appid' => Config::$config['COMBINE_APPID'],
            'type' => $type,
            'account' => $account,
            'relation_type' => $relation_type,
        ];
        if ($type === 'MERCHANT_ID') {
            $post['name'] = $name;
        }
        if ($type === 'PERSONAL_OPENID') {
            $post['encrypted_name'] = Signs::getEncrypt($name);
        }

        return Signs::_Postresponse($url, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function delReceivers($type, $account)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/receivers/delete';
        $post = [
            'appid' => Config::$config['COMBINE_APPID'],
            'type' => $type,
            'account' => $account,
        ];

        return Signs::_Postresponse($url, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function reqAccount($out_order_no, $transaction_id, $sub_mchid, $receivers, $finish = true)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/orders';
        $post = [
            'appid' => Config::$config['COMBINE_APPID'],
            'out_order_no' => $out_order_no,
            'transaction_id' => $transaction_id,
            'sub_mchid' => strval($sub_mchid),
            'receivers' => $receivers,
            'finish' => $finish,
        ];

        return Signs::_Postresponse($url, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function findAccount($out_order_no, $transaction_id, $sub_mchid)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/orders';
        $url .= '?sub_mchid=' . urlencode((string) $sub_mchid) . '&transaction_id=' . urlencode((string) $transaction_id) . '&out_order_no=' . urlencode((string) $out_order_no);

        return Signs::_Getresponse($url);
    }

    public function finishAccount($out_order_no, $transaction_id, $sub_mchid, $description)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/finish-order';
        $post = [
            'sub_mchid' => strval($sub_mchid),
            'transaction_id' => $transaction_id,
            'out_order_no' => $out_order_no,
            'description' => $description ?: '分账完结',
        ];

        return Signs::_Postresponse($url, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function returnAccount($sub_mchid, $order_id, $out_order_no, $out_return_no, $return_mchid, $amount, $description)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/returnorders';
        $post = [
            'sub_mchid' => $sub_mchid,
            'order_id' => $order_id,
            'out_order_no' => $out_order_no,
            'out_return_no' => $out_return_no,
            'return_mchid' => $return_mchid,
            'amount' => (int) $amount,
            'description' => $description,
        ];

        return Signs::_Postresponse($url, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function findreturnAccount($sub_mchid, $order_id = '', $out_order_no = '', $out_return_no = '')
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/returnorders';
        $query = ['sub_mchid' => $sub_mchid];
        if ($order_id !== '') {
            $query['order_id'] = $order_id;
        }
        if ($out_order_no !== '') {
            $query['out_order_no'] = $out_order_no;
        }
        if ($out_return_no !== '') {
            $query['out_return_no'] = $out_return_no;
        }
        $url .= '?' . http_build_query($query);

        return Signs::_Getresponse($url);
    }
}
