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

    public function isUrl ($url = '')
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
        if ($this->setting['header']['Auth-Appid'] === $request->header('Auth_Appid')) {
            return true;
        }

        return false;
    }

        //加密函数
    protected function jiami($txt, $key = null)
    {
        if (empty($key)) {
            $key = $this->setting['secure_code'];
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
            $key = $this->setting['secure_code'];
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
        $model = new LiveUserInfo();
        $stream_server = $config['stream_server'] ?: '';
        $Service_User_Url = $stream_server . '/Users';
        $usid_prex = $config['usid_prex'] ?: '';
        $curl_header = $config['curl_header'] ?: '';
        $data['usid'] = $usid_prex.$data['id'];
        $client = new Client();

        $response = $client->request('post', $Service_User_Url, ['form_params' => $data, 'headers' => $curl_header]);

        $response = json_decode(($response->getBody()->getContents()), true);

        if ($response['code'] === 1) {
            $model->uid = $data['id'];
            $model->usid = $usid_prex . $data['id'];
            $model->sex = $data['sex'];
            $model->uname = $data['uname'];
            $model->ticket = $response['data']['ticket'];

            $model->save();
            $this->_notifyLiveServer($model->usid, $config);
            return $model;
        }

        return false;
    }

    /**
     * 通知直播服务器需要更新
     * 只做发送请求，不做回调说明
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function notifyLiveServer (Request $request, $config = [])
    {
        $user = $request->user('api');
        $model = new LiveUserInfo();

        $usid = $model->where('uid', $user->id)->value('usid');

        $data = ['usid' => $usid];

        // 通知地址
        $notify_url = $config['stream_server'] . '/users/syncNotify';

        $client = new Client();

        $client->request('post', $notify_url, ['form_params' => $data, 'headers' => $config['curl_header']]);
    }

    public function _notifyLiveServer ( string $usid, $config = [])
    {
        $data = [ 'usid' => $usid];

        // 通知地址
        $notify_url = $config['stream_server'] . '/users/syncNotify';

        $client = new Client();

        $client->request('post', $notify_url, ['form_params' => $data, 'headers' => $config['curl_header']]);
    }
}