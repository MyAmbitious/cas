<?php
/**
 *============================
 * author:Farmer
 * time:2018/3/30
 * blog:blog.icodef.com
 * function:
 *============================
 */


namespace app\common\api;


use app\common\BasePlatform;
use app\common\Encrypt;
use app\common\PlatformLogin;
use icf\lib\other\http;

class ChinaUnicomPlatform extends BasePlatform implements PlatformLogin {

    public function __construct($param) {
        parent::__construct($param);
        $this->httpRequest = new http();
        $this->httpRequest->https();
        $this->httpRequest->setCookie($this->cookie);
    }

    public function VerifyAccount() {
        // TODO: Implement VerifyAccount() method.
        $u = getStrMid($this->cookie, 'u_account=', ';');
        $data = 'showType=3&version=android%405.62&desmobile=' . urlencode($u);
        $data = $this->httpRequest->post('https://m.client.10010.com/mobileService/home/queryUserInfoFive.htm', $data);
        $data = json_decode($data, true);
        if (isset($data['data'])) {
            return $u;
        }
        return false;
    }

    private $resCookie;

    public function VerifyAction($action) {
        // TODO: Implement VerifyAction() method.
        switch ($action) {
            case 'SignLT':
                $this->httpRequest->responseHeader();
                $this->httpRequest->setRedirection(0);
                $this->httpRequest->get('http://m.client.10010.com/mobileService//thirdRedirect.htm?redirect_uri=https://act.10010.com/SigninApp/signin/querySigninActivity.htm&version=android@5.62&desmobile=' . $this->platAccount['pu_u']);
                $location = getStrMid($this->httpRequest->getResponseHeader(), 'Location: ', "\r\n");
                if ($location == '') {
                    return false;
                }
                $this->httpRequest->get($location);
                $this->resCookie = $this->httpRequest->getCookie();
                return true;
        }
        return false;
    }

    public function VerifyActionResult($actionRet) {
        // TODO: Implement VerifyActionResult() method.
        if (isset($actionRet['msgCode'])) {
            if ($actionRet['msgCode'] == '0008') {
                return 1;
            }
            return 0;
        }
        return 2;
    }

    public function SignLT($actMsg) {
        $this->httpRequest->setCookie($this->resCookie);
        $this->httpRequest->post("https://act.10010.com/SigninApp/signin/daySign.do", "className=btnPouplePost");
        $data = $this->httpRequest->data();
        return json_decode($data, true);
    }

    public function Login($u, $p, &$cookie) {
        // TODO: Implement Login() method.
        if (strpos($u, '@') !== false) {
            $u .= getRandString(6, 0);
        }

        $key = <<<EOF
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDc+CZK9bBA9IU+gZUOc6FUGu7yO9WpTNB0Pzmg
FBh96Mg1WrovD1oqZ+eIF4LjvxKXGOdI79JRdve9NPhQo07+uqGQgE4imwNnRx7PFtCRryiIEcUo
avuNtuRVoBAm6qdB0SrctgaqGfLgKvZHOnwTjyNqjBUxzMeQlEC2czEMSwIDAQAB
-----END PUBLIC KEY-----
EOF;
        $pubkey = openssl_pkey_get_public($key);
        $mobile = Encrypt::lt_encrypt($u, $pubkey);
        $pwd = Encrypt::lt_encrypt($p, $pubkey);
        $timestamp = fillZero(time() - strtotime(date('Y/m/d 0:0:0')), 6);
        $timestamp = date('Ymd') . $timestamp;
        $data = 'deviceOS=android7.1.1&mobile=' . urlencode($mobile) . '&netWay=WIFI&deviceCode=&isRemberPwd=true&version=' .
            'android%405.62&deviceId=&password=' . urlencode($pwd) . '&keyVersion=&pip=192.168.232.2&provinceChanel=general&appId=ChinaunicomMobileBusiness&deviceModel=Android+SDK+built+for+x86&deviceBrand=Google' .
            '&timestamp=' . $timestamp;
        $this->httpRequest->setHeader([
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: xxxx',
            'Cookie2: $Version=1',
            'Connection: Keep-Alive'
        ]);
        $tmpCookie = 'c_sfbm=234g_00; mobileServiceOther=' . md5(time()) . '; mobileService=' . getRandString(52) . '!' . getRandString(10, 0);
        $this->httpRequest->setCookie($tmpCookie);
        $this->httpRequest->setopt(CURLOPT_HEADER, true);
        $ret = $this->httpRequest->post('https://m.client.10010.com/mobileService/login.htm', $data);
        preg_match_all('/Set-Cookie:(.*);/iU', $ret, $matchCookie);
        foreach ($matchCookie[1] as $value) {
            $cookie .= $value . ';';
        }
        $ret = json_decode(substr($ret, strpos($ret, '{"code"')), true);
        if ($ret['code'] == 0 && $ret['dsc'] == '') {
            return true;
        }
        return $ret['dsc'];
    }

}