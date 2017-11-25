<?php
 
namespace Slimkit\PlusLive\API\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Slimkit\PlusLive\Models\LiveUserInfo;
use Zhiyi\Plus\Http\Controllers\Controller;


class BaseController extends Controller 
{
    protected $setting;

    // 构造方法
    public function __construct()
    {
        $this->setting = config('live');
    }

    public function isUrl ($urs = '') 
    {
        return preg_match('/^http(s)?:\/\/.+/', $url) ? true : false;
    }

    // 获取直播服务器
    public function getStreamServerUrl () 
    {
        if (! $this->isUrl($this->setting['stream_server'])) {
            return '';
        }

        return $this->setting['stream_server'];
    }

    // 检测直播服务器地址
    public function checkStreamServiceUrl () 
    {
        if (!$this->getStreamServerUrl()) {
            return false;
        }

        return true;
    }

    // 判断是否是合法请求
    public function is_ZhiboService (Request $request)
    {
        if ($this->setting['header']['Auth-Appid'] === $request->header('HTTP_AUTH_APPID')) {
            return true;
        }

        return false;
    }

        //加密函数
    protected function jiami($txt, $key = null)
    {
        if (empty($key)) {
            $key = C('SECURE_CODE');
        }
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_';
        $nh = rand(0, 64);
        $ch = $chars[$nh];
        $mdKey = md5($key.$ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = base64_encode($txt);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = ($nh + strpos($chars, $txt[$i]) + ord($mdKey[$k++])) % 64;
            $tmp .= $chars[$j];
        }

        return $ch.$tmp;
    }

    //解密函数
    protected function jiemi($txt, $key = null)
    {
        if (empty($key)) {
            $key = C('SECURE_CODE');
        }
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_';
        $ch = $txt[0];
        $nh = strpos($chars, $ch);
        $mdKey = md5($key.$ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = substr($txt, 1);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = strpos($chars, $txt[$i]) - $nh - ord($mdKey[$k++]);
            while ($j < 0) {
                $j += 64;
            }
            $tmp .= $chars[$j];
        }

        return base64_decode($tmp);
    }

    public function registerOther($data = [], $config = [])
    {   
        dd($config);
        $model = new LiveUserInfo();
        $stream_server = $config['stream_server'] ?: '';
        $Service_User_Url = $stream_server . '/Users';
        $usid_prex = $config['usid_prex'] ?: '';
        $curl_header = $config['curl_header'] ?: '';
        $data['usid'] = $usid_prex.$data['id'];
        $client = new Client();
        dd($curl_header);
        $response = $client->request('post', $Service_User_Url, ['form_params' => $data, 'headers' => $curl_header]);

        $response = json_decode(($response->getBody()->getContents()), true);

        if ($response['code'] === 1) {
            $model->uid = $data['id'];
            $model->usid = $usid_prex . $data['id'];
            $model->sex = $data['sex'];
            $model->uname = $data['uname'];
            $model->ticket = $response['data']['ticket'];

            $model->save();

            return $model;
        }

        return false;
    }

}