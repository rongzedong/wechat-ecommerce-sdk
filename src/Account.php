<?php

namespace jiujiude\WechatEcommerce;

/** 电商收付通分账接口。 */
class Account
{
    public function addReceivers($type, $account, $name, $relation_type = 'SERVICE_PROVIDER')
    {
        $post = [
            'appid' => Config::$config['COMBINE_APPID'],
            'type' => $type,
            'account' => $account,
            'relation_type' => $relation_type,
        ];
        if ($type === 'MERCHANT_ID') {
            $post['name'] = $name;
        } elseif ($type === 'PERSONAL_OPENID') {
            $post['encrypted_name'] = Signs::getEncrypt($name);
        }

        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/receivers/add',
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function delReceivers($type, $account)
    {
        $post = [
            'appid' => Config::$config['COMBINE_APPID'],
            'type' => $type,
            'account' => $account,
        ];

        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/receivers/delete',
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function reqAccount($out_order_no, $transaction_id, $sub_mchid, $receivers, $finish = true)
    {
        $post = [
            'appid' => Config::$config['COMBINE_APPID'],
            'out_order_no' => $out_order_no,
            'transaction_id' => $transaction_id,
            'sub_mchid' => (string) $sub_mchid,
            'receivers' => $receivers,
            'finish' => (bool) $finish,
        ];

        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/orders',
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function findAccount($out_order_no, $transaction_id, $sub_mchid)
    {
        $query = http_build_query([
            'sub_mchid' => (string) $sub_mchid,
            'transaction_id' => (string) $transaction_id,
            'out_order_no' => (string) $out_order_no,
        ], '', '&', PHP_QUERY_RFC3986);

        return Signs::_Getresponse('https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/orders?' . $query);
    }

    public function finishAccount($out_order_no, $transaction_id, $sub_mchid, $description)
    {
        $post = [
            'sub_mchid' => (string) $sub_mchid,
            'transaction_id' => $transaction_id,
            'out_order_no' => $out_order_no,
            'description' => $description ?: '分账完结',
        ];

        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/finish-order',
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function returnAccount($sub_mchid, $order_id, $out_order_no, $out_return_no, $return_mchid, $amount, $description)
    {
        $post = [
            'sub_mchid' => (string) $sub_mchid,
            'order_id' => $order_id,
            'out_order_no' => $out_order_no,
            'out_return_no' => $out_return_no,
            'return_mchid' => (string) $return_mchid,
            'amount' => (int) $amount,
            'description' => $description,
        ];

        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/returnorders',
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function findreturnAccount($sub_mchid, $order_id = '', $out_order_no = '', $out_return_no = '')
    {
        $query = ['sub_mchid' => (string) $sub_mchid];
        if ($order_id !== '') {
            $query['order_id'] = (string) $order_id;
        } elseif ($out_order_no !== '') {
            $query['out_order_no'] = (string) $out_order_no;
        } else {
            throw new WxPayv3Exception('order_id 和 out_order_no 必须提供其中一个');
        }
        if ($out_return_no !== '') {
            $query['out_return_no'] = (string) $out_return_no;
        }

        return Signs::_Getresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/returnorders?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
        );
    }
}
