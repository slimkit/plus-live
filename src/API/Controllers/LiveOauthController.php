<?php

namespace Slimkit\PlusLive\API\Controllers;

use Zhiyi\Plus\Models\User;
use Illuminate\Http\Request;
use Zhiyi\Plus\Auth\JWTAuthToken;
use Zhiyi\Plus\Models\WalletCharge;
use Slimkit\PlusLive\Models\LiveUserInfo;

class LiveOauthController extends BaseController
{
    protected $liveUser;
    protected $userModel;

    public function __construct (LiveUserInfo $liveUser, User $userModel) {
        $this->liveUser = $liveUser;
        $this->userModel = $userModel;
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
    public function getLiveUsers(Request $request)
    {
        $usids = explode(',', $request->input('usid'));
        $login = $request->user('api');

        $users = $this->liveUser->whereIn('usid', $usids)->with('user')->get();
        $userFormate = $users->map( function ($user) use ($login) {
            return [
                'uid'               => (string) $user->user->id,
                'uname'             => $user->user->name,
                // 'phone'             => $user->user->phone,
                'sex'               => $user->user->sex,
                'intro'             => $user->user->bio,
                'location'          => $user->user->location,
                'reg_time'          => $user->user->created_at->toDateTimeString(),
                'is_verified'       => $user->user->verified ? 1 : 0,
                'gold'              => $user->user->wallet ? $user->user->wallet->balance : 0,
                'follow_count'      => $user->user->extra ? $user->user->extra->followings_count : 0,
                'fans_count'        => $user->user->extra ? $user->user->extra->followers_count : 0,
                'zan_count'         => $user->user->extra ? $user->user->extra->live_zans_count : 0,
                'is_follow'         => $login ? intval($login->hasFollwing($user->user)) : 0,
                'cover'             => (object) [ '0' => $user->user->extra->cover ?: '' ],
                'avatar'            => (object) [ '0' => $user->user->avatar ?: '' ],
                'live_time'         => $user->user->extra ? $user->user->extra->live_time : 0,
                'usid'              => 'ts_plus_' . $user->user->id
            ];
        });

        return response()->json(['code' => 00000, 'data' => $userFormate], 200);
    }

    public function followAction(Request $request)
    {
        $action = $request->input('action');
        $user = $request->user();
        switch ($action) {
            case 1:
                return $this->follow($request);
                break;
            case 2:
                return $this->unfollow($request);
                break;
            default:
                return response()->json($user->hasFollwing($request->input('usid')), 200);
                break;
        }
    }

    /**
     * 关注用户
     * @param Request
     */
    public function follow(Request $request, string $usid)
    {
        $login = $request->user();

        if (!$usid) {
            return response()->json(['message' => '缺少被关注用户'], 422);
        }

        $uid = $this->liveUser->where('usid', $usid)->value('uid');

        if (!$uid === $login->id) {
            return response()->json(['message' => '不能关注自己'], 400);
        }

        $follow = $this->userModel->find($uid);

        $status = $login->hasFollwing($follow);

        if ($status) {

            return response()->json(['message' => '已关注过该用户'], 422);
        }

        return $login->getConnection()->transaction(function () use ($login, $follow) {
            $login->followings()->attach($follow);
            $login->extra()->firstOrCreate([])->increment('followings_count', 1);
            $follow->extra()->firstOrCreate([])->increment('followers_count', 1);

            $message = sprintf('%s关注了你，去看看吧', $login->name);
            $follow->sendNotifyMessage('user:follow', $message, [
                'user' => $login,
            ]);

            return response()->json('', 201);
        });
    }

    /**
     * 取消关注
     * @param Request
     */
    public function unfollow(Request $request, string $usid)
    {
        $login = $request->user();

        if (!$usid) {
            return response()->json(['message' => '缺少被取消关注的用户'], 400);
        }

        $uid = $this->liveUser->where('usid', $usid)->value('uid');

        if (!$uid === $login->id) {
            return response()->json(['message' => '不能对自己取关'], 400);
        }

        $follow = $this->userModel->find($uid);

        $status = $login->hasFollwing($follow);

        if (!$status) {

            return response()->json(['message' => '你并没有关注该用户'], 422);
        }

        return $login->getConnection()->transaction(function () use ($login, $follow) {
            $login->followings()->detach($follow);
            $login->extra()->decrement('followings_count', 1);
            $follow->extra()->decrement('followers_count', 1);

            return response()->json('', 201);
        });
    }

    /**
     * 获取用户关注列表
     * @param Request
     */
    public function getUsers(Request $request)
    {
        $type = $request->query('type', 'followers');
        $offset = $request->query('offset');
        $user = $request->user('api');
        $limit = $request->query('limit', 15);
        $data = [];

        if ($type === 'fans') {
            $user->load([
                'followings' => function ($query) use ($offset, $limit) {
                    return $query->when($offset, function ($query) use ($offset) {
                        return $query->offset($offset);
                    })->limit($limit);
                }
            ]);

            // return response()->json($user->followings, 200);
            $data = $user->followings;
        }

        if ($type === 'followers') {
            $user->load([
                'followers' => function ($query) use ($offset, $limit) {
                    return $query->when($offset, function ($query) use ($offset) {
                        return $query->offset($offset);
                    })->limit($limit);
                }
            ]);

            $data = $user->followers;
            // return response()->json($user->followers, 200);
        }

        $data = $data->map(function ($u) use ($user) {
            return [
                'uid'               => (string) $u->id,
                'uname'             => $u->name,
                // 'phone'             => $u->phone,
                'sex'               => $u->sex,
                'intro'             => $u->bio,
                'location'          => $u->location,
                'reg_time'          => $u->created_at->toDateTimeString(),
                'is_verified'       => $u->verified ? 1 : 0,
                'gold'              => $u->wallet ? $this->wallet->balance : 0,
                'follow_count'      => $u->extra ? $u->extra->followings_count : 0,
                'fans_count'        => $u->extra ? $u->extra->followers_count : 0,
                'zan_count'         => $u->extra ? $u->extra->live_zans_count : 0,
                'is_follow'         => $user ? intval($user->hasFollwing($u)) : 0,
                'cover'             => [ '0' => $u->extra->cover ?: '' ],
                'avatar'            => [ '0' => $u->avatar ?: '' ],
                'live_time'         => $u->extra ? $u->extra->live_time : 0,
                'usid'              => 'ts_plus_' . $u->id
            ];
        });

        return response()->json(['code' => 00000, 'data' => $data], 200);
    }

    // /**
    //  * 预操作口令生成
    //  * @param Request
    //  */
    // public function ZB_Trade_Get_Pretoken(Request $request)
    // {
    //     $token = $request->input('token');
    //     $hextime = $request->input('hextime');
    //     $user_id = $request->input('user_id');
    //     $user = $request->user();

    //     $uid = $user->id;

    //     if (!$token || !$hextime || !$user_id)
    //     {
    //         return response()->json(['message' => '参数不完整'], 422);
    //     }

    //     // 口令时间
    //     $ctime = hexdec($hextime);

    //     if ($ctime + 120 < NOW_TIME)
    //     {
    //         return response()->json(['message' => '交易超市'], 422);
    //     }

    //     $m_token = md5($ctime . $type . $user_id);
    //     if (strtolower($m_token) != strtolower($token)) {
    //         return response()->json(['message' => '口令验证失败'], 400);
    //     }

    //     $data = [
    //         'uid' => $user_id,
    //         'to_uid' => $uid
    //     ];

    //     // 尝试获取交易口令
    //     $token = $this->getPreToken($data);
    //     if ($token) {
    //         return response()->json(['pre_token' => $this->jiami($token)], 201);
    //     }

    //     return response()->json(['message' => '交易失败'], 500)
    // }

    /**
     * 发起赞兑换金币订单.
     *
     * @Author   Wayne[qiaobin@zhiyicx.com]
     * @DateTime 2016-10-26T17:36:37+0800s
     */
    public function createOrder(Request $request, WalletCharge $charge)
    {
        $count = $request->input('count');
        $user = $request->user();

        if (!$count || $count < 0) {
            return response()->json(['message' => '兑换数量必须大于0'], 400);
        }

        if (!$count > $user->extra->live_zans_remain) {
            return response()->json(['message' => '你的赞数量不足'], 422);
        }

        $ratio = config('live.exchange_type');
        // 正确的处理方式 $amount = $systemRatio * ($count / $ratio) / 10000;
        $amount = $count / $ratio;

        $user->getConnection()->transaction( function () use ($user, $amount, $charge) {
            $user->wallet()->increment('balance', $amount);

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

        return response()->json(['message' => '兑换成功'], 201);
    }

}