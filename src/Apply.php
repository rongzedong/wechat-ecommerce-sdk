<?php

namespace jiujiude\WechatEcommerce;

/** 商户进件与结算账户操作。 */
class Apply
{
    public function applyment($param = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/applyments/';
        $fields = [
            ['id_card_info', 'id_card_name'],
            ['id_card_info', 'id_card_number'],
            ['id_card_info', 'id_card_address'],
            ['account_info', 'account_number'],
            ['account_info', 'account_name'],
            ['contact_info', 'contact_name'],
            ['contact_info', 'contact_id_card_number'],
            ['contact_info', 'mobile_phone'],
            ['contact_info', 'contact_email'],
        ];
        foreach ($fields as $fieldPath) {
            if (isset($param[$fieldPath[0]][$fieldPath[1]]) && $param[$fieldPath[0]][$fieldPath[1]] !== '') {
                $param[$fieldPath[0]][$fieldPath[1]] = Signs::getEncrypt($param[$fieldPath[0]][$fieldPath[1]]);
            }
        }

        return Signs::_Postresponse($url, json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function getApplymentByid($applyment_id)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/applyments/' . rawurlencode((string) $applyment_id);
        $data = json_decode(Signs::_Getresponse($url), true);
        if (is_array($data)) {
            foreach (['account_name', 'account_no'] as $field) {
                if (isset($data['account_validation'][$field])) {
                    $data['account_validation'][$field] = Signs::getPrivateEncrypt($data['account_validation'][$field]);
                }
            }
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getApplymentByno($out_request_no)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/applyments/out-request-no/' . rawurlencode((string) $out_request_no);
        $data = json_decode(Signs::_Getresponse($url), true);
        if (is_array($data)) {
            foreach (['account_name', 'account_no'] as $field) {
                if (isset($data['account_validation'][$field])) {
                    $data['account_validation'][$field] = Signs::getPrivateEncrypt($data['account_validation'][$field]);
                }
            }
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function modifySettlement($param = [])
    {
        if (empty($param['sub_mchid']) || strlen((string) $param['sub_mchid']) < 8) {
            throw new WxPayv3Exception('特约商户号:长度最小8个字节');
        }
        $url = 'https://api.mch.weixin.qq.com/v3/apply4sub/sub_merchants/' . rawurlencode((string) $param['sub_mchid']) . '/modify-settlement';
        if (!empty($param['account_number'])) {
            $param['account_number'] = Signs::getEncrypt($param['account_number']);
        }
        $data = [
            'account_type' => $param['account_type'] ?? '',
            'account_bank' => $param['account_bank'] ?? '',
            'bank_address_code' => $param['bank_address_code'] ?? '',
            'account_number' => $param['account_number'] ?? '',
        ];
        foreach (['bank_name', 'bank_branch_id'] as $optional) {
            if (!empty($param[$optional])) {
                $data[$optional] = $param[$optional];
            }
        }
        return Signs::_Postresponse($url, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function getSettlement($sub_mchid)
    {
        if (strlen((string) $sub_mchid) < 8) {
            throw new WxPayv3Exception('特约商户号:长度最小8个字节');
        }
        $url = 'https://api.mch.weixin.qq.com/v3/apply4sub/sub_merchants/' . rawurlencode((string) $sub_mchid) . '/settlement';
        return Signs::_Getresponse($url);
    }

    public function applymentTY($param = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/applyment4sub/applyment/';
        $paths = [
            ['subject_info', 'identity_info', 'id_card_info', 'id_card_name'],
            ['subject_info', 'identity_info', 'id_card_info', 'id_card_number'],
            ['bank_account_info', 'account_name'],
            ['bank_account_info', 'account_number'],
            ['contact_info', 'contact_name'],
            ['contact_info', 'contact_id_number'],
            ['contact_info', 'openid'],
            ['contact_info', 'mobile_phone'],
            ['contact_info', 'contact_email'],
        ];
        foreach ($paths as $path) {
            $value =& $param;
            $exists = true;
            foreach ($path as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    $exists = false;
                    break;
                }
                $value =& $value[$key];
            }
            if ($exists && $value !== '') {
                $value = Signs::getEncrypt($value);
            }
            unset($value);
        }
        return Signs::_Postresponse($url, json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function getApplymentByidTY($applyment_id)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/applyment4sub/applyment/applyment_id/' . rawurlencode((string) $applyment_id);
        return Signs::_Getresponse($url);
    }

    public function getApplymentBynoTY($business_code)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/applyment4sub/applyment/business_code/' . rawurlencode((string) $business_code);
        return Signs::_Getresponse($url);
    }

    public function modifySettlementTY($post = [])
    {
        $subMchid = $post['sub_mchid'] ?? '';
        if ($subMchid === '') {
            throw new WxPayv3Exception('sub_mchid不能为空');
        }
        $url = 'https://api.mch.weixin.qq.com/v3/apply4sub/sub_merchants/' . rawurlencode((string) $subMchid) . '/modify-settlement';
        $data = [
            'account_type' => $post['account_type'] ?? '',
            'account_bank' => $post['account_bank'] ?? '',
            'bank_address_code' => $post['bank_address_code'] ?? '',
            'bank_name' => $post['bank_name'] ?? '',
        ];
        if (isset($post['account_number']) && $post['account_number'] !== '') {
            $data['account_number'] = Signs::getEncrypt($post['account_number']);
        }
        if (!empty($post['bank_branch_id'])) {
            $data['bank_branch_id'] = $post['bank_branch_id'];
        }
        return Signs::_Postresponse($url, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function findSettlementTY($sub_mchid)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/apply4sub/sub_merchants/' . rawurlencode((string) $sub_mchid) . '/settlement';
        return Signs::_Getresponse($url);
    }
}
