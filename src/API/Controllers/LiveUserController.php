<?php

namespace Slimkit\PlusLive\API\Controllers;

use GuzzleHttp\Client;
use Zhiyi\Plus\Models\User;
use Illuminate\Http\Request;
use Zhiyi\Plus\Models\UserExtra;
use Zhiyi\Plus\Jobs\PushMessage;
use GuzzleHttp\Psr7\Request as GRequest;
use Slimkit\PlusLive\Models\LiveUserInfo;

class LiveUserController extends BaseController
{
    protected $setting;

    public function __construct()
    {
        $this->setting = config('live', []);
    }

    /**
     * 注册直播服务端账户
     * @param  Request
     * @param  LiveUserInfo
     * @return [type]
     */
    public function registerUser (Request $request, LiveUserInfo $model)
    {
        $user = $request->user();
        $ticket = $request->input('ticket', '');
        $stream_server = $this->setting['stream_server'] ?? '';
        $usid_prex = $this->setting['usid_prex'] ?? '';
        $curl_header = $this->setting['curl_header'] ?? '';
        

        if (!$stream_server || !$usid_prex) {
            return response()->json(['message' => '请先配置直播设置']);
        }
        $Service_User_Url = $stream_server . '/Users';
        $data = [];
        $data['usid'] = $usid_prex . $user->id;
        $data['sex'] = $user->sex;
        $data['uname'] = $user->name;

        $client = new Client();
        $response = $client->request('post', $Service_User_Url, ['form_params' => $data, 'headers' => $curl_header]);

        $response = json_decode(($response->getBody()->getContents()), true);
        $model = $ticket ? $model->where('usid', $usid_prex . $user->id)->first() : $model;
        if ($response['code'] === 1) {
            if (!$ticket) {
                $model->uid = $user->id;
                $model->usid = $usid_prex . $user->id;
                $model->sex = $user->sex;
                $model->uname = $user->name;
            }
            
            $model->ticket = $response['data']['ticket'];

            $model->save();

            return response()->json($model)->setStatusCode(201);
        } else {

            return response()->json(['message' => '注册直播用户失败'])->setStatusCode(500);
        }
        
    }

    /**
     * @param  Request
     * @param  string usid
     * @param  LiveUserInfo
     * @return [mix]
     */
    public function getUserData (Request $request, string $usid, LiveUserInfo $liveUser)
    {
        if (!$this->is_ZhiBoService($request)) {
            return response()->json(['message' => '授权错误'])->setStatusCode(401);
        }

        $liveUser = $liveUser->where('usid', $usid)->with('user')->first();
        
        if (!$liveUser) {
            return response()->json(['message' => '该用户未开通直播'])->setStatusCode(404);
        }

        // return response()->json($liveUser->user->extra)->setStatusCode(200);
        return response()->json([
            'gold'          => $liveUser->user->wallet->balance,
            'zan_count'     => $liveUser->user->extra->live_zans_count,
            'zan_remain'    => $liveUser->user->extra->live_zans_remain,
            'uname'         => $liveUser->user->name,
            'sex'           => $liveUser->user->sex 
        ])->setStatusCode(200);
    }

    // 同步数据
    public function syncData (Request $request, string $usid, LiveUserInfo $liveUser, UserExtra $userExtra)
    {
        $data = $request->input('data');

        if (!$this->is_ZhiBoService($request)) {
            return response()->json(['message' => '授权错误'])->setStatusCode(401);
        }

        $liveUser = $liveUser->where('usid', $usid)->first();

        if (!$liveUser) {
            return response()->json(['message' => '该用户未开通直播'])->setStatusCode(404);
        }

        $userExtra = $userExtra->where('user_id', $liveUser->uid)->first();

        $userExtra->live_zans_count = $data['zan_count'];
        $userExtra->live_zans_remain += $data['zan_remain'];
        $userExtra->live_time = $data['live_time'];

        $userExtra->update();

        return response()->json(['status' => 1, 'data' => ['is_sync' => 1]])->setStatusCode(201);
    }

    // 直播时推送给直播用户的粉丝
    public function pushLive (Request $request, string $usid, LiveUserInfo $liveUser, User $user)
    {
        if (!$this->is_ZhiBoService($request)) {
            return response()->json(['message' => '授权错误'])->setStatusCode(401);
        }

        $status = $request->input('status');
        if (!$usid) {
            return response()->json(['message' => '参数传递错误'])->setStatusCode(400);
        }

        $liveUser = $liveUser->where('usid', $usid)->first();

        $user = $user->find($liveUser->uid);
        $alert = $user->name . '正在直播，快去看看吧';

        $followers = $user->followers->pluck('id');
        $alias = implode(',', $followers);
        $extras = ['action' => 'notice', 'type' => 'live'];

        dispatch(new PushMessage($alert, $alias, $extras));

        return response()->json(['status' => true])->setStatusCode(202);
    }
}