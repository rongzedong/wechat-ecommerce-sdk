<?php

namespace jiujiude\WechatEcommerce;

class Bill
{
    public function getComBalance($fund_type = 1, $account_type = 'BASIC', $date = '')
    {
        if (!in_array($account_type, ['BASIC', 'OPERATION', 'FEES'], true)) {
            throw new WxPayv3Exception('查询的账户类型不存在');
        }
        if ($fund_type === 1 || $fund_type === '1') {
            $url = 'https://api.mch.weixin.qq.com/v3/merchant/fund/balance/' . rawurlencode((string) $account_type);
        } elseif ($fund_type === 2 || $fund_type === '2') {
            $date = $date ?: date('Y-m-d');
            $url = 'https://api.mch.weixin.qq.com/v3/merchant/fund/dayendbalance/' . rawurlencode((string) $account_type) . '?date=' . urlencode($date);
        } else {
            throw new WxPayv3Exception('请求路径错误');
        }
        return Signs::_Getresponse($url);
    }

    public function getShopBalance($sub_mchid, $fund_type = 1, $date = '')
    {
        if ($sub_mchid === '' || $sub_mchid === null) {
            throw new WxPayv3Exception('查询的二级商户不能为空');
        }
        $subMchid = rawurlencode((string) $sub_mchid);
        if ($fund_type === 1 || $fund_type === '1') {
            $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/balance/' . $subMchid;
        } elseif ($fund_type === 2 || $fund_type === '2') {
            $date = $date ?: date('Y-m-d');
            $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/fund/enddaybalance/' . $subMchid . '?date=' . urlencode($date);
        } else {
            throw new WxPayv3Exception('请求路径错误');
        }
        return Signs::_Getresponse($url);
    }

    public function tradeBill($filepath, $bill_date, $sub_mchid = '', $bill_type = 'ALL', $tar_type = '')
    {
        $query = [
            'bill_date' => (string) $bill_date,
            'bill_type' => (string) $bill_type,
        ];
        if ($sub_mchid !== '') {
            $query['sub_mchid'] = (string) $sub_mchid;
        }
        if ($tar_type !== '') {
            $query['tar_type'] = (string) $tar_type;
        }
        $url = 'https://api.mch.weixin.qq.com/v3/bill/tradebill?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $ret = json_decode(Signs::_Getresponse($url), true);
        if (!is_array($ret) || isset($ret['code'])) {
            return $ret;
        }
        $file = $this->saveBillFile($ret, '交易账单_' . $bill_type, $filepath);
        if ($file === false) {
            throw new WxPayv3Exception('交易账单下载出错');
        }
        return $file;
    }

    private function saveBillFile($data, $name, $filepath)
    {
        if (empty($data['download_url'])) {
            return false;
        }
        $content = Signs::_Getresponse($data['download_url']);
        $file = $name . '_' . date('YmdHis') . '.xlsx';
        $directory = rtrim($filepath, '/\\');
        if ($directory === '') {
            $directory = '.';
        }
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new WxPayv3Exception('账单保存目录不可写：' . $directory);
        }
        $target = $directory . DIRECTORY_SEPARATOR . $file;
        if (file_put_contents($target, $content, LOCK_EX) === false) {
            return false;
        }
        // Verify the downloaded bill against the API response digest when present.
        if (!empty($data[' hash_type']) && !empty($data['hash_value'])) {
            $algorithm = strtolower((string) $data[' hash_type']);
            if (in_array($algorithm, hash_algos(), true) && hash_file($algorithm, $target) !== strtolower((string) $data['hash_value'])) {
                @unlink($target);
                throw new WxPayv3Exception('账单文件校验失败');
            }
        }
        return $file;
    }

    public function fundBill($filepath, $bill_date, $account_type = 'BASIC', $tar_type = '')
    {
        $query = ['bill_date' => (string) $bill_date];
        if ($account_type !== '') {
            $query['account_type'] = (string) $account_type;
        }
        if ($tar_type !== '') {
            $query['tar_type'] = (string) $tar_type;
        }
        $url = 'https://api.mch.weixin.qq.com/v3/bill/fundflowbill?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $ret = json_decode(Signs::_Getresponse($url), true);
        if (!is_array($ret) || isset($ret['code'])) {
            return $ret;
        }
        $file = $this->saveBillFile($ret, '资金账单_' . $account_type, $filepath);
        if ($file === false) {
            throw new WxPayv3Exception('资金账单下载出错');
        }
        return $file;
    }
}
