<?php

namespace Slimkit\PlusLive\API\Controllers;

use Zhiyi\Plus\Models\User;
use Illuminate\Http\Request;
use Zhiyi\Plus\Auth\JWTAuthToken;
use Zhiyi\Plus\Models\WalletCharge;
use Zhiyi\Plus\Models\CommonConfig;
use Slimkit\PlusLive\Models\LiveUserInfo;
use Slimkit\PlusLive\Models\LivePreOrderToken;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;

class LiveOauthController extends BaseController
{
    protected $liveUser;
    protected $userModel;

    public function __construct (LiveUserInfo $liveUser, User $userModel) {
        $this->liveUser = $liveUser;
        $this->userModel = $userModel;
        $this->setting = config('live', []);
    }

    /**
     * 获取授权.
     *
     * @param Request $request
     * @return mixed
     * @author BS <414606094@qq.com>
     */
    public function getTicket(Request $request)
    {
        $user = $request->user();
        $ticket = $this->liveUser->where('uid', $user->id)->value('ticket');
        if (! $ticket) {
            $result = app(LiveUserController::class)->register($request, $this->liveUser);
            $result = json_decode($result->getContent(), true);

            if (! isset($result['data']['ticket'])) {
                return response()->json([
                    'message' => '授权验证失败'
                ], 500);
            }

            $ticket = $result['data']['ticket'];
        }

        return response()->json($ticket, 200);
    }

    /**
     * desc 需要传递用户usid
     * @param Request
     * @param LiveUserInfo
     * @param User
     */
    public function getLiveUsers(Request $request, LiveUserInfo $model)
    {
        $usids = explode(',', $request->input('usid'));
        $login = $request->user('api');

        $users = $this->liveUser->whereIn('usid', $usids)->with('user')->get();
        $userFormate = $users->map( function ($user) use ($login, $model) {
            $usid = $model->newQuery()->where('uid', $user->user->id)->value('usid');
            if (!$usid) {
                $result = $this->registerOther(['id' => $user->user->id, 'uname' => $user->user->name, 'sex' => $user->user->sex], $this->setting);
                if ($result) {
                    $usid = 'ts_plus_' . $user->user->id;
                } else {
                    $usid = '';
                }
            }
            return [
                'uid'               => (string) $user->user->id,
                'uname'             => $user->user->name,
                'sex'               => $user->user->sex,
                'intro'             => $user->user->bio ?: '',
                'location'          => $user->user->location ?: '',
                'reg_time'          => $user->user->created_at->toDateTimeString(),
                'is_verified'       => $user->user->verified ? 1 : 0,
                'gold'              => $user->user->wallet ? $user->user->wallet->balance : 0,
                'follow_count'      => $user->user->extra ? $user->user->extra->followings_count : 0,
                'fans_count'        => $user->user->extra ? $user->user->extra->followers_count : 0,
                'zan_count'         => $user->user->extra ? $user->user->extra->live_zans_count : 0,
                'is_follow'         => $login ? intval($login->hasFollwing($user->user)) : 0,
                'cover'             => $user->user->extra ? (object) [ '0' => $user->user->extra->cover] : (object) [],
                'avatar'            => $user->user->avatar ? (object) [ '0' => $user->user->avatar ] : (object) [],
                'live_time'         => $user->user->extra ? $user->user->extra->live_time : 0,
                'usid'              => $usid
            ];
        });

        return response()->json(['code' => '00000', 'data' => $userFormate], 200);
    }

    public function followAction(Request $request)
    {
        $action = $request->input('action');

        switch ($action) {
            case 1:
                return $this->follow($request);
                break;
            case 2:
                return $this->unfollow($request);
                break;
            default:
                return $this->getFollowStatus($request);
                break;
        }
    }

    /**
     * 查询关注状态
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getFollowStatus(Request $request)
    {   
        $user = $request->user('api');
        if (!$user) {

            return response()->json(['code' => '00001', 'message' => '请先登录'], 200);
        }

        $model = new LiveUserInfo();

        $usid = $request->input('usid');

        if (!$usid) {
            return response()->json(['code' => '00502','message' => "缺少需要查询的用户"], 200);
        }

        $uid = $model->where('usid', $usid)->value('uid');

        return response()->json(['code' => '00000', 'data' => ['is_follow' => intval($user->hasFollwing($uid))]], 200);
    }

    /**
     * 关注用户
     * @param Request
     */
    public function follow(Request $request)
    {
        $login = $request->user('api');
        $usid = $request->input('usid');
        if (!$login) {
            return response()->json(['code' => '00001', 'message' => '请先登录'], 200);
        }

        if (!$usid) {
            return response()->json(['code' => '00502', 'message' => '缺少被关注用户'], 200);
        }

        $uid = $this->liveUser->where('usid', $usid)->value('uid');

        if ($uid === $login->id) {
            return response()->json(['code' => '00506', 'message' => '不能关注自己'], 200);
        }

        $follow = $this->userModel->find($uid);

        $status = $login->hasFollwing($follow);

        if ($status) {

            return response()->json(['code' => '00506', 'message' => '已关注过该用户'], 200);
        }

        return $login->getConnection()->transaction(function () use ($login, $follow) {
            $login->followings()->attach($follow);
            $login->extra()->firstOrCreate([])->increment('followings_count', 1);
            $follow->extra()->firstOrCreate([])->increment('followers_count', 1);

            $message = sprintf('%s关注了你，去看看吧', $login->name);
            $follow->sendNotifyMessage('user:follow', $message, [
                'user' => $login,
            ]);

            return response()->json(['code' => '00000', 'data' => ['is_follow' => 1]], 200);
        });
    }

    /**
     * 取消关注
     * @param Request
     */
    public function unfollow(Request $request)
    {
        $login = $request->user('api');
        if (!$login) {
            return response()->json(['code' => '00001', 'message' => '请先登录'], 200);
        }

        $usid = $request->input('usid');
        if (!$usid) {
            return response()->json(['code' => '00502', 'message' => '缺少被取消关注的用户'], 200);
        }

        $uid = $this->liveUser->where('usid', $usid)->value('uid');

        if (!$uid === $login->id) {
            return response()->json(['code' => '00506', 'message' => '不能对自己取关'], 200);
        }

        $follow = $this->userModel->find($uid);

        $status = $login->hasFollwing($follow);

        if (!$status) {

            return response()->json(['code' => '00506', 'message' => '你并没有关注该用户'], 200);
        }

        return $login->getConnection()->transaction(function () use ($login, $follow) {
            $login->followings()->detach($follow);
            $login->extra()->decrement('followings_count', 1);
            $follow->extra()->decrement('followers_count', 1);

            return response()->json(['code' => '00000', 'data' => ['is_follow' => 0]], 200);
        });
    }

    /**
     * 获取用户关注列表
     * @param Request
     */
    public function getUsers(Request $request, LiveUserInfo $model, User $userModel, ApplicationContract $app)
    {   
        $usid = $request->input('usid');
        $type = $request->input('type', 'follow');
        $offset = $request->input('offset');
        $limit = $request->input('limit', 15);
        $data = [];
        $uid = $model->where('usid', $usid)->value('uid');

        $user = $userModel->find($uid);
        if ($type === 'follow') {
            $user->load([
                'followings' => function ($query) use ($offset, $limit) {
                    return $query->with('wallet')->when($offset, function ($query) use ($offset) {
                        return $query->offset($offset);
                    })->paginate($limit);
                }
            ]);

            // return response()->json($user->followings, 200);
            $data = $user->followings;
        }

        if ($type === 'following') {
            $user->load([
                'followers' => function ($query) use ($offset, $limit) {
                    return $query->with('wallet')->when($offset, function ($query) use ($offset) {
                        return $query->offset($offset);
                    })->paginate($limit);
                }
            ]);

            $data = $user->followers;
            // return response()->json($user->followers, 200);
        }

        $data = $data->map(function ($u) use ($user, $model, $app) {
            $usid = $model->newQuery()->where('uid', $u->id)->value('usid');
            if (!$usid) {
                $result = $this->registerOther(['id' => $u->id, 'uname' => $u->name, 'sex' => $u->sex], $this->setting);
                if ($result) {
                    $usid = 'ts_plus_' . $u->id;
                } else {
                    $usid = '';
                }
            }
            return [
                'user' => [
                    'uid'               => (string) $u->id,
                    'uname'             => $u->name,
                    'sex'               => $u->sex,
                    'intro'             => $u->bio ?: '',
                    'location'          => $u->location ?: '',
                    'reg_time'          => $u->created_at->toDateTimeString(),
                    'is_verified'       => $u->verified ? 1 : 0,
                    'gold'              => $u->wallet ? $u->wallet->balance : 0,
                    'follow_count'      => $u->extra ? $u->extra->followings_count : 0,
                    'fans_count'        => $u->extra ? $u->extra->followers_count : 0,
                    'zan_count'         => $u->extra ? $u->extra->live_zans_count : 0,
                    'cover'             => $u->extra ? (object) [ '0' => $u->extra->cover ] : (object) [],
                    'avatar'            =>  $u->avatar ? (object) [ '0' => $u->avatar ] : (object) [],
                    'live_time'         => $u->extra ? $u->extra->live_time : 0,
                    'usid'              => $usid
                ],
                'is_follow'             => intval($user->hasFollwing($u)) ?: 0
            ];
        });

        return response()->json(['code' => '00000', 'data' => $data], 200);
    }

    /**
     * 发起赞兑换金币订单.
     * resetful接口
     *
     * @Author   Wayne[qiaobin@zhiyicx.com]
     * @DateTime 2016-10-26T17:36:37+0800s
     */
    public function createOrder(Request $request, WalletCharge $charge, CommonConfig $config)
    {
        $count = $request->input('count');
        $user = $request->user('api');

        if (! $user) {
            return response()->json(['code' => '00001', 'message' => '请先登录'], 200);
        }

        if (!$count || $count < 0) {
            return response()->json(['code' => '70302', 'message' => '兑换数量必须大于0'], 400);
        }

        if (!$count > $user->extra->live_zans_remain) {
            return response()->json(['code' => '70302', 'message' => '你的赞数量不足'], 422);
        }

        // 赞到金币的兑换比例
        $exchange_type = config('live.exchange_type');
        // 金币到cny的兑换比例
        $ratio = $config->where('namespace', 'common')
            ->where('name', 'wallet:ratio')
            ->value('value') ?: 1000;

        // 计算成CNY分单位
        $amount = $count * 10000 / $exchange_type / $ratio;

        $user->getConnection()->transaction( function () use ($user, $amount, $charge, $count) {
            $user->wallet()->increment('balance', $amount); // 加余额
            // 减赞
            $user->extra->live_zans_remain = $user->extra->live_zans_remain - $count;
            $user->extra->save();
            // $user->extra()->decrement('live_zans_remain', $count); // 减赞

            $charge->user_id = $user->id;
            $charge->channel = 'live';
            $charge->account = $user->id;
            $charge->subject = '直播获取的赞兑换金币';
            $charge->action = 1;
            $charge->amount = $amount;
            $charge->body = '将直播获得的赞兑换成为金币';
            $charge->status = 1;

            $charge->save();
        });

        // 通知直播服务器需要更新当前用户信息
        $this->notifyLiveServer($request, $this->setting);

        $charge->usid = LiveUserInfo::where('uid', $user->id)->value('usid');

        return response()->json(['code' => '00000', 'data' => $charge], 201);
    }

    public function getPreToken(Request $request)
    {
        $login = $request->user('api');

        if (!$login) {
            return response()->json(['code' => '00001', 'message' => '请先登录'], 200);
        }

        $type = (int) $request->input('type');
        $token = $request->input('token');
        $hextime = $request->input('hextime');
        $uid = (int) $request->input('user_id');

        if ((!$type || $type != 1) || !$token || !$hextime || !$uid) {

            return response()->json(['code' => '40007', 'message' => '参数错误'], 422);
        }
        if ($uid != $login->id) {

            return response()->json(['code' => '40007', 'message' => '您没有权限执行此操作'], 422);
        }
        //口令时间检测
        $ctime = hexdec($hextime);
        if ($ctime + 120 < time()) {
            //过期的口令

            return response()->json(['code' => '40007', 'message' => '交易超时'], 422);
        }
        $m_token = md5($ctime.$type.$uid);
        if (strtolower($m_token) != strtolower($token)) {
            //口令验证失败

            return response()->json(['code' => '50000', 'message' => '口令验证失败'], 422);
        }
        //条件
        $data = [
            'uid'    => $uid,
            'to_uid' => $login->id,
        ];
        //尝试获取预交易口令
        $table = new LivePreOrderToken();
        //禁用状态
        $data['disabled'] = 0;
        //是否存在未使用的口令

        $hasOne = $table->firstOrCreate($data, ['token' =>  $this->jiami(date('mdHs', time() - 60).mt_rand(10000, 99999).$data['uid'])]);
        $token = $hasOne->token;
        if ($token) {
            return response()->json(['code' => '00000', 'data' => ['pre_token' => $this->jiami($token)]]);
        }

        return response()->json(['code' => '70500', 'message' => '交易失败']);
    }
}