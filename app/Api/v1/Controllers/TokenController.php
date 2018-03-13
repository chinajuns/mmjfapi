<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\TokenModel;
use App\Api\Models\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class TokenController extends BaseController
{
    /**
     * @param Request $request
     * @return array
     * Token 生成
     */
    public function generateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required',
            'sys_version' => 'required',
            'app_version' => 'required',
            'deviceid' => 'required'
        ]);
        $data = array();
        $token = $this->verifyToken();//new
        if (!$validator->fails()) {
            //1:数据库查询
            $res = TokenModel::where(['deviceid'=>$request->deviceid])->get();
            $data['deviceid'] = $request->deviceid;
            $data['platform'] = $request->platform;
            $data['sys_version'] = $request->sys_version;
            $data['app_version'] = $request->app_version;
            if (!count($res)) {//不存在相应参数
                $id = TokenModel::create($data);
                if (count($id))//成功
                {
                    $this->setRedis(array('key' => $request->deviceid, 'val' => array('uid' => '', 'token' => $token)));
                    $this->data = array('uid' => '', 'token' => $token);
                } else {
                    $this->setstatusCode(500);
                    $this->setMessage('fail');
                }
            } elseif (!($this->getRedis($request->deviceid))) {//2:redis过期
                //更换版本
                $where = array('deviceid' => $request->deviceid, 'sys_version' => $request->sys_version, 'app_version' => $request->app_version, 'platform' => $request->platform);
                $exists = TokenModel::where($where)->get();
                if (count($exists))
                {
                    $this->setRedis(array('key' => $request->deviceid, 'val' => array('uid' => '', 'token' => $token)));
                    $this->data = array('uid' => '', 'token' => $token);
                }
                $this->setRedis(array('key' => $request->deviceid, 'val' => array('uid' => '', 'token' => $token)));
                $this->data = array('uid' => '', 'token' => $token);
            } else {//token
                //是否包含token
                $data = json_decode($this->getRedis($request->deviceid)['value'],true);
                if($data['token']) {//不包含token
                    //3:直接取出
                    $redis = $this->getRedis($request->deviceid);
                    $this->data = json_decode($redis['value'], true);
                }else{
                    //4,更新token 保留uid
//                    $where = array('deviceid' => $request->deviceid, 'sys_version' => $request->sys_version, 'app_version' => $request->app_version, 'platform' => $request->platform);
//                    $exists = TokenModel::where($where)->get();
                    $this->setRedis(array('key' => $request->deviceid, 'val' => array('uid' => $data['uid'], 'token' => $token)));
                    $this->data = array('uid' => $data['uid'], 'token' => $token);
                    UserModel::where(['id'=>$data['uid']])->update(['token'=>$token]);
                }
            }
            $this->data['api_url'] = 'http://api.com/';
            $this->data['image_url'] = 'http://image.com/';
        } else {//参数不全
            $this->setstatusCode(4002);
            $this->setMessage('fail');
        }
        return $this->result($this->data);
    }

    /**
     * @param Request $request
     * @param $token
     * @param $deviceid
     * @return bool
     * 图片上传
     */
    public function imgUpload(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'image'=>'required',
//            'uid'=>'required'
        ]);
        if (!$validator->fails()) {//
            $images = $this->uploadImg($request);
            if($images['status']=='success')
            {
                $this->setstatusCode(200);
                $result['msg'] = 'success';
                $result['data'] = $images;
            }
            else
            {
                $this->setstatusCode(500);
                $result['msg'] = '服务器错误';
                $result['data'] = null;
            }
        }
        else
        {
            $this->setstatusCode(4002);
            $result['msg'] = '参数错误/不全';
            $result['data'] = null;
        }
        $result['status'] = $this->getstatusCode();
        return $result;
    }
}
