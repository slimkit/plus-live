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


    public function ZB_User_Get_ticket(Request $request)
    {

    }

    /**
     * desc 需要传递用户usid
     * @param Request
     * @param LiveUserInfo
     * @param User
     */
    public function ZB_User_Get_Info (Request $request)
    {
        $usids = explode(',', $request->input('usids'));

        $users = $this->liveUser->whereIn('usid', $usids)->with('user')->get();

        return $users;
    }

    /**
     * 关注用户
     * @param Request
     */
    public function ZB_User_Follow (Request $request, string $usid)
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

            return response()->json(['massage' => '已关注过该用户'], 422);
        }

        return $login->getConnection()->transaction(function () use ($login, $follow) {
            $login->followings()->attach($follow);
            $login->extra()->firstOrCreate([])->increment('followings_count', 1);
            $follow->extra()->firstOrCreate([])->increment('followers_count', 1);

            $message = sprintf('%s关注了你，去看看吧', $login->name);
            $follow->sendNotifyMessage('user:follow', $message, [
                'user' => $login,
            ]);

            return response()->json('', 204);
        });
    }

    /**
     * 取消关注
     * @param Request
     */
    public function ZB_User_Unfollow (Request $request, string $usid)
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

            return response()->json(['massage' => '你并没有关注该用户'], 422);
        }

        return $login->getConnection()->transaction(function () use ($login, $follow) {
            $login->followings()->detach($follow);
            $login->extra()->decrement('followings_count', 1);
            $follow->extra()->decrement('followers_count', 1);

            return response()->json('', 204);
        });
    }

    /**
     * 获取用户关注列表
     * @param Request
     */
    public function ZB_User_Get_List (Request $request)
    {
        $type = $request->query('type', 'followers');
        $offset = $request->query('offset');
        $user = $request->user();
        $limit = $request->query('limit', 15);

        if ($type === 'fans') {
            $user->load([
                'followings' => function ($query) use ($offset, $limit) {
                    return $query->when($offset, function ($query) use ($offset) {
                        return $query->offset($offset);
                    })->limit($limit);
                }
            ]);

            return $user->followings;
        }

        if ($type === 'followers') {
            $user->load([
                'followers' => function ($query) use ($offset, $limit) {
                    return $query->when($offset, function ($query) use ($offset) {
                        return $query->offset($offset);
                    })->limit($limit);
                }
            ]);

            return $user->followers;
        }
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
    public function ZB_Trade_Create(Request $request, WalletCharge $charge)
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

        return response()->json(['messge' => '打赏成功'], 201);
    }

}