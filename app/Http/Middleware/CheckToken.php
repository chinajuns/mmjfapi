<?php

namespace App\Http\Middleware;

use App\Api\Models\UserModel;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;

class CheckToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $deviceid = $request->header('deviceid');
        $token = $request->header('token');
        if(Redis::exists($deviceid) && $token){
            //根据键名获取键值
            $data = [];
            $data['deviceid'] = $deviceid;
            $data['value'] = Redis::get($deviceid);
            $array_token = $data;
            if(json_decode($array_token['value'],true)['token'] == $token && ((json_decode($array_token['value'],true)['token']) || $token))
            {
                return $next($request);
            }
            else
            {
                $result['status'] = 4001;
                $result['data'] = null;
                $result['msg'] = 'fail';
                return $result;
            }
        }else{
            /*已经登录用户：查询相应的uid ： token：uid*/
            if($token)
            {
                $uid = UserModel::where(['token' => $token])->first(['id']);
                if (count($uid)) {
                    Redis::set($deviceid, json_encode(['uid' => $uid->id, 'token' => '']));
                }
            }
            $result['status'] = 4001;
            $result['data'] = null;
            $result['msg'] = 'fail';
            return $result;
        }
    }
}
