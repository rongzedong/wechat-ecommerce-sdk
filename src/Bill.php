<?php

namespace jiujiude\WechatEcommerce;

class Banks
{
    public function searchBanks($account_number)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/search-banks-by-bank-account';
        $url .= '?account_number=' . urlencode(Signs::getEncrypt($account_number));
        $ret = Signs::_Getresponse($url);
        return json_decode($ret, true);
    }

    public function provinces()
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/areas/provinces';
        $ret = Signs::_Getresponse($url);
        return json_decode($ret, true);
    }

    public function cities($province_code)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/areas/provinces/' . rawurlencode((string) $province_code) . '/cities';
        $ret = Signs::_Getresponse($url);
        return json_decode($ret, true);
    }

    public function branches($bank_alias_code, $city_code)
    {
        $url = "https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/{$bank_alias_code}/branches?city_code={$city_code}&offset=0&limit=200";
        $ret = Signs::_Getresponse($url);
        return json_decode($ret, true);
    }

    public function personalBank()
    {
        $bank_list = [];
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/personal-banking?offset=0&limit=200';
        while (true) {
            $data = json_decode(Signs::_Getresponse($url), true);
            if (!is_array($data) || !isset($data['data'])) {
                break;
            }
            $bank_list = array_merge($bank_list, $data['data']);
            $next_url = $data['links']['next'] ?? '';
            if ($next_url === '') {
                break;
            }
            $url = 'https://api.mch.weixin.qq.com' . $next_url;
        }
        return $bank_list;
    }

    public function corporateBank()
    {
        $bank_list = [];
        $url = 'https://api.mch.weixin.qq.com/v3/capital/capitallhh/banks/corporate-banking?offset=0&limit=200';
        while (true) {
            $data = json_decode(Signs::_Getresponse($url), true);
            if (!is_array($data) || !isset($data['data'])) {
                break;
            }
            $bank_list = array_merge($bank_list, $data['data']);
            $next_url = $data['links']['next'] ?? '';
            if ($next_url === '') {
                break;
            }
            $url = 'https://api.mch.weixin.qq.com' . $next_url;
        }
        return $bank_list;
    }
}
