<?php

namespace jiujiude\WechatEcommerce;

class Apply
{
    public function applyment($param = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/applyments/' ;
        if (isset($param['id_card_info']['id_card_name'])) {
            $param['id_card_info']['id_card_name'] = Signs::getEncrypt($param['id_card_info']['id_card_name']);
        }
        if (isset($param['id_card_info']['id_card_number'])) {
            $param['id_card_info']['id_card_number'] = Signs::getEncrypt($param['id_card_info']['id_card_number']);
        }
        if (isset($param['id_card_info']['id_card_address'])) {
            $param['id_card_info']['id_card_address'] = Signs::getEncrypt($param['id_card_info']['id_card_address']);
        }
        if (isset($param['account_info']['account_number'])) {
            $param['account_info']['account_number'] = Signs::getEncrypt($param['account_info']['account_number']);
        }
        if (isset($param['account_info']['account_name'])) {
            $param['account_info']['account_name'] = Signs::getEncrypt($param['account_info']['account_name']);
        }
        if (isset($param['contact_info']['contact_name'])) {
            $param['contact_info']['contact_name'] = Signs::getEncrypt($param['contact_info']['contact_name']);
        }
        if (isset($param['contact_info']['contact_id_card_number'])) {
            $param['contact_info']['contact_id_card_number'] = Signs::getEncrypt($param['contact_info']['contact_id_card_number']);
        }
        if (isset($param['contact_info']['mobile_phone'])) {
            $param['contact_info']['mobile_phone'] = Signs::getEncrypt($param['contact_info']['mobile_phone']);
        }
        if (isset($param['contact_info']['contact_email'])) {
            $param['contact_info']['contact_email'] = Signs::getEncrypt($param['contact_info']['contact_email']);
        }

        return Signs::_Postresponse($url, json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function getApplymentByid($applyment_id)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/applyments/' . rawurlencode((string) $applyment_id);
        $ret = Signs::_Getresponse($url);
        $data = json_decode($ret, true);
        if (is_array($data)) {
            if (isset($data['account_validation']['account_name'])) {
                $data['account_validation']['account_name'] = Signs::getPrivateEncrypt($data['account_validation']['account_name']);
            }
            if (isset($data['account_validation']['account_no'])) {
                $data['account_validation']['account_no'] = Signs::getPrivateEncrypt($data['account_validation']['account_no']);
            }
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getApplymentByno($out_request_no)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/ecommerce/applyments/out-request-no/' . rawurlencode((string) $out_request_no);
        $ret = Signs::_Getresponse($url);
        $data = json_decode($ret, true);
        if (is_array($data)) {
            if (isset($data['account_validation']['account_name'])) {
                $data['account_validation']['account_name'] = Signs::getPrivateEncrypt($data['account_validation']['account_name']);
            }
            if (isset($data['account_validation']['account_no'])) {
                $data['account_validation']['account_no'] = Signs::getPrivateEncrypt($data['account_validation']['account_no']);
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
            'bank_name' => $param['bank_name'] ?? '',
            'bank_branch_id' => $param['bank_branch_id'] ?? '',
            'account_number' => $param['account_number'] ?? '',
        ];
        foreach (['bank_name', 'bank_branch_id'] as $key) {
            if (empty($data[$key])) {
                unset($data[$key]);
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

        if (!empty($param['subject_info']['identity_info']['id_card_info']['id_card_name'])) {
            $param['subject_info']['identity_info']['id_card_info']['id_card_name'] = Signs::getEncrypt($param['subject_info']['identity_info']['id_card_info']['id_card_name']);
        }
        if (!empty($param['subject_info']['identity_info']['id_card_info']['id_card_number'])) {
            $param['subject_info']['identity_info']['id_card_info']['id_card_number'] = Signs::getEncrypt($param['subject_info']['identity_info']['id_card_info']['id_card_number']);
        }
        if (!empty($param['bank_account_info']['account_name'])) {
            $param['bank_account_info']['account_name'] = Signs::getEncrypt($param['bank_account_info']['account_name']);
        }
        if (!empty($param['bank_account_info']['account_number'])) {
            $param['bank_account_info']['account_number'] = Signs::getEncrypt($param['bank_account_info']['account_number']);
        }
        if (!empty($param['contact_info']['contact_name'])) {
            $param['contact_info']['contact_name'] = Signs::getEncrypt($param['contact_info']['contact_name']);
        }
        if (!empty($param['contact_info']['contact_id_number'])) {
            $param['contact_info']['contact_id_number'] = Signs::getEncrypt($param['contact_info']['contact_id_number']);
        }
        if (!empty($param['contact_info']['openid'])) {
            $param['contact_info']['openid'] = Signs::getEncrypt($param['contact_info']['openid']);
        }
        if (!empty($param['contact_info']['mobile_phone'])) {
            $param['contact_info']['mobile_phone'] = Signs::getEncrypt($param['contact_info']['mobile_phone']);
        }
        if (!empty($param['contact_info']['contact_email'])) {
            $param['contact_info']['contact_email'] = Signs::getEncrypt($param['contact_info']['contact_email']);
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
        $url = 'https://api.mch.weixin.qq.com/v3/apply4sub/sub_merchants/' . rawurlencode((string) $post['sub_mchid']) . '/modify-settlement';
        $data = [
            'account_type' => $post['account_type'],
            'account_bank' => $post['account_bank'],
            'bank_address_code' => $post['bank_address_code'],
            'bank_name' => $post['bank_name'],
            'account_number' => Signs::getEncrypt($post['account_number']),
        ];
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
