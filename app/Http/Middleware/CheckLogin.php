<?php

namespace App\Http\Middleware;

use App\Api\Models\UserModel;
use Closure;
use Illuminate\Support\Facades\Redis;

class CheckLogin
{
    /**
     * Handle an incoming request.
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(Redis::exists($request->header('deviceid'))) {
            //根据键名获取键值
            $array_token = Redis::get($request->header('deviceid'));
            $token = json_decode($array_token, true);
            if($token['uid']) {
                $request->merge(['uid'=>$token['uid']]);
                return $next($request);
            }else{
                $result['status'] = 4004;
                $result['data'] = null;
                $result['msg'] = '未登录';
                return $result;
            }
        }else{
            $result['status'] = 4001;
            $result['data'] = null;
            $result['msg'] = 'token失效';
            return $result;
        }
    }
}
