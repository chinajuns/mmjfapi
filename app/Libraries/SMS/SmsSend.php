<?php
class SmsSend
{
    private $error_code_msg = [
        '100' => '发送成功',
        '101' => '验证失败',
        '102' => '短信不足',
        '103' => '操作失败',
        '104' => '非法字符',
        '105' => '内容过多',
        '106' => '号码过多',
        '107' => '频率过快',
        '108' => '号码内容空',
        '109' => '账号冻结',
        '110' => '禁止频繁单条发送',
        '111' => '系统暂定发送',
        '112' => '号码不正确',
        '113' => '定时时间格式不对',
        '114' => '账号被锁，10分钟后登录',
        '115' => '连接失败',
        '116' => '禁止接口发送',
        '117' => '绑定IP不正确',
        '120' => '系统升级',
    ];

    private $error_code_msg_bz = [
        '1' => '操作成功',
        '0' => '帐户格式不正确(正确的格式为:员工编号@企业编号)',
        '-1' => '服务器拒绝(速度过快、限时或绑定IP不对等)如遇速度过快可延时再发',
        '-2' => '密钥不正确',
        '-3' => '密钥已锁定',
        '-4' => '参数不正确(内容和号码不能为空，手机号码数过多，发送时间错误等)',
        '-5' => '无此帐户',
        '-6' => '帐户已锁定或已过期',
        '-7' => '帐户未开启接口发送',
        '-8' => '不可使用该通道组',
        '-9' => '帐户余额不足',
        '-10' => '内部错误',
        '-11' => '扣费失败',
    ];

    /**
     * @param $uid
     * @param $pwd
     * @param $mobile
     * @param $content
     * @param string $encode
     * @param string $time
     * @param string $mid
     * @return bool
     * @throws Exception
     */
    function send($uid, $pwd, $mobile, $content, $encode = 'utf8', $time = '', $mid = '')
    {
        if (is_array($mobile)) {
            $mobile = join(',', $mobile);
        }
        $uri = "http://dxhttp.c123.cn/tx/";
        $data = [
            'uid' => $uid,
            'pwd' => $pwd,
            'mobile' => $mobile,
            'content' => $content,
            'encode' => $encode
        ];
        if ($time) {
            $data['time'] = $time;
        }
        if ($mid) {
            $data['mid'] = $mid;
        }
        list($result, $code) = $this->requestPost($uri, $data);
        if (!$result) {
            throw new Exception($code);
        }
        if (empty($code)) {
            throw new Exception('接口返回失败');
        }
        if ($code != 100) {
            throw new Exception($this->error_code_msg[$code]);
        }
        return true;
    }

    /**
     * @param $ac
     * @param $authkey
     * @param $mobile
     * @param $content
     * @param string $action
     * @param string $cgid
     * @param string $csid
     * @param string $time
     * @return bool
     * @throws Exception
     */
    function sendBZ($ac, $authkey, $mobile, $content, $action = 'sendOnce', $cgid = '9195', $csid = '', $time = '')
    {
        if (is_array($mobile)) {
            $mobile = join(',', $mobile);
        }
        $uri = "http://smsapi.c123.cn/OpenPlatform/OpenApi";
        $data = [
            'ac' => $ac,
            'authkey' => $authkey,
            'cgid' => $cgid,
            'c' => $content,
            'm' => $mobile,
            'action' => $action
        ];
        if ($csid) {
            $data['csid'] = $csid;
        }
        if ($time) {
            $data['t'] = $time;
        }
        $query = http_build_query($data);
        list($result, $xml) = $this->requestGet($uri . '?' . $query);
        if (!$result) {
            throw new Exception($xml);
        }
        if (empty($xml)) {
            throw new Exception('接口返回失败');
        }
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if (trim($array_data['@attributes']['result']) != 1) {
            throw new Exception($this->error_code_msg_bz[trim($array_data['@attributes']['result'])]);
        }
        return true;
    }

    /**
     * @param $uri
     * @param $data
     * @return array
     */
    function requestPost($uri, $data)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uri);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $return = curl_exec($ch);
            curl_close($ch);
            return [true, $return];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * @param $uri
     * @return array
     */
    function requestGet($uri)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $return = curl_exec($ch);
            curl_close($ch);
            return [true, $return];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }
}