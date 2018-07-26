<?php

namespace Slimkit\PlusLive\API\Controllers;

use Zhiyi\Plus\Models\CurrencyOrder;
use Zhiyi\Plus\Models\CurrencyType;
use Zhiyi\Plus\Models\User;
use Illuminate\Http\Request;
use Slimkit\PlusLive\Models\LiveUserInfo;

class LiveGiftController extends BaseController
{
    protected $setting;

    public function __construct()
    {
        $this->setting = config('live');
    }

    // 请求赠送礼物
    public function handleGift (Request $request, LiveUserInfo $liveModel, User $userModel, CurrencyOrder $currencyOrder)
    {
        $currency_type = CurrencyType::find(1);
        if (! $currency_type) {
            return response()->json(['status' => 0, 'message' => '出错了,请稍后再试'])->setStatusCode(200);
        }
        if (!$this->is_ZhiboService($request)) {
            return response()->json(['status' => 0, 'message' => '授权错误'])->setStatusCode(200);
        }

        $data = $request->only(['num', 'to_usid', 'usid', 'type', 'order', 'description', 'ctime', 'order_type']);

        $targetLiveUser = $liveModel->where('usid', $data['to_usid'])->value('uid');
        $liveUser = $liveModel->newQuery()->where('usid', $data['usid'])->value('uid');
        
        $targetUser = $userModel->find($targetLiveUser);
        $liveUser = $userModel->newQuery()->find($liveUser);

        if ($liveUser->currency->sum < $data['num']) {
            return response()->json(['status' => 0, 'message' => '余额不足'])->setStatusCode(200);
        }

        $liveUser->getConnection()->transaction( function () use ($targetUser, $liveUser, $data, $currencyOrder, $currency_type) {
            // 扣除操作用户余额
            $liveUser->currency()->decrement('sum', $data['num']);
            // 被扣款人
            $currentCurrencyOrder = clone $currencyOrder;
            $currentCurrencyOrder->owner_id = $liveUser->id;
            $currentCurrencyOrder->title = '直播送礼物';
            $currentCurrencyOrder->body = sprintf('给[%s]的直播间送《%s》', $targetUser->name, $data['description']);
            $currentCurrencyOrder->type = -1;
            $currentCurrencyOrder->target_type = 'live';
            $currentCurrencyOrder->target_id = 0;
            $currentCurrencyOrder->currency = $currency_type->id;
            $currentCurrencyOrder->amount = $data['num'];
            $currentCurrencyOrder->state = 1;
            $currentCurrencyOrder->save();

            if($targetUser->currency) {
                // 增加目标用户余额
                $targetUser->currency()->increment('sum', $data['num']);
                // 被送的人
                $currencyOrder->owner_id = $liveUser->id;
                $currencyOrder->title = '直播被送礼物';
                $currencyOrder->body = sprintf('直播被[%s]赠送《%s》', $liveUser->name, $data['description']);
                $currencyOrder->type = 1;
                $currencyOrder->target_type = 'live';
                $currencyOrder->target_id = 0;
                $currencyOrder->currency = $currency_type->id;
                $currencyOrder->amount = $data['num'];
                $currencyOrder->state = 1;
                $currencyOrder->save();
            }
            // 直播被送礼物只有交易记录，没有通知
        });

        $this->_notifyLiveServer($request->input('usid'), $this->setting);

        return response()->json(['status' => 1, 'message' => '交易成功'], 200);
    }
}