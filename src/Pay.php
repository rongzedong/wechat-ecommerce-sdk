<?php

namespace jiujiude\WechatEcommerce;

class Bill
{
    public function getComBalance($fund_type = 1, $account_type = 'BASIC', $date = '')
    {
        if (!in_array($account_type, ['BASIC', 'OPERATION', 'FEES'], true)) {
            throw new WxPayv3Exception('查询的账户类型不存在');
        }
        switch ($fund_type) {
            case 1:
                $http_url = 'https://api.mch.weixin.qq.com/v3/merchant/fund/balance/' . $account_type;
                break;
            case 2:
                $date = $date ?: date('Y-m-d');
                $http_url = 'https://api.mch.weixin.qq.com/v3/merchant/fund/dayendbalance/' . $account_type . '?date=' . urlencode($date);
                break;
            default:
                throw new WxPayv3Exception('请求路径错误');
        }
        return Signs::_Getresponse($http_url);
    }

    public function getShopBalance($sub_mchid, $fund_type = 1, $date = '')
    {
        if (empty($sub_mchid)) {
            throw new WxPayv3Exception('查询的二级商户不能为空');
        }
        switch ($fund_type) {
            case 1:
                $http_url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/balance/' . rawurlencode((string) $sub_mchid);
                break;
            case 2:
                $date = $date ?: date('Y-m-d');
                $http_url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/enddaybalance/' . rawurlencode((string) $sub_mchid) . '?date=' . urlencode($date);
                break;
            default:
                throw new WxPayv3Exception('请求路径错误');
        }
        return Signs::_Getresponse($http_url);
    }

    public function tradeBill($filepath, $bill_date, $sub_mchid = '', $bill_type = 'ALL', $tar_type = '')
    {
        $url = 'https://api.mch.weixin.qq.com/v3/bill/tradebill?bill_date=' . urlencode((string) $bill_date);
        if ($sub_mchid !== '') {
            $url .= '&sub_mchid=' . rawurlencode((string) $sub_mchid);
        }
        $url .= '&bill_type=' . urlencode((string) $bill_type);
        if ($tar_type !== '') {
            $url .= '&tar_type=' . urlencode((string) $tar_type);
        }
        $ret = json_decode(Signs::_Getresponse($url), true);
        if (!is_array($ret) || isset($ret['code'])) {
            return $ret;
        }
        $down = $this->saveBillFile($ret, '交易账单_' . $bill_type, $filepath);
        if ($down) {
            return $down;
        }
        throw new WxPayv3Exception('下载出错');
    }

    private function saveBillFile($data, $name, $filepath)
    {
        $url = $data['download_url'] ?? '';
        if ($url === '') {
            return false;
        }
        $ret = Signs::_Getresponse($url);
        $file = $name . '_' . date('YmdHIs') . '.xlsx';
        if (file_put_contents(rtrim($filepath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file, $ret) === false) {
            return false;
        }
        return $file;
    }

    public function fundBill($filepath, $bill_date, $account_type = 'BASIC', $tar_type = '')
    {
        $url = 'https://api.mch.weixin.qq.com/v3/bill/fundflowbill?bill_date=' . urlencode((string) $bill_date);
        if ($account_type !== '') {
            $url .= '&account_type=' . urlencode((string) $account_type);
        }
        if ($tar_type !== '') {
            $url .= '&tar_type=' . urlencode((string) $tar_type);
        }
        $ret = json_decode(Signs::_Getresponse($url), true);
        if (!is_array($ret) || isset($ret['code'])) {
            return $ret;
        }
        $down = $this->saveBillFile($ret, '资金账单_' . $account_type, $filepath);
        if ($down) {
            return $down;
        }
        throw new WxPayv3Exception('下载出错');
    }
}
