<?php

namespace jiujiude\WechatEcommerce;

/**
 * 电商收付通分账接口。
 * 提供添加/删除分账接收方、请求/查询分账、完结分账及分账回退功能。
 */
class Account
{
    /**
     * 添加分账接收方。
     * 接收方类型为商户时提交商户名称；类型为个人时将姓名加密后提交。
     * @param string $type 接收方类型：MERCHANT_ID（商户）或 PERSONAL_OPENID（个人）
     * @param string $account 接收方账号：商户号或个人 OpenID
     * @param string $name 接收方名称；个人姓名将按 APIv3 要求加密
     * @param string $relation_type 分账方关系类型，如 SERVICE_PROVIDER、PLATFORM、SUPPLIER、DISTRIBUTOR、OTHERS
     * @return string 微信支付接口响应
     */
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

    /**
     * 删除分账接收方。
     * @param string $type 接收方类型：MERCHANT_ID（商户）或 PERSONAL_OPENID（个人）
     * @param string $account 接收方账号：商户号或个人 OpenID
     * @return string 微信支付接口响应
     */
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

    /**
     * 发起分账请求；每笔订单的请求次数和接收方数量受微信支付接口限制。
     * @param string $out_order_no 商户分账单号，由商户系统生成
     * @param string $transaction_id 微信支付订单号
     * @param string $sub_mchid 分账出资的二级商户号
     * @param array $receivers 分账接收方列表
     * @param bool $finish 是否完结分账；true 表示本次分账后结束分账
     * @return string 微信支付接口响应
     */
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

    /**
     * 查询分账结果。
     * @param string $out_order_no 商户分账单号
     * @param string $transaction_id 微信支付订单号
     * @param string $sub_mchid 分账出资的二级商户号
     * @return string 微信支付接口响应
     */
    public function findAccount($out_order_no, $transaction_id, $sub_mchid)
    {
        $query = http_build_query([
            'sub_mchid' => (string) $sub_mchid,
            'transaction_id' => (string) $transaction_id,
            'out_order_no' => (string) $out_order_no,
        ], '', '&', PHP_QUERY_RFC3986);

        return Signs::_Getresponse('https://api.mch.weixin.qq.com/v3/ecommerce/profitsharing/orders?' . $query);
    }

    /**
     * 完结分账，将订单剩余待分金额解冻给二级商户。
     * @param string $out_order_no 商户分账单号
     * @param string $transaction_id 微信支付子单号
     * @param string $sub_mchid 电商平台二级商户号
     * @param string $description 完结分账描述；为空时使用“分账完结”
     * @return string 微信支付接口响应
     */
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

    /**
     * 请求分账回退，将已分给接收方的资金退回分账方。
     * @param string $sub_mchid 分账出资的二级商户号
     * @param string $order_id 微信分账单号；与 out_order_no 二选一
     * @param string $out_order_no 商户分账单号；与 order_id 二选一
     * @param string $out_return_no 商户回退单号，由商户系统生成
     * @param string $return_mchid 回退接收方商户号
     * @param int $amount 回退金额，单位为分
     * @param string $description 回退描述
     * @return string 微信支付接口响应
     */
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

    /**
     * 查询分账回退结果。
     * @param string $sub_mchid 分账出资的二级商户号
     * @param string $order_id 微信分账单号；或通过 out_order_no 查询
     * @param string $out_order_no 商户分账单号；与 order_id 二选一
     * @param string $out_return_no 商户回退单号，可选
     * @return string 微信支付接口响应
     */
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
