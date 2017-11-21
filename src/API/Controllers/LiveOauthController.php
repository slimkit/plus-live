<?php

namespace Slimkit\PlusLive\API\Controllers;

use Zhiyi\Plus\Models\User;
use Illuminate\Http\Request;
use Zhiyi\Plus\Auth\JWTAuthToken;
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

    /**
     * 预操作口令生成
     * @param Request
     */
    public function ZB_Trade_Get_Pretoken(Request $request)
    {

    }

    /**
     * 发起创建订单.
     *
     * @Author   Wayne[qiaobin@zhiyicx.com]
     * @DateTime 2016-10-26T17:36:37+0800s
     */
    public function ZB_Trade_Create()
    {

    }

    /**
     * @name 生成预交易口令
     */
    public function getPreToken($data = array())
    {

    }

}