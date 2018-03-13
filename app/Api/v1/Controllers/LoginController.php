<?php

namespace App\Api\v1\Controllers;


use App\Api\Models\IntegralListModel;
use App\Api\Models\JunkModel;
use App\Api\Models\NoticeModel;
use App\Api\Models\RegionModel;
use App\Api\Models\UserModel;
use App\Api\Models\VerifyListModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
class LoginController extends BaseController
{
    use AuthenticatesUsers;
    /**
     * @param Request $request
     * @return mixed
     * 获取验证码
     */
    public function getVerifyCode(Request $request){
        $deviceid = $request->header('deviceid');
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|min:11',
        ]);
        if (!$validator->fails()) {
            if (preg_match('/^1[34578]{1}\d{9}$/', $request->mobile))
            {
                //判断验证码发送次数
                $counts = VerifyListModel::where([['mobile','=',$request->mobile],['create_time','>',strtotime(date('Ymd',time()))]])->count('id');
                if($counts < VerifyListModel::NUMBER){
                //生成验证码
                    $strCode = random_int(100000,999999);
                //发送验证码
                    if($request->type == 'register') {
                        $content = '尊敬的用户，您的注册验证码是' . $strCode . '，10分钟内输入有效，请勿泄露给他人！';
                    }elseif($request->type == 'reset'){
                        $content = '尊敬的用户，您正在申请重置账户密码，验证码是' . $strCode . '，10分钟内输入有效，请勿泄露给他人！';
                    }elseif($request->type == 'verify_code'){//动态验证码登录
                        $content = '尊敬的用户，您的动态验证码是' . $strCode . '，10分钟内输入有效，请勿泄露给他人！';
                    }
                    else{
                        $content = '尊敬的用户，您正在申请重置账户密码，验证码是' . $strCode . '，10分钟内输入有效，请勿泄露给他人！';
                    }
                    $send = $this->sendSmsCode($request->mobile,$content);
                    if($send){
                        //保存验证码：redis
                        Redis::setex('verify:'.$request->mobile,VerifyListModel::EXPIRE_TIME,$strCode);
                        //记录验证码
                        VerifyListModel::create(['mobile'=>$request->mobile,'code'=>$strCode,'create_time'=>time(),'deviceid'=>$deviceid]);
                    }
                    else{
                        $this->setstatusCode(4006);
                        $this->setMessage('系统错误');
                    }
                }else{
                    $this->setstatusCode(4006);
                    $this->setMessage('数量上限');
                }
            }else{
                $this->setstatusCode(4006);
                $this->setMessage('手机号错误');
            }
        }else{
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 验证码检查
     */
    public function checkVerifyCode(Request $request){
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|min:11',
            'code' => 'required|min:6',
        ]);
        if (!$validator->fails() && preg_match('/^1[34578]{1}\d{9}$/', $request->mobile)) {
                //检查验证码
                $exist = $this->getRedis('verify:'.$request->mobile);
                if($exist){
                    if($request->code == $exist['value'])
                    {//清楚验证码
                        Redis::del('verify:'.$request->mobile);
                    }else{
                        $this->setstatusCode(4003);
                        $this->setMessage('验证码错误');
                    }
                }else{
                    //暂无验证码
                    $this->setstatusCode(4003);
                    $this->setMessage('验证码错误');
                }
        }else{
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 用户注册
     */
    public function register(Request $request){
        $deviceid = $request->header('deviceid');
        $validator = Validator::make($request->all(), [
            'mobile' => 'required',
            'password'=>'required',//base64解密
            'type' => 'required',
            'platform'=>'required'
        ]);
        if (!$validator->fails()) {
//            $mobile = base64_decode($request->mobile);
//            $password = base64_decode($request->password);
            if(preg_match('/^1[34578]{1}\d{9}$/', $request->mobile))
            {//type:1用户|2经理
                $exist = UserModel::where(['mobile'=>$request->mobile])->first();
                if(!count($exist)) {
                    DB::beginTransaction();
                    try {
                        $res = UserModel::create(['mobile' => $request->mobile, 'password' => Hash::make($request->password), 'sign_from' => $request->platform, 'create_time' => time(), 'last_login_time' => time(), 'last_login_ip' => $request->getClientIp(),'type'=>$request->type,'token'=>$request->header('token'),'header_img'=>$request->url,'group_id'=>$request->platform]);
                        //积分添加
                        IntegralListModel::create(['user_id'=>$res->id,'integral_id'=>4,'number'=>30,'total'=>30,'create_time'=>time()]);
                        UserModel::where(['id'=>$res->id])->update(['integral'=>30]);
                        $this->updateToken($deviceid, $res->id);
                        NoticeModel::create(['to_uid'=>$res->id,'title'=>'系统消息','content'=>'注册成功！','is_success'=>1,'type'=>2,'create_time'=>time()]);
                        //返回用户资料信息
                        $data['id'] = $res->id;
                        $this->setData($data);
                        DB::commit();
                    } catch (\Exception $e){
                        DB::rollback();//事务回滚
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                }else{
                    $this->setstatusCode(4010);
                    $this->setMessage('重复注册');
                }
            }else{
                $this->setstatusCode(4002);
                $this->setMessage('参数错误');
            }
        }else{
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required',
            'password' => 'required',//base64解密
            'type' => 'required',
            'platform' => 'required',
//            'login_way' => 'required',//登录方式general|verifyCode
        ]);
        $deviceid = $request->header('deviceid');
        $token = $request->header('token');
//        $mobile = base64_decode($request->mobile);
//        $password = base64_decode($request->password);
        if (!$validator->fails()) {
                if (preg_match('/^1[346578]{1}\d{9}$/', $request->mobile))
                {//type:1用户|2经理
                    if(Auth::attempt(['mobile' => $request->mobile, 'password' => $request->password,'type'=>$request->type,'is_disable' => 1]))
                    {
                        $info = UserModel::where(['mobile'=>$request->mobile,'is_disable'=>1,'type'=>$request->type])
                            ->select(['id','username','mobile','sex','header_img','is_auth','integral','last_login_time','type','last_deviceid','token'])
                            ->first();
                        if(count($info))
                        {
                           if($info->last_deviceid && $info->token){
                               $this->updateToken($info->last_deviceid, '');
                           }
                           UserModel::where(['id'=>$info->id])->update(['token'=>$token,'last_login_time'=>time(),'last_login_ip'=>$request->getClientIp(),'last_deviceid'=>$deviceid]);
                            $this->updateToken($deviceid, $info->id);
                            $info->mobile = substr($info->mobile,0,3).'****'.substr($info->mobile,7);
                            $this->setData($info);
                        }
                        else{
                            $this->setstatusCode(500);
                            $this->setMessage('服务器错误');
                        }
                    }else{
                        $this->setstatusCode(4005);
                        $this->setMessage('账户|密码错误');
                    }
                }else{
                    $this->setstatusCode(4002);
                    $this->setMessage('参数错误');
                }
            } else {//动态码登录
                if($request->login_way == 'verify_code')
                {
                    //code,mobile,type
                    $res = $this->checkVerifyCode($request);
                    if($res['status'] == 200)
                    {//验证通过://判断新用户
                        $exist = UserModel::where(['mobile'=>$request->mobile])
                            ->select(['id','username','mobile','sex','header_img','is_auth','integral','last_login_time','type','is_disable','last_deviceid','token'])
                            ->first();
                        if(count($exist))
                        {
                            if($exist->type == $request->type && $exist->is_disable == 1)
                            {
                                if($exist->last_deviceid && $exist->token){
                                    $this->updateToken($exist->last_deviceid, '');
                                }
                                UserModel::where(['id' => $exist->id])->update(['token' => $token, 'last_login_time' => time(), 'last_login_ip' => $request->getClientIp(),'last_deviceid'=>$request->header('deviceid')]);
                                $this->updateToken($deviceid, $exist->id);
                                $exist->mobile = substr($exist->mobile,0,3).'****'.substr($exist->mobile,7);
                                $this->setData($exist);
                            }else{
                                $this->setstatusCode(5000);//用户类型不匹配
                                $this->setMessage('用户不匹配');
                            }
                        }else{
                            //新用户
                            $insert = UserModel::create(['username'=>'y'.substr($request->mobile,0,3).'****'.substr($request->mobile,7),'mobile'=>$request->mobile,'type'=>$request->type,'create_time'=>time(),'sign_from'=>4,'last_login_time'=>time(),'last_login_ip'=>$request->getClientIp(),'token'=>$token,'last_deviceid'=>$request->header('deviceid')]);
                            if(count($insert))
                            {
                                $info = UserModel::where(['id'=>$insert->id])
                                    ->select(['id','username','mobile','sex','header_img','is_auth','integral','last_login_time','type'])
                                    ->first();
                                $info->mobile = substr($info->mobile,0,3).'****'.substr($info->mobile,7);
                                $this->setData($info);
                            }else{
                                $this->setstatusCode(500);
                                $this->setMessage('服务器错误');
                            }
                        }
                    }
                    else{
                        $this->setstatusCode(4003);
                        $this->setMessage('动态码错误');
                    }
                }else{
                    $this->setstatusCode(4002);
                    $this->setMessage('参数错误');
                }
            }
            return $this->result();
        }

    /**
     * @param $deviceid
     * @return mixed
     *  注销登录，删除token->uid
     */
    public function logout(Request $request)
    {
        $uid = $this->checkUid($request->header('deviceid'));
        $this->setData(['uid'=>$uid,'deviceid'=>$request->header('deviceid')]);
        if($uid)
        {
            $this->updateToken($request->header('deviceid'), '');
            UserModel::where(['id' => $uid])->update(['token' => '']);
        }
        else
        {
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }


    /**
     * @param Request $request
     * @return mixed
     * 修改密码
     */
    public function resetPassword(Request $request)
    {
        $uid = $this->checkUid($request->header('deviceid'));
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|between:6,20',
            'password' => 'required|between:6,20|confirmed',
            'password_confirmation' => 'required|between:6,20'
        ]);
        if(!$validator->fails())
        {
            if (Auth::attempt(['id' => $uid, 'password' => $request->old_password])) {
                $res = UserModel::where(['id'=>$uid])
                    ->update(['password'=>Hash::make($request->password),'token'=>'']);
                $this->updateToken($request->header('deviceid'),'');
                if(!$res){
                    $this->setMessage('系统错误');
                    $this->setstatusCode(500);
                }
            }
            else{
                $this->setMessage('旧密码不正确');
                $this->setstatusCode(4002);
            }
        }
        else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 忘记密码
     */
    public function forgot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|between:6,20|confirmed',
            'password_confirmation' => 'required|between:6,20',
            'mobile' => 'required'
        ]);
        if(!$validator->fails())
        {
            $res = UserModel::where(['mobile'=>$request->mobile])
                ->update(['password'=>Hash::make($request->password),'token'=>'']);
            if(!$res){
                $this->setMessage('系统错误');
                $this->setstatusCode(500);
            }
        }
        else
        {
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    public function testAccount(Request $request){
        $exist = UserModel::where(['mobile'=>$request->mobile])->first();
        if(!count($exist)) {
            $res = UserModel::create(['mobile' => $request->mobile, 'password' => Hash::make($request->password), 'sign_from' => $request->platform, 'create_time' => time(), 'last_login_time' => time(), 'last_login_ip' => $request->getClientIp(), 'type' => $request->type, 'token' => $request->header('token'), 'header_img' => $request->url, 'group_id' => $request->platform,'integral'=>10000]);
            NoticeModel::create(['to_uid'=>$res->id,'title'=>'系统消息','content'=>'注册成功！','is_success'=>'1','type'=>2,'create_time'=>time()]);
            $this->setData($res);
        }else{
            $this->setMessage('已存在该号码');
            $this->setstatusCode(4010);
        }
        return $this->result();
    }

    public function checkOrder()
    {
        $res = JunkModel::where([['expire_time','>',0],['expire_time','<',time()],['status','<>',3],['is_check','=',2]])->get(['id']);
        if(count($res))
        {
            foreach ($res as $item)
            {
                JunkModel::where(['id'=>$item->id])->update(['status'=>2,'update_time'=>time()]);
            }
        }
    }
}
