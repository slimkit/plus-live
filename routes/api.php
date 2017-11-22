<?php

use Illuminate\Support\Facades\Route;
use Slimkit\PlusLive\API\Controllers as API;
use Illuminate\Contracts\Routing\Registrar as RouteRegisterContract;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'api/v2'], function (RouteRegisterContract $api) {
    $api->group(['prefix' => 'live'], function (RouteRegisterContract $api) {
        // 获取所有的直播列表
        // $api->get('/', API\HomeController::class.'@rooms');
        $api->get('/users/{usid}', API\LiveUserController::class.'@getUserData');
        $api->post('/users/{usid}/sync', API\LiveUserController::class.'@syncData');
        $api->post('/handleGift', API\LiveGiftController::class.'@handleGift');
        $api->post('/pushLive/{usid}', API\LiveUserController::class.'@pushLive');

        // 通过usid批量获取用户信息
        $api->get('/ZB_User_Get_Info', API\LiveOauthController::class.'@ZB_User_Get_Info');

    });

    $api->group(['middleware' => 'auth:api'], function (RouteRegisterContract $api) {
        // 注册直播用户
        $api->group(['prefix' => 'live'], function (RouteRegisterContract $api) {
            $api->post('/registerUser', API\LiveUserController::class. '@registerUser');

            // 我的页面相关
            $api->get('/users', API\LiveOauthController::class. '@index');

            // 获取直播凭据
            $api->get('/ticket', API\LiveOauthController::class.'@ZB_User_Get_ticket');

            // 关注用户
            $api->post('/ZB_User_Follow/{usid}', API\LiveOauthController::class. '@ZB_User_Follow');

            // 取消关注用户
            $api->post('/ZB_User_Unfollow/{usid}', API\LiveOauthController::class. '@ZB_User_Unfollow');

            // 获取用户关注列表
            $api->get('/ZB_User_Get_List', API\LiveOauthController::class . '@ZB_User_Get_List');

            // 赞兑换金币
            $api->post('/ZB_Trade_Create', API\LiveOauthController::class . '@ZB_Trade_Create');
        });
    });
});

