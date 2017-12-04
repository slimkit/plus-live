<?php
namespace Slimkit\PlusLive\API\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;

class HomeController
{
    public function index(Request $request, ApplicationContract $app)
    {
        $api = $request->input('api');

        switch ($api) {

            // 注册直播服务端用户
            case 'postUser':
                
                return $app->call([app(LiveUserController::class), 'register']);
                break;
            
            // 获取直播服务端用户信息
            case 'getUserData':
                
                return $app->call([app(LiveUserController::class), 'getInfo']);
                break;

            // 同步用户信息
            case 'syncData':
                
                return $app->call([app(LiveUserController::class), 'sync']);
                break;
            case 'ZB_User_Get_Info':
                return $app->call([app(LiveOauthController::class), 'getLiveUsers']);
                break;
            // 获取直播凭据
            case 'ZB_User_Get_ticket':
                
                return $app->call([app(LiveOauthController::class), 'getTicket']);
                break;

            // 获取粉丝/关注用户列表
            case 'ZB_User_Get_List':
                
                return $app->call([app(LiveOauthController::class), 'getUsers']);
                break;

            // 关注/取关用户
            case 'ZB_User_Follow':
                
                return $app->call([app(LiveOauthController::class), 'followAction']);
                break;

            // 发起赞兑换金币订单.
            case 'ZB_Trade_Create':
                
                return $app->call([app(LiveOauthController::class), 'createOrder']);
                break;

            // 生成预交易口令
            case 'ZB_Trade_Get_Pretoken':

                return $app->call([app(LiveOauthController::class), 'getPreToken']);
                break;

            default:

                return response()->json(['status' => 0, 'message' => '参数错误']);
                break;
        }
    }

    public function rooms () {
        return 'hehehe';
    }
}
