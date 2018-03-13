<?php

namespace App\Api\v1\Mobile\Business;

use App\Api\Models\LoanerModel;
use App\Api\Models\RegionModel;
use App\Api\Models\UserAuthModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ManagerController extends BaseController
{
    /**
     * @param Request $request
     * 信贷经理认证
     */
    public function submitProfile(Request $request){
        $uid = $request->uid;
        $validator = Validator::make($request->all(), [
            'true_name' => 'required',//真实名字
            'cert_number' => 'required',//身份证号
//            'address' => 'required',//城市
            'mechanism_type' => 'required',//机构类型
            'mechanism' => 'required',//机构名称
            'department' => 'required',//部门
            'front_cert' => 'required',//身份证正面
            'back_cert' => 'required',//背面
//            'work_card' => 'required',//工牌
//            'card' => 'required',//名片
//            'contract_page' => 'required',//合同签字页
//            'logo_personal' => 'required',//公司logo合影
            'lng'=>'required',//经度
            'lat'=>'required',//纬度
            'province_id'=>'required',
            'city_id'=>'required',
            'region_id'=>'required',
        ]);
        if(!$validator->fails()) {
            $exist = UserAuthModel::where(['user_id'=>$uid])
                ->first(['id','is_pass']);
            $info = UserModel::find($uid, ['mobile', 'header_img']);
            $header_img = $request->header_img ? $request->header_img :$info->header_img;
            if(count($exist))
            {
                if(in_array($exist->is_pass,[2,3]))//TODO::有认证记录
                {
                    $this->setstatusCode(4010);
                    $this->setMessage('重复提交');
                } else {//TODO::被拒后重新提交
                    $loaner = LoanerModel::where(['user_id' => $uid])
                        ->first(['id']);
                    DB::beginTransaction();
                    try {
                        UserAuthModel::where(['id' => $exist->id])
                            ->update(['true_name' => $request->true_name, 'user_id' => $uid, 'identity_number' => $request->cert_number, 'front_identity' => $request->front_cert, 'back_identity' => $request->back_cert, 'work_card' => $request->work_card,  'mechanism_type' => $request->mechanism_type, 'mechanism' => $request->mechanism, 'department' => $request->department, 'card' => $request->card, 'contract_page' => $request->contract_page, 'logo_personal' => $request->logo_personal, 'update_time' => time(),'is_pass'=>2,'province_id'=>$request->province_id,'city_id'=>$request->city_id,'region_id'=>$request->region_id,'type'=>2]);
                        LoanerModel::where(['id'=>$loaner->id])
                            ->update(['loanername' => $request->true_name, 'loanername_mobile' => $info->mobile, 'loaner_lng' => $request->lng, 'loaner_lat' => $request->lat, 'header_img' => $header_img, 'create_time' => time(), 'province_id'=>$request->province_id,'city_id'=>$request->city_id,'region_id'=>$request->region_id, 'user_id' => $uid, 'is_auth' => 2]);
                        UserModel::where(['id' => $uid])
                            ->update(['is_auth' => 2, 'update_time' => time(),'header_img' => $header_img]);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollback();//事务回滚
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                }
            }else{//无认证记录：直接添加
                DB::beginTransaction();
                try {
                    UserAuthModel::create(['true_name' => $request->true_name, 'user_id' => $uid, 'identity_number' => $request->cert_number, 'front_identity' => $request->front_cert, 'back_identity' => $request->back_cert, 'work_card' => $request->work_card, 'province_id'=>$request->province_id,'city_id'=>$request->city_id,'region_id'=>$request->region_id, 'mechanism_type' => $request->mechanism_type, 'mechanism' => $request->mechanism, 'department' => $request->department, 'card' => $request->card, 'contract_page' => $request->contract_page, 'logo_personal' => $request->logo_personal, 'create_time' => time(),'is_pass'=>2,'type'=>2]);
                    LoanerModel::create(['loanername' => $request->true_name, 'loanername_mobile' => $info->mobile, 'loaner_lng' => $request->lng, 'loaner_lat' => $request->lat, 'header_img' => $header_img, 'create_time' => time(), 'city_id'=>$request->city_id,'region_id'=>$request->region_id,  'user_id' => $uid, 'is_auth' => 2]);
                    UserModel::where(['id' => $uid])
                        ->update(['is_auth' => 2, 'update_time' => time()]);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();//事务回滚
                    $this->setstatusCode(500);
                    $this->setMessage('服务器错误');
                }
            }
        }
        else {
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * 认证成功资料
     */
    public  function profile(Request $request)
    {
        $is_auth = UserAuthModel::where(['user_id'=>$request->uid])
            ->with(['province','city','district','user'=>function($query){
                $query->select(['id','header_img']);
            }])
            ->first(['id','user_id','front_identity','back_identity','true_name','address','is_pass','province_id','city_id','region_id','work_card','card','contract_page','logo_personal','mechanism','mechanism_type','department','is_pass','identity_number']);
        if(count($is_auth))
        {
            $address = '';
            if($is_auth->province) $address =$is_auth->province['name'];
            if($is_auth->city) $address .= ','. $is_auth->city['name'];
            if($is_auth->district) $address .= ','. $is_auth->district['name'];
            $is_auth->address = trim($address,',');
            $is_auth->identity_number = substr($is_auth->identity_number,0,3).'************'.substr($is_auth->identity_number,15);
            $is_auth->header_img = $is_auth->user['header_img'];
            unset($is_auth->user);
            unset($is_auth->district);
            unset($is_auth->province);
            unset($is_auth->city);
            $this->setData($is_auth);
        }
        else
        {
            $this->setData(['is_pass'=>1]);//
        }
        return $this->result();
    }
}
