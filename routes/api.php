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
        
        // 获取某个用户授权
        $api->get('/users/{usid}', API\LiveUserController::class.'@getUserData');

        // 更新某个用户授权
        $api->post('/users/{usid}/sync', API\LiveUserController::class.'@syncData');

        // 赠送礼物
        $api->post('/handleGift', API\LiveGiftController::class.'@handleGift');

        // 推送直播信息
        $api->post('/pushLive/{usid}', API\LiveUserController::class.'@pushLive');
    });

    $api->group(['middleware' => 'auth:api'], function (RouteRegisterContract $api) {

        $api->group(['prefix' => 'live'], function (RouteRegisterContract $api) {
            // 注册直播用户
            $api->post('/user', API\LiveUserController::class. '@register');

            // 获取直播凭据
            $api->get('/ticket', API\LiveOauthController::class.'@getTicket');

            // 关注用户
            $api->post('/{usid}/follow', API\LiveOauthController::class. '@follow');

            // 取消关注用户
            $api->delete('/{usid}/follow', API\LiveOauthController::class. '@unfollow');

            // 获取用户关注列表
            $api->get('/followers', API\LiveOauthController::class . '@getUsers');

            // 赞兑换金币
            $api->post('/order', API\LiveOauthController::class . '@createOrder');

            // 通过usid批量获取用户信息
            $api->get('/users', API\LiveOauthController::class.'@getLiveUser');
        });
    });
});

