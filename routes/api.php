<?php

use Illuminate\Http\Request;
use App\Http\Middleware\CheckToken;
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

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});
$api = app('Dingo\Api\Routing\Router');
//$api->version('v', ['namespace'=>'App\Api\v1\Controllers'],function ($api) {

$api->version('v',['prefix'=>'api/v1','namespace'=>'App\Api\v1'], function ($api) {
    $api->post('token','Controllers\TokenController@generateToken');//generate token
    $api->post('upload','Controllers\TokenController@imgUpload');//图片上传
    $api->get('proxyList/{id}','Controllers\ShopController@getProxyList');//分享店铺列表
    $api->post('proxyShow','Controllers\ShopController@proxyShow');//分享店铺产品详情
    $api->get('detail/{id}','Controllers\ShopController@detail');//分享店铺列表
    $api->post('getInformation', 'Controllers\ShopController@shareInformation');//分享信息
    $api->get('getHeader/{id}', 'Controllers\ShopController@getHeader');//店铺页面底部信息
    $api->get('test', 'Controllers\IndexController@test');
    $api->post('testAccount', 'Controllers\LoginController@testAccount');
    $api->get('checkOrder', 'Controllers\LoginController@checkOrder');
    $api->group(['middleware'=>'App\Http\Middleware\CheckToken'],function ($api) {
        $api->post('getCode','Controllers\LoginController@getVerifyCode');//验证码获取:注册
        $api->post('checkCode','Controllers\LoginController@checkVerifyCode');//验证码验证
        $api->post('register','Controllers\LoginController@register');//用户注册
        $api->post('login','Controllers\LoginController@login');//用户登录
        $api->get('logout','Controllers\LoginController@logout');//用户注销
        $api->post('reset','Controllers\LoginController@resetPassword');//修改密码
        $api->post('forgot','Controllers\LoginController@forgot');//忘记密码
        $api->get('region','Controllers\UserController@region');//地区信息
        $api->get('basicCity', 'Controllers\UserController@basicCity');//城市字母排序
        $api->post('getQrcode','Controllers\ShopController@getQrcode');//单独生成二维码
    });
    $api->group(['prefix'=>'','middleware'=>'App\Http\Middleware\CheckToken'],function ($api) {
        $api->get('index','Controllers\IndexController@index');//首页
        $api->get('manager','Controllers\IndexController@manager');//找顾问
        $api->get('article','Controllers\IndexController@article');//咨询
        $api->post('map','Controllers\IndexController@mapLoaner');//地图
        $api->get('quickConfig','Controllers\IndexController@quickConfig');//快速搜索配置
        $api->post('quickSearch','Controllers\IndexController@quickSearch');//快速搜索
    });
    $api->group(['prefix'=>'article','middleware'=>'App\Http\Middleware\CheckToken'],function ($api) {//首页
        $api->get('index','Controllers\ArticleController@index');//首页
        $api->get('category','Controllers\ArticleController@category');//分类
        $api->post('search','Controllers\ArticleController@search');//筛选|排序
        $api->get('{show}','Controllers\ArticleController@show');//详情
    });
    $api->group(['prefix'=>'user','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api) {//个人中心
        $api->get('info','Controllers\UserController@information');//用户信息
        $api->get('process','Controllers\UserController@process');//贷款进度
        $api->get('history','Controllers\UserController@history');//贷款历史
        $api->get('score','Controllers\UserController@scoreList');//贷款评价
        $api->get('scoreType','Controllers\UserController@scoreType');//贷款评价类型
        $api->post('addScore','Controllers\UserController@addScore');//添加贷款评价
        $api->post('deleteLoan','Controllers\UserController@deleteLoan');//订单删除
        $api->post('setFavorite','Controllers\UserController@setFavorite');//收藏:添加|删除
        $api->post('favorite','Controllers\UserController@favoriteList');//收藏列表
        $api->get('points','Controllers\UserController@pointList');//积分流水列表
        $api->get('rule','Controllers\UserController@pointRules');//积分规则
        $api->post('feedback','Controllers\UserController@feedback');//用户反馈
        $api->post('document','Controllers\UserController@document');//审核资料提交
        $api->get('authDocument','Controllers\UserController@authDocument');//认证成功资料
        $api->get('question','Controllers\UserController@myQuestion');//我的提问
        $api->get('answer','Controllers\UserController@myAnswer');//我的答案
        $api->post('avatar','Controllers\UserController@avatar');//头像修改
        $api->get('notice/{type}','Controllers\UserController@notice');//信息提醒
        $api->get('noticeNumber','Controllers\UserController@noticeNumber');//未读消息数量
        $api->post('report', 'Controllers\UserController@report');//店铺订单：举报
        $api->post('setRead','Controllers\UserController@setRead');//消息：已读设置
    });
    $api->group(['prefix'=>'loan','middleware'=>['App\Http\Middleware\CheckToken']],function ($api) {//找贷款
        $api->post('search/{region?}','Controllers\LoanController@search');//找贷款筛选
        $api->get('config','Controllers\LoanController@attrConfig');//找贷款条件
        $api->get('applyConfig','Controllers\LoanController@applyConfig');//申请贷款:配置
    });
    $api->group(['prefix'=>'loan','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api) {//找贷款
        $api->post('apply','Controllers\LoanController@applyLoan');//申请贷款
        $api->post('quickApply','Controllers\LoanController@quickApply');//申请贷款:快速申请
        $api->get('recommend','Controllers\LoanController@recommend');//申请贷款:推荐
    });

    $api->group(['prefix'=>'loaner','middleware'=>'App\Http\Middleware\CheckToken'],function ($api) {//找顾问
//        $api->get('index/{region?}','Controllers\LoanerController@index');//找顾问首页
        $api->get('config','Controllers\LoanerController@config');//找顾问筛选条件
        $api->post('search/{region?}','Controllers\LoanerController@search');//找顾问筛选
        $api->get('show/{show}','Controllers\LoanerController@show');//找顾问详情
        $api->get('show/{show}/list','Controllers\LoanerController@productList');//找顾问产品
        $api->get('single/{shopid}/{id}','Controllers\LoanerController@single');//找顾:单个产品详情
        $api->get('question/{id}','Controllers\LoanerController@question');//顾问：提问
        $api->post('evaluate','Controllers\LoanerController@evaluate');//顾问：评价列表
        $api->get('attribute','Controllers\LoanerController@attribute');//顾问：服务评价类型
        $api->get('average/{id}','Controllers\LoanerController@average');//顾问：综合评价

        $api->get('getProduct','Controllers\LoanerController@getProduct');//第三方产品
    });
    $api->group(['prefix'=>'shop','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckManager']],function ($api) {//店铺
        $api->get('index', 'Controllers\ShopController@index');//店铺首页：判断信贷经理认证情况
        $api->get('info/{id?}', 'Controllers\ShopController@information');//经理信息
        $api->post('apply', 'Controllers\ShopController@applyShop');//店铺申请
        $api->get('showHeader', 'Controllers\ShopController@showHeader');//店铺详情
        $api->post('list', 'Controllers\ShopController@orderList');//店铺订单列表：
        $api->get('tag', 'Controllers\ShopController@userTags');//店铺详情：用户评价标签
        $api->post('evaluate', 'Controllers\ShopController@evaluate');//店铺订单：提交评价
        $api->post('report', 'Controllers\ShopController@report');//店铺订单：举报
        $api->get('show/{show}', 'Controllers\ShopController@show');//店铺订单：订单详情
        $api->post('setProcess', 'Controllers\ShopController@setProcess');//店铺订单：进度控制
        $api->get('otherList/{type}', 'Controllers\ShopController@otherProduct');//店铺订单：代理列表
        $api->get('otherSearch', 'Controllers\ShopController@otherSearch');//店铺订单：代理:搜索
        $api->get('otherProduct', 'Controllers\ShopController@otherProduct');//店铺订单：代理:我的代理
        $api->get('otherType', 'Controllers\ShopController@otherType');//店铺订单：代理列表
        $api->post('otherShow', 'Controllers\ShopController@otherShow');//店铺订单：代理：详情
        $api->post('setAgent', 'Controllers\ShopController@setAgent');//店铺订单：代理：申请|取消
        $api->post('getList', 'Controllers\ShopController@getList');//代理列表
        $api->get('shareInfo', 'Controllers\ShopController@shareInfo');//分享信息
        $api->post('getInformation', 'Controllers\ShopController@getInformation');//分享信息
        $api->post('updateInformation', 'Controllers\ShopController@updateInformation');//更新
    });
    $api->group(['prefix'=>'product','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckManager']],function ($api) {//甩单
        $api->post('create','Controllers\JunkController@create');//店铺：甩单：发布
        $api->get('type','Controllers\JunkController@type');//店铺：甩单：产品类型
        $api->post('listProduct','Controllers\JunkController@listProduct');//店铺：甩单：列表
        $api->get('show/{id}','Controllers\JunkController@productShow');//店铺：甩单:详情
        $api->post('setPrice','Controllers\JunkController@setPrice');//店铺：甩单:价格设定
        $api->post('again','Controllers\JunkController@junkAgain');//店铺：甩单:重新甩单
    });
    $api->group(['prefix'=>'loot','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api) {//抢单
        $api->post('search','Controllers\LootController@search');//抢单：搜索
        $api->get('config','Controllers\LootController@config');//抢单：配置
    });
    $api->group(['prefix'=>'loot','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckManager']],function ($api) {//抢单
        $api->post('grab','Controllers\LootController@grab');//抢单：抢单
        $api->get('show/{show}','Controllers\LootController@productShow');//抢单：详情
    });
    $api->group(['prefix'=>'manager','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api) {//B个人中心
        $api->get('info','Controllers\ManagerController@information');//B个人中心：首页
        $api->get('point','Controllers\ManagerController@point');//B个人中心：积分流水
        $api->get('rule','Controllers\ManagerController@pointRule');//B个人中心：积分规则
        $api->post('auth','Controllers\ManagerController@authentication');//B个人中心：认证资料提交
        $api->get('listProduct','Controllers\ManagerController@listProduct');//B个人中心：甩单列表
        $api->post('orderList','Controllers\ManagerController@orderList');//B个人中心：订单列表
        $api->post('evaluate', 'Controllers\ManagerController@evaluate');//店铺订单：提交评价
        $api->get('index', 'Controllers\ManagerController@index');//B端：首页
        $api->get('authDocument', 'Controllers\ManagerController@authDocument');//B端：认证情况
        $api->post('myJunkProduct', 'Controllers\JunkController@myJunkProduct');//B端：我的甩单
        $api->get('test', 'Controllers\JunkController@test');//B端：
    });
    /***********Mobile Api H5 Start************/
    $api->group(['prefix'=>'web'],function($api){
        $api->get('info/{deviceid}/{token}/{id}','Mobile\Client\WebController@showHeader');//首页：店铺详情
        $api->get('proList/{deviceid}/{token}/{id}','Mobile\Client\WebController@productList');//首页：店铺详情:产品列表
        $api->get('average/{id}','Mobile\Client\WebController@average');//首页:申请：综合评分
        $api->post('evaluate/{id}','Mobile\Client\WebController@evaluate');//首页:申请：评价列表
        $api->get('article/{deviceid}/{token}/{id}','Mobile\Client\WebController@show');//首页:申请：评价列表
        $api->post('recommend/{deviceid}/{token}','Mobile\Client\WebController@recommend');//贷款计算器：推荐信贷经理
        $api->get('information/{deviceid}/{token}/{id}','Mobile\Client\WebController@info');//分享经理人信息
    });
    /***********Mobile Api H5 END************/
    /***********Mobile Api Start************/
    $api->group(['prefix'=>'mobile/client','middleware'=>['App\Http\Middleware\CheckToken']],function ($api)
    {//C端:首页
        $api->post('map','Mobile\Client\IndexController@mapLoaner');//信贷经理地图
        $api->get('manager','Mobile\Client\IndexController@manager');//推荐信贷经理
        $api->get('topConfig','Mobile\Client\IndexController@config');//首页配置
        $api->get('attrConfig','Mobile\Client\IndexController@attrConfig');//搜索配置
        $api->post('search','Mobile\Client\IndexController@search');//搜索
        $api->post('report','Mobile\Client\IndexController@report');//首页：店铺详情:信贷经理：举报
        $api->post('single','Mobile\Client\IndexController@single');//首页:信贷经理：产品详情
        $api->get('config','Mobile\Client\IndexController@applyConfig');//首页:我要贷款:配置
        $api->get('recommend','Mobile\Client\IndexController@recommend');//首页:申请：推荐经理
        $api->get('article','Mobile\Client\ArticleController@index');//咨询首页
        $api->get('articleList/{id}','Mobile\Client\ArticleController@articleList');//咨询列表
    });
    $api->group(['prefix'=>'mobile/client',' middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api)
    {//C端:首页:
        $api->post('checkFavorite','Mobile\Client\IndexController@checkFavorite');//信贷经理：判断是否收藏
        $api->get('checkNotice','Mobile\Client\IndexController@checkNotice');//信贷经理：判断是否有未读消息
        $api->post('addFavorite','Mobile\Client\IndexController@addFavorite');//首页:店鋪：收藏
        $api->post('apply','Mobile\Client\IndexController@applyLoan');//首页:申请
        $api->post('quickApply','Mobile\Client\IndexController@quickApply');//首页:快速申请
        $api->post('evaluate','Mobile\Client\IndexController@evaluate');//首页:申请：评价列表
        $api->get('average/{id}','Mobile\Client\IndexController@average');//首页：评价列表:顶部信息
    });
    $api->group(['prefix'=>'mobile/client/loan','middleware'=>'App\Http\Middleware\CheckToken'],function ($api)
    {//C端:贷款
        $api->get('/','Mobile\Client\LoanController@index');//貸款：首頁
        $api->get('config','Mobile\Client\LoanController@config');//貸款：搜索配置
        $api->post('region','Mobile\Client\LoanController@region');//貸款：搜索:城市
        $api->post('search','Mobile\Client\LoanController@search');//貸款：搜索
    });
    $api->group(['prefix'=>'mobile/client/article','middleware'=>'App\Http\Middleware\CheckToken'],function ($api)
    {//C端:贷款
        $api->get('/','Mobile\Client\ArticleController@index');//咨询：首頁
    });
    $api->group(['prefix'=>'mobile/client/message','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api)
    {//C端:信息提醒
        $api->get('type/{type}','Mobile\Client\MessageController@message');//消息：列表
        $api->post('setRead','Mobile\Client\MessageController@setRead');//消息：已读设置
    });
    $api->group(['prefix'=>'mobile/client/member','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api)
    {//C端:我的
        $api->get('point','Mobile\Client\MemberController@point');//积分：首页
        $api->get('pointList','Mobile\Client\MemberController@pointList');//积分：流水
        $api->post('feedback','Mobile\Client\MemberController@feedback');//我的设置：意见反馈
        $api->post('document','Mobile\Client\MemberController@document');//我的:审核：资料上传
        $api->get('authDocument','Mobile\Client\MemberController@authDocument');//我的：认证情况
        $api->post('favoriteList','Mobile\Client\MemberController@favoriteList');//我的：收藏
        $api->post('setFavorite','Mobile\Client\MemberController@setFavorite');//我的：收藏
        $api->post('history','Mobile\Client\MemberController@history');//我的：订单
    });
    /***********Mobile Api End************/

    $api->group(['prefix'=>'mobile/business','namespace'=>'Mobile\Business','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckLogin']],function ($api)
        {//移动端-信贷经理
            $api->post('index','IndexController@index');//首页
            $api->post('index','IndexController@index');//首页
            $api->get('order/grabConfig','OrderController@grabConfig');//抢单搜索条件
            $api->post('order/index','OrderController@index');//抢单首页
            $api->post('order/detail','OrderController@detail');//抢单详情
            $api->post('manager/submitProfile','ManagerController@submitProfile');//提交认证资料
            $api->get('manager/profile','ManagerController@profile');//认证资料展示
            $api->post('score/index','ScoreController@index');//积分首页
        });
    $api->group(['prefix'=>'mobile/business','namespace'=>'Mobile\Business','middleware'=>['App\Http\Middleware\CheckToken','App\Http\Middleware\CheckManager']],function ($api)
        {//移动端-信贷经理
            $api->post('order/checkPurchase','OrderController@checkPurchase');//检查抢单资格
            $api->post('order/purchase','OrderController@purchase');//确认支付
            $api->post('order/junkAttr','OrderController@junkAttr');//发布甩单时展示属性
            $api->post('order/junkPublish','OrderController@junkPublish');//发布甩单
            $api->post('order/junkList','OrderController@junkList');//甩单列表
            $api->post('order/junkDetail','OrderController@junkDetail');//甩单列表
            $api->post('order/junkAgain','OrderController@junkAgain');//甩单列表
            $api->post('shop/index','ShopController@index');//店铺首页
            $api->post('shop/showCreate','ShopController@showCreate');//创建店铺界面信息展示
            $api->post('shop/create','ShopController@create');//创建店铺
            $api->post('shop/order','ShopController@customerOrder');//客户订单
            $api->post('shop/orderDetail','ShopController@customerOrderDetail');//客户订单详情
            $api->post('shop/customerDetail','ShopController@customerDetail');//客户订单详情
            $api->post('shop/report','ShopController@report');//客户订单举报
            $api->post('shop/orderRefuse','ShopController@customerOrderRefuse');//客户订单驳回
            $api->post('shop/orderProcess','ShopController@customerOrderProcess');//客户订单执行办理流程
            $api->post('shop/orderCommentLabel','ShopController@customerOrderCommentLabel');//展示评价界面印象标签
            $api->post('shop/orderComment','ShopController@customerOrderComment');//提交评价
            $api->post('shop/orderJunk','ShopController@customerOrderJunk');//甩单
            $api->post('product/index','ProductController@index');//未代理的产品列表
            $api->post('product/myProduct','ProductController@myProduct');// 已代理产品列表
            $api->post('product/detail','ProductController@detail');//未代理的产品列表
            $api->post('product/setAgent','ProductController@setAgent');//产品代理：取消|添加
            $api->get('product/otherType','ProductController@otherType');//产品代理：取消|添加
        });

    });
/*2.0*/
//$api->version('v',['namespace'=>'App\Api\v1\Controllers'], function ($api) {
//    $api->group(['prefix'=>'v2'],function ($api) {
//        $api->get('index','IndexController@index');
//    });
//$api->get('department/test', [
//    'middleware' => 'api.throttle',
//    'limit' => 2, //频次
//    'expires' => 1, //分钟
//    'uses' => 'DepartmentController@test'
//]);
//});