<?php

namespace App\Http\Middleware;

use App\Api\Models\UserModel;
use Closure;
use Illuminate\Support\Facades\Redis;

class checkManager
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
        if(Redis::exists($request->header('deviceid'))) {
            //根据键名获取键值
            $array_token = Redis::get($request->header('deviceid'));
            $token = json_decode($array_token, true);
            if($token['uid']) {
                //检查信贷经理
//                $is_manager = UserModel::where(['is_auth'=>3,'type'=>2,'id'=>$token['uid']])->first();
                $is_manager=UserModel::from('user as u')
                    ->select('l.id as loaner_id','l.loanername','u.*')
                    ->leftJoin('loaner as l','l.user_id','=','u.id')
                    ->where(['u.is_auth'=>3,'u.type'=>2,'u.id'=>$token['uid']])->first();
                if(count($is_manager))
                {
                    $request->merge(['uid'=>$token['uid'],'loaner_id'=>$is_manager->loaner_id,'loanername'=>$is_manager->loanername,'loaner_mobile'=>$is_manager->mobile]);
                    return $next($request);
                }
                else{
                    $result['status'] = 5000;
                    $result['data'] = null;
                    $result['msg'] = '没有找到符合条件的经理信息';
                    return $result;
                }
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
