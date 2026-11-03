<?php

namespace jiujiude\WechatEcommerce;

class Banks
{
    public function searchBanks($account_number)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/search-banks-by-bank-account';
        $url .= '?account_number=' . urlencode(Signs::getEncrypt($account_number));
        return json_decode(Signs::_Getresponse($url), true);
    }

    public function provinces()
    {
        return json_decode(Signs::_Getresponse('https://api.mch.weixin.qq.com/v3/capital/capitallhh/areas/provinces'), true);
    }

    public function cities($province_code)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/areas/provinces/' . rawurlencode((string) $province_code) . '/cities';
        return json_decode(Signs::_Getresponse($url), true);
    }

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

    public function personalBank()
    {
        return $this->fetchBankPages('personal-banking');
    }

    public function corporateBank()
    {
        return $this->fetchBankPages('corporate-banking');
    }

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
            // Avoid following an arbitrary host returned in an API response.
            if (strpos($next, '/') !== 0 || strpos($next, '//') === 0) {
                throw new WxPayv3Exception('银行列表分页链接格式错误');
            }
            $url = 'https://api.mch.weixin.qq.com' . $next;
        }
        return $banks;
    }
}
