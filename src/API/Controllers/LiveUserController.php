<?php

namespace Slimkit\PlusLive\API\Controllers;

use GuzzleHttp\Client;
use Zhiyi\Plus\Models\User;
use Zhiyi\Plus\Service\Push;
use Illuminate\Http\Request;
use Zhiyi\Plus\Models\UserExtra;
use Zhiyi\Plus\Models\CommonConfig;
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
    public function register(Request $request, LiveUserInfo $model)
    {
        $user = $request->user();
        $ticket = $request->input('ticket', '');
        $stream_server = $this->setting['stream_server'] ?? '';
        $usid_prex = $this->setting['usid_prex'] ?? '';
        $curl_header = $this->setting['curl_header'] ?? '';

        if (!$stream_server || !$usid_prex) {
            return response()->json(['msg' => '请先设置直播服务器']);
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
            
            $this->notifyLiveServer($request, $this->setting);

            return response()->json(['msg' => '直播用户更新成功', 'data' => $model])->setStatusCode(201);
        } else {
            return response()->json(['msg' => '注册直播用户失败'])->setStatusCode(500);
        }
    }

    /**
     * 获取直播服务器代码.
     *
     * @param Request $request
     * @param string $usid
     * @param LiveUserInfo $liveUser
     * @return mixed
     */
    public function getInfo(Request $request, LiveUserInfo $liveUser)
    {
        $usid = $request->input('usid');

        if (!$this->is_ZhiBoService($request)) {
            return response()->json(['message' => '授权错误'])->setStatusCode(401);
        }

        $liveUser = $liveUser->where('usid', $usid)->with('user')->first();
        
        if (!$liveUser) {
            return response()->json(['message' => '该用户未开通直播'])->setStatusCode(404);
        }

        return response()->json([
            'status' => 1,
            'data' => [
                'gold' => $liveUser->user->currency->sum ?? 0,
                'zan_count' => $liveUser->user->extra->live_zans_count,
                'zan_remain' => $liveUser->user->extra->live_zans_remain,
                'uname' => $liveUser->user->name,
                'sex' => $liveUser->user->sex
            ]
        ])->setStatusCode(200);
    }

    /**
     * 同步用户信息.
     *
     * @param Request $request
     * @param string $usid
     * @param LiveUserInfo $liveUser
     * @param UserExtra $userExtra
     * @return mixed
     */
    public function sync(Request $request, LiveUserInfo $liveUser, UserExtra $userExtra)
    {
        $data = $request->input('data');
        $usid = $request->input('usid');

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

    /**
     * 推送直播信息给直播用户的粉丝.
     *
     * @param Request $request
     * @param string $usid
     * @param LiveUserInfo $liveUser
     * @param User $user
     * @return mixed
     */
    public function pushLive(Request $request, LiveUserInfo $liveUser, User $user)
    {
//        $usid = $request->input('usid');
//
//        if (!$this->is_ZhiBoService($request)) {
//            return response()->json(['message' => '授权错误'])->setStatusCode(401);
//        }
//
//        $status = $request->input('status');
//        if (!$usid) {
//            return response()->json(['message' => '参数传递错误'])->setStatusCode(400);
//        }

//        $liveUser = $liveUser->where('usid', $usid)->first();

//        $user = $user->find($liveUser->uid);
//        $alert = $user->name . '正在直播，快去看看吧';

//        $followers = $user->followers->pluck('id');
//        if ($followers) {
//            $alias = implode(',', $followers);
//            $extras = ['action' => 'notice', 'type' => 'live'];

//            dispatch(new PushMessage($alert, $alias, $extras));
//        }

        return response()->json(['status' => true])->setStatusCode(202);
    }
}
