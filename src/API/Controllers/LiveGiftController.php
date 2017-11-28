<?php

namespace Slimkit\PlusLive\API\Controllers;

use Zhiyi\Plus\Models\User;
use Illuminate\Http\Request;
use Zhiyi\Plus\Models\WalletCharge;
use Zhiyi\Plus\Models\CommonConfig;
use Slimkit\PlusLive\Models\LiveUserInfo;

class LiveGiftController extends BaseController
{
    protected $setting;

    public function __construct()
    {
        $this->setting = config('live');
    }

    // 请求赠送礼物
    public function handleGift (Request $request, LiveUserInfo $liveModel, User $userModel, WalletCharge $charge, CommonConfig $config)
    {
        if (!$this->is_ZhiboService($request)) {
            return response()->json(['status' => 0, 'message' => '授权错误'])->setStatusCode(200);
        }

        $data = $request->only(['num', 'to_usid', 'usid', 'type', 'order', 'description', 'ctime', 'order_type']);

        $targetLiveUser = $liveModel->where('usid', $data['to_usid'])->value('uid');
        $liveUser = $liveModel->newQuery()->where('usid', $data['usid'])->value('uid');
        
        $targetUser = $userModel->find($targetLiveUser);
        $liveUser = $userModel->newQuery()->find($liveUser);

        if ($liveUser->wallet->balance < $data['num']) {
            return response()->json(['status' => 0, 'message' => '余额不足'])->setStatusCode(200);
        }  

        // 获取转换比例
        $ratio = $config->where('namespace', 'common')
            ->where('name', 'wallet:ratio')
            ->value('value') ?: 1000;

        $liveUser->getConnection()->transaction( function () use ($targetUser, $liveUser, $data, $charge, $ratio) {
            // 扣除操作用户余额
            $liveUser->wallet()->decrement('balance', $data['num'] * 10000 / $ratio );
            // 扣费记录
            $userCharge = clone $charge;
            $userCharge->channel = 'user';
            $userCharge->account = $targetUser->id;
            $userCharge->subject = '直播送礼物';
            $userCharge->action = 0;
            $userCharge->amount = $data['num'];
            $userCharge->body = sprintf('给[%s]的直播间送《%s》', $targetUser->name, $data['description']);
            $userCharge->status = 1;
            $liveUser->walletCharges()->save($userCharge);

            if($targetUser->wallet) {
                // 增加目标用户余额
                $targetUser->wallet()->increment('balance', $data['num'] * 10000 / $ratio);

                $charge->user_id = $targetUser->id;
                $charge->channel = 'user';
                $charge->account = $liveUser->id;
                $charge->subject = '直播被送礼物';
                $charge->action = 1;
                $charge->amount = $data['num'];
                $charge->body = sprintf('直播被[%s]赠送《%s》', $liveUser->name, $data['description']);
                $charge->status = 1;
                $charge->save();
            }
            // 直播被送礼物只有交易记录，没有通知
        });

        return response()->json(['status' => 1, 'message' => '交易成功'], 200);
    }
}