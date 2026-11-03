<?php

namespace jiujiude\WechatEcommerce;

/** 查询开户银行、地区及支行信息。 */
class Banks
{
    /**
     * 根据银行卡号查询开户银行信息。
     * 银行卡号会按 APIv3 要求加密后提交。
     * @param string $account_number 银行卡号
     * @return array 银行详情数组
     */
    public function searchBanks($account_number)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/search-banks-by-bank-account';
        $url .= '?account_number=' . urlencode(Signs::getEncrypt($account_number));
        return json_decode(Signs::_Getresponse($url), true);
    }

    /** 获取省份列表。 */
    public function provinces()
    {
        return json_decode(Signs::_Getresponse('https://api.mch.weixin.qq.com/v3/capital/capitallhh/areas/provinces'), true);
    }

    /**
     * 根据省份编码获取城市列表。
     * @param string $province_code 省份编码
     * @return array 城市列表
     */
    public function cities($province_code)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/areas/provinces/' . rawurlencode((string) $province_code) . '/cities';
        return json_decode(Signs::_Getresponse($url), true);
    }

    /**
     * 查询某银行在指定城市中的支行列表。
     * @param string $bank_alias_code 银行别名编码
     * @param string $city_code 城市编码
     * @param int $offset 分页偏移量
     * @param int $limit 每页记录数
     * @return array 支行列表
     */
    public function branches($bank_alias_code, $city_code, $offset = 0, $limit = 200)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/' . rawurlencode((string) $bank_alias_code) . '/branches?'
            . http_build_query([
                'city_code' => (string) $city_code,
                'offset' => (int) $offset,
                'limit' => (int) $limit,
            ], '', '&', PHP_QUERY_RFC3986);
        return json_decode(Signs::_Getresponse($url), true);
    }

    /** 获取支持个人业务的银行列表。 */
    public function personalBank()
    {
        return $this->fetchBankPages('personal-banking');
    }

    /** 获取支持对公业务的银行列表。 */
    public function corporateBank()
    {
        return $this->fetchBankPages('corporate-banking');
    }

    /**
     * 分页拉取银行列表。
     * @param string $endpoint 银行列表接口路径
     * @return array 银行列表
     */
    private function fetchBankPages($endpoint)
    {
        $banks = [];
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/' . $endpoint . '?offset=0&limit=200';
        while ($url !== '') {
            $data = json_decode(Signs::_Getresponse($url), true);
            if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
                throw new WxPayv3Exception('银行列表响应格式错误');
            }
            $banks = array_merge($banks, $data['data']);
            $next = $data['links']['next'] ?? '';
            if ($next === '') {
                break;
            }
            // 仅跟随微信支付相对路径分页，避免访问接口返回中的任意主机。
            if (strpos($next, '/') !== 0 || strpos($next, '//') === 0) {
                throw new WxPayv3Exception('银行列表分页链接格式错误');
            }
            $url = 'https://api.mch.weixin.qq.com' . $next;
        }
        return $banks;
    }
}
