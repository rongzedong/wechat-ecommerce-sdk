<?php

namespace jiujiude\WechatEcommerce;

/** 电商收付通二级商户提现接口。 */
class Withdraw
{
    /**
     * 申请将二级商户余额提现至其结算账户。
     *
     * @param string $sub_mchid      微信支付分配给二级商户的商户号。
     * @param string $out_request_no 商户系统生成的提现申请单号。
     * @param int    $amount         提现金额，单位为分。
     * @param string $remark         提现备注，默认为“交易提现”。
     * @param string $bank_memo      银行附言；为空时不向接口发送该字段。
     * @param string $account_type   账户类型；为空时不向接口发送该字段。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function fundWithdraw($sub_mchid, $out_request_no, $amount, $remark = '交易提现', $bank_memo = '', $account_type = '')
    {
        $data = [
            // 微信支付分配的二级商户号。
            'sub_mchid' => (string) $sub_mchid,
            // 商户侧提现申请单号，用于标识本次提现请求。
            'out_request_no' => (string) $out_request_no,
            // 提现金额以分为单位，转换为整数后提交。
            'amount' => (int) $amount,
            // 提现申请备注。
            'remark' => $remark,
        ];
        // 银行附言为可选参数，仅在非空时加入请求。
        if ($bank_memo !== '') {
            $data['bank_memo'] = $bank_memo;
        }
        // 账户类型为可选参数，仅在非空时加入请求。
        if ($account_type !== '') {
            $data['account_type'] = $account_type;
        }
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/fund/withdraw',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * 根据微信支付提现单号查询提现状态。
     *
     * @param string $sub_mchid  微信支付分配给二级商户的商户号。
     * @param string $withdraw_id 微信支付返回的提现单号。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function getWithdrawByid($sub_mchid, $withdraw_id)
    {
        // 查询条件中的二级商户号采用 RFC 3986 规则编码。
        $query = http_build_query(['sub_mchid' => (string) $sub_mchid], '', '&', PHP_QUERY_RFC3986);
        // 提现单号属于 URL 路径参数，先进行 URL 编码。
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/withdraw/' . rawurlencode((string) $withdraw_id) . '?' . $query;
        return Signs::_Getresponse($url);
    }

    /**
     * 根据商户提现申请单号查询提现状态。
     *
     * @param string $sub_mchid      微信支付分配给二级商户的商户号。
     * @param string $out_request_no 商户提交提现申请时使用的申请单号。
     * @return string|bool 微信支付接口的响应内容或请求失败时的结果。
     */
    public function getWithdrawByno($sub_mchid, $out_request_no)
    {
        // 查询条件中的二级商户号采用 RFC 3986 规则编码。
        $query = http_build_query(['sub_mchid' => (string) $sub_mchid], '', '&', PHP_QUERY_RFC3986);
        // 商户申请单号是路径参数，进行 URL 编码后拼接查询地址。
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/withdraw/out-request-no/' . rawurlencode((string) $out_request_no) . '?' . $query;
        return Signs::_Getresponse($url);
    }
}
