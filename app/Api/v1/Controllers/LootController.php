<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\IntegralListModel;
use App\Api\Models\IntegralModel;
use App\Api\Models\JunkModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanExamineModel;
use App\Api\Models\LoanModel;
use App\Api\Models\LootModel;
use App\Api\Models\RegionModel;
use App\Api\Models\ShopModel;
use App\Api\Models\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LootController extends BaseController
{
    /**
     * 抢单:列表
     */
    public function search(Request $request)
    {
            /*搜索条件*/
            $condition = $request->ids ? $request->ids : '';
            $region_id = $request->region_id ? $request->region_id : '';
            $province_id = $request->province_id ? $request->province_id : '';
            $city_id = $request->city_id ? $request->city_id : '';
            if($condition){
                if(strpos($condition,','))
                {
                    $con = explode(',',$condition);
                    $where = [['is_check','=', 2], ['status','=', 1],['apply_information','like','%'.$con[0].'%'],['apply_information','like','%'.$con[1].'%'],['expire_time','>',time()]];
                }else{
                    $where = [['is_check','=', 2], ['status','=', 1],['apply_information','like','%'.$condition.'%'],['expire_time','>',time()]];
                }
                if($region_id||$province_id||$city_id)
                {
                    if($province_id && !$city_id && !$region_id){
                        $where = array_merge($where,[['province_id','=',$province_id]]);
                    }
                    if($province_id && $city_id && !$region_id){
                        $where = array_merge($where,[['city_id','=',$city_id]]);
                    }
                    if($province_id && $city_id && $region_id){
                        $where = array_merge($where,[['region_id','=',$region_id]]);
                    }
                    if(!$province_id && $city_id && !$region_id){
                        $where = array_merge($where,[['city_id','=',$city_id]]);
                    }
                }
                //
                if($request->is_vip==1)
                {
                    $where = array_merge($where,[['is_vip','=',1]]);
                }elseif($request->is_vip == 2)
                {
                    $where = array_merge($where,[['is_vip','=',0]]);
                }
            }else{
                if($request->is_vip==1)
                {
                    $where = [['is_check','=',2],['status','=',1],['is_vip','=',1],['expire_time','>',time()]];
                }elseif($request->is_vip == 2)
                {
                    $where = [['is_check','=',2],['status','=',1],['is_vip','=',0],['expire_time','>',time()]];
                }else{
                    $where = [['is_check','=',2],['status','=',1],['expire_time','>',time()]];
                }
                if($region_id||$province_id||$city_id)
                {
                    if($province_id && !$city_id && !$region_id){
                        $where = array_merge($where,[['province_id','=',$province_id]]);
                    }
                    if($province_id && $city_id && !$region_id){
                        $where = array_merge($where,[['city_id','=',$city_id]]);
                    }
                    if($province_id && $city_id && $region_id){
                        $where = array_merge($where,[['region_id','=',$region_id]]);
                    }
                    if(!$province_id && $city_id && !$region_id){
                        $where = array_merge($where,[['city_id','=',$city_id]]);
                    }
                }
            }
//        dd($where);
//        DB::connection()->enableQueryLog();
            $list = JunkModel::where($where)
                ->with(['LoanType'=>function($query){
                    $query->select(['id','attr_value']);
                },'region'=>function($query){
                    $query->select(['id','name']);
                },'limit'=>function($query){
                    $query->select(['id','attr_value']);
                }])
                ->select(['id','name','age','region_id','loan_type','apply_number','is_check','mobile','apply_information','loan_type','price','description','source_id','source_table','create_time','time_limit','is_vip','loaner_id','is_marry','expire_time','city_id'])
                ->orderBy('id','desc')
                ->paginate($this->pageSize);
//        $log = DB::getQueryLog();
//                    dd($log);
            if(count($list))
            {
                $list = $list -> toArray();
                foreach($list['data'] as &$item)
                {
                        $arr = [];//
                        $item['type'] = $item['loan_type']['attr_value'];
                        $item['time_limit'] = $item['limit']['attr_value'];
                        $item['is_auth'] = 1;
                    ///
                    $arr[] = ['attr_value'=>(date('Y') - substr($item['age'],0,strpos($item['age'],'-')+1)).'岁','attr_key'=>'出生'];
                    $arr[] = ['attr_value'=>$item['region']['name'],'attr_key'=>'现居'];
                    $arr[] = ['attr_value'=>substr($item['mobile'],0,3).'****'.substr($item['mobile'],7),'attr_key'=>'电话'];
                        $info = LoanerAttrModel::whereIn('id',explode(',',$item['apply_information']))
                            ->with('parent')
                            ->get();
                        if(count($info))
                        {
                            foreach($info as $value)
                            {
                                $arr[] = ['attr_value' => $value->attr_value,'attr_key'=>$value->parent['attr_value']];
                            }

                        }
                        $item['info'] = $arr;
                        unset($item['loan_type']);
                        unset($item['region']);
                        unset($item['limit']);
                        unset($item['source_id']);
                        unset($item['source_table']);
                        unset($item['source']);
                        unset($item['mobile']);
                    }
                $this->setData($list);
            }else{
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 抢单
     */
    public function grab(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',//甩单id
        ]);
        if (!$validator->fails())
        {
            $uid = $request->uid;
            $loaner_id = $request->loaner_id;
            $shop = ShopModel::where(['user_id' => $uid, 'check_result' => 2, 'status' => 1])
                    ->first();
            $is_mine = JunkModel::where(['id'=>$request->id,'loaner_id'=>$request->loaner_id])
                ->first();//判断自己id单子
            if(!count($is_mine))
            {
//                if (count($shop))
//                {
                    //信贷经理信息
                    $loanerInfo = LoanerModel::find($request->loaner_id,['province_id']);
                    //单子信息
                    $junkInfo = JunkModel::where([['id', '=', $request->id], ['is_check', '=', 2], ['status', '=', 1], ['expire_time', '>', time()]])
                        ->first(['id', 'price', 'loaner_id', 'mobile', 'description', 'region_id', 'age', 'time_limit', 'apply_number', 'loan_type', 'apply_information', 'name','city_id','province_id']);
                    if (count($junkInfo)) {
                        if($junkInfo->province_id == $loanerInfo->province_id) {

                            $userInfo = UserModel::where(['id' => $uid, 'is_auth' => 3])
                                ->first(['id', 'integral', 'username']);
                            if ($userInfo->integral >= $junkInfo->price) {
                                DB::beginTransaction();
                                try {
                                    //抢单记录
                                    LootModel::create(['user_id' => $uid, 'loaner_id' => $loaner_id, 'junk_id' => $junkInfo->id, 'status' => 1, 'create_time' => time()]);
                                    //添加到订单表
                                    $orderNo = date("YmdHis") . rand(100000, 999999);
                                    $res = LoanModel::create(['name' => $junkInfo->name, 'age' => $junkInfo->age, 'loan_account' => $orderNo, 'loaner_id' => $loaner_id, 'apply_number' => $junkInfo->apply_number, 'type' => 2, 'region_id' => $junkInfo->region_id, 'apply_information' => $junkInfo->apply_information, 'loan_type' => $junkInfo->loan_type, 'description' => $junkInfo->description, 'mobile' => $junkInfo->mobile, 'create_time' => time(), 'process' => 11, 'time_limit' => $junkInfo->time_limit, 'junk_id' => $junkInfo->id, 'city_id' => $junkInfo->city_id]);
                                    LoanExamineModel::create(['loan_id' => $res->id, 'loaner_id' => $loaner_id, 'process' => 11, 'create_time' => time(), 'describe' => '申请提交成功！']);
                                    //更新原订单状态
                                    JunkModel::where(['id' => $request->id])
                                        ->update(['status' => 3, 'update_time' => time()]);
                                    //积分++
                                    //甩单信贷经理的uid
                                    $junkUserInfo = LoanerModel::where(['id' => $junkInfo->loaner_id])
                                        ->with(['user' => function ($query) {
                                            $query->select(['id', 'integral']);
                                        }])
                                        ->first(['user_id']);
                                    UserModel::where(['id' => $junkUserInfo->user_id])
                                        ->increment('integral', $junkInfo->price);
                                    IntegralListModel::create(['user_id' => $junkUserInfo->user_id, 'integral_id' => 8, 'number' => $junkInfo->price, 'total' => $junkUserInfo->user['integral'] + $junkInfo->price, 'create_time' => time(), 'description' => '甩单收入' . $junkInfo->price, 'desc' => '甩单id：' . $junkInfo->id]);
                                    //抢单信贷经理：
                                    //积分--
                                    UserModel::where(['id' => $uid])
                                        ->decrement('integral', $junkInfo->price);
                                    IntegralListModel::create(['user_id' => $uid, 'integral_id' => 9, 'number' => '-' . $junkInfo->price, 'total' => $userInfo->integral - $junkInfo->price, 'create_time' => time(), 'description' => '抢单消费' . $junkInfo->price, 'desc' => '甩单id：' . $junkInfo->id]);
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollback();//事务回滚
                                }
                            } else {
                                $this->setMessage('积分不足');
                                $this->setstatusCode(4013);
                            }
                        }else{
                            $this->setMessage('不能跨省抢单');
                            $this->setstatusCode(5001);
                        }
                    } else {//异常
                        $this->setMessage('订单异常');
                        $this->setstatusCode(5000);
                    }
//                } else {
//                    $this->setMessage('暂无店铺,不能抢单');
//                    $this->setstatusCode(5000);
//                }
            }else{
                $this->setMessage('不能抢自己的甩单');
                $this->setstatusCode(5001);
            }
            }else{
                $this->setMessage('参数错误');
                $this->setstatusCode(5000);
            }
        return $this->result();
    }

    public function config(){
        $config = LoanerAttrModel::where(['type'=>'pc_loot'])
            ->with(['values'=>function($query){
                $query->select(['id','pid','attr_value']);
            }])
            ->get(['id','attr_value']);
        $config = $config->toArray();
       return $this->setData($config)->result();
    }
    /**
     * @param $id
     * @return mixed
     * 抢单详情
     */
    public function productShow($id)
    {
            $item = JunkModel::with(['LoanType'=>function($query){
                $query->select(['id','attr_value']);
            },'region'=>function($query){
                $query->select(['id','name']);
            },'limit'=>function($query){
                $query->select(['id','attr_value']);
            }])
                ->where(['status'=>1,'is_check'=>2])
                ->find($id,['id','loan_type','region_id','age','name','time_limit','apply_number','apply_information','job_information','description','source_id','price','is_vip','mobile','city_id','create_time']);
            if(count($item))
            {
                $id = $item->id;
//                $price = $item->price;
//                $mobile = $item->mobile;
//                $is_vip = $item->is_vip;
//                if($item->source){
//                    $arr = [];
//                    $item = $item->source;
//                    $item['id'] = $id;
//                    $item['price'] = $price;
//                    $item['mobile'] = $mobile;
//                    $item['is_vip'] = $is_vip;
//                    if($item['user_id']) {
//                        $user = UserModel::find($item['user_id'], ['id', 'username', 'is_auth']);
//                        $item['name'] = $user->username;
//                    }
//                    $item['city'] = RegionModel::find($item['region_id'], ['id', 'name'])->name;
//                    $item['type'] = LoanerAttrModel::find($item['loan_type'], ['id', 'attr_value'])->attr_value;
//                    $item['time_limit'] = LoanerAttrModel::find($item['time_limit'],['id', 'attr_value'])->attr_value;
//                    $info = LoanerAttrModel::whereIn('id', explode(',', $item['apply_information']))
//                        ->with('parent')
//                        ->get();
//                    if (count($info)) {
//                        foreach ($info as $value) {
//                            $arr[] = ['attr_value' => $value->attr_value, 'attr_key' => $value->parent['attr_value']];
//                        }
//                        ////
//                        $job_information = LoanerAttrModel::whereIn('id', explode(',', $item['job_information']))
//                            ->with('parent')
//                            ->get();
//                        $job = [];
//                        if (count($job_information)) {
//                            foreach ($job_information as $value) {
//                                $job[] = ['attr_value' => $value->attr_value, 'attr_key' => $value->parent['attr_value']];
//                            }
//                        }
//                        ////
//                        $basic[] = ['attr_value'=>$item['age'],'attr_key'=>'出生'];
//                        $basic[] = ['attr_value'=>$item['region']['name'],'attr_key'=>'现居'];
//                        $basic[] = ['attr_value'=>substr($item['mobile'],0,3).'****'.substr($item['mobile'],7),'attr_key'=>'电话'];
//                        $item['basic'] = $basic;
//                        $item['job'] = $job ? $job : null;
//                        $item['info'] = $arr ? $arr : null;
//                    }
//                    unset($item['loan_type']);
//                    unset($item['region']);
//                    unset($item['region_id']);
//                    unset($item['age']);
//                    unset($item['city']);
//                    unset($item['limit']);
//                    unset($item['mobile']);
//                    unset($item['apply_information']);
//                    unset($item['job_information']);
//                    $this->setData($item);
//                }else {
                    $arr = [];
                    $item['type'] = $item['LoanType']['attr_value'];
                    $item['city'] = $item['region']['name'];
                    $item['type'] = $item['LoanType']['attr_value'];
                    $item['time_limit'] = $item['limit']['attr_value'];
                    $info = LoanerAttrModel::whereIn('id', explode(',', $item['apply_information']))
                        ->with('parent')
                        ->get();
                    if (count($info)) {
                        foreach ($info as $value) {
                            $arr[] = ['attr_value' => $value->attr_value, 'attr_key' => $value->parent['attr_value']];
                        }
                        $item['info'] = $arr;
                    }
                    $basic[] = ['attr_value'=>(date('Y') - substr($item['age'],0,strpos($item['age'],'-')+1)).'岁','attr_key'=>'出生'];
                    $basic[] = ['attr_value'=>$item['region']['name'],'attr_key'=>'现居'];
                    $basic[] = ['attr_value'=>substr($item['mobile'],0,3).'****'.substr($item['mobile'],7),'attr_key'=>'电话'];
                    ////
                    $job_information = LoanerAttrModel::whereIn('id', explode(',', $item['job_information']))
                        ->with('parent')
                        ->get();
                    $job = [];
                    if (count($job_information)) {
                        foreach ($job_information as $value) {
                            $job[] = ['attr_value' => $value->attr_value, 'attr_key' => $value->parent['attr_value']];
                        }
                    }
                    ////
                    $item['basic'] = $basic;
                    $item['job'] = $job ? $job : null;
                    unset($item['loan_type']);
                    unset($item['region']);
                    unset($item['city']);
                    unset($item['age']);
                    unset($item['limit']);
                    unset($item['apply_information']);
                    unset($item['job_information']);
                    unset($item['mobile']);
                    $this->setData($item);
//                }
            }else{
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        return $this->result();
    }
}
