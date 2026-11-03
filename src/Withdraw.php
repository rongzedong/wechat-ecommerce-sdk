<?php

namespace jiujiude\WechatEcommerce;

/** 电商收付通二级商户提现接口。 */
class Withdraw
{
    public function fundWithdraw($sub_mchid, $out_request_no, $amount, $remark = '交易提现', $bank_memo = '', $account_type = '')
    {
        $data = [
            'sub_mchid' => (string) $sub_mchid,
            'out_request_no' => (string) $out_request_no,
            'amount' => (int) $amount,
            'remark' => $remark,
        ];
        if ($bank_memo !== '') {
            $data['bank_memo'] = $bank_memo;
        }
        if ($account_type !== '') {
            $data['account_type'] = $account_type;
        }
        return Signs::_Postresponse(
            'https://api.mch.weixin.qq.com/v3/ecommerce/fund/withdraw',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function getWithdrawByid($sub_mchid, $withdraw_id)
    {
        $query = http_build_query(['sub_mchid' => (string) $sub_mchid], '', '&', PHP_QUERY_RFC3986);
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/withdraw/' . rawurlencode((string) $withdraw_id) . '?' . $query;
        return Signs::_Getresponse($url);
    }

    public function getWithdrawByno($sub_mchid, $out_request_no)
    {
        $query = http_build_query(['sub_mchid' => (string) $sub_mchid], '', '&', PHP_QUERY_RFC3986);
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/withdraw/out-request-no/' . rawurlencode((string) $out_request_no) . '?' . $query;
        return Signs::_Getresponse($url);
    }
}
