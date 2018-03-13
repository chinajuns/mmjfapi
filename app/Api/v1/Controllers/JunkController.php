<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\JunkModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductAttrValueModel;
use App\Api\Models\RegionModel;
use App\Api\Models\ShopModel;
use App\Api\Models\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class JunkController extends BaseController
{
    /**
     * @param Request $request
     * 发布甩单
     */
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',//贷款用户
            'apply_number' => 'required',//贷款金额
            'region_id' => 'required',//区域
            'job_information' => 'required',//申请信息:1,2,3
//            'assets_information' => 'required',//申请信息:1,2,3
            'age' => 'required',//年龄
            'loan_type' => 'required',//年龄
            'mobile'=>'required',
            'is_marry'=>'required',
            'time_limit'=>'required',
//            'describe'=>'required',
            'price'=>'required',
            'province_id'=>'required',
            'city_id'=>'required',
            'income'=>'required',
            'work_time'=>'required',
        ]);
        if (!$validator->fails()) {
            //查询工龄
            $job_information = $request->job_information;
            $in_come = $request->income.'元';
            $work_time = LoanerAttrModel::where(['attr_value'=>$request->work_time,'pid'=>48])->first();
            if(count($work_time)){
                $job_information = $job_information.','.$work_time->id;
            }else{
                $insert = LoanerAttrModel::create(['attr_value'=>$request->work_time,'pid'=>48]);
                $job_information = $job_information.','.$insert->id;
            }
            //查询收入
            $income = LoanerAttrModel::where(['attr_value'=>$in_come,'pid'=>49])->first();
            if(count($income)){
                $job_information = $job_information.','.$income->id;
            }else{
                $insert = LoanerAttrModel::create(['attr_value'=>$in_come,'pid'=>49]);
                $job_information = $insert->information.','.$insert->id;
            }
                $insert = JunkModel::create(['loaner_id'=>$request->loaner_id,'loaner_name'=>$request->loanername,'apply_number'=>$request->apply_number,'time_limit'=>$request->time_limit,'loan_type'=>$request->loan_type,'region_id'=>$request->region_id,'province_id'=>$request->province_id,'city_id'=>$request->city_id,'age'=>$request->age,'name'=>$request->name,'price'=>$request->price,'mobile'=>$request->mobile,'description'=>$request->describe,'create_time'=>time(),'job_information'=>$job_information,'apply_information'=>$request->assets_information,'is_marry'=>$request->is_marry]);
                if (!$insert) {
                    $this->setMessage('服务器错误');
                    $this->setstatusCode(500);
                }else{
                    $this->setData(['id'=>$insert->id]);
                }
                //*Redis back*//

            }else{
                $this->setMessage('参数错误');
                $this->setstatusCode(4002);
            }
        return $this->result();
    }

    /**
     * 产品类型
     */
    public function type(){
        $type = ProductAttrValueModel::where(['attr_id'=>5])
            ->get(['attr_value','id']);
        $this->setData($type);
        return $this->result();
    }

    /**
     * 甩单:列表
     */
    public function listProduct(Request $request){
        $uid = $request->uid;
        $loaner = LoanerModel::where(['user_id'=>$uid])->first(['id','loanername']);
        if(count($loaner))
        {
            if($request->type == 1)//审核
            {
                $where = ['status'=>1,'is_check'=>1,'loaner_id' => $loaner->id];
            }elseif($request->type == 2)//进行中
            {
                $where = ['status'=>1,'is_check'=>2,'loaner_id' => $loaner->id];
            }elseif($request->type == 3)//成交
            {
                $where = ['status'=>3,'loaner_id' => $loaner->id];
            }elseif($request->type == 4)//过期
            {
                $where = [['expire_time','>',0],['loaner_id','=',  $loaner->id],['expire_time','<',time()],['status','<>',3],['is_check','=',2]];
            }else{
                $where = ['loaner_id' => $loaner->id];
            }
            $list = JunkModel::where($where)
                ->with(['LoanType' => function ($query) {
                    $query->select(['id', 'attr_value']);
                }, 'region' => function ($query) {
                    $query->select(['id', 'name']);
                }, 'limit' => function ($query) {
                    $query->select(['id', 'attr_value']);
                }])
                ->select(['id', 'name', 'age', 'region_id', 'loan_type', 'apply_number', 'is_check', 'apply_information', 'loan_type', 'price', 'description', 'source_id', 'source_table', 'create_time', 'time_limit', 'status','expire_time','is_vip','city_id','mobile'])
                ->orderBy('id', 'desc')
                ->paginate($this->pageSize);
            if(count($list))
            {
                $list = $list -> toArray();
                foreach($list['data'] as &$item)
                {
                    $arr=[];
                    $item['type'] = $item['loan_type']['attr_value'];
                    $item['city'] = $item['region']['name'];
                    $item['type'] = $item['loan_type']['attr_value'];
                    $item['time_limit'] = $item['limit']['attr_value'];
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
                    }
                $this->setData($list);
            }else{
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        }else{
            $this->setMessage('暂无经理信息');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param $id
     * @return mixed
     * 甩单详情（甩单栏目）
     */
    public function productShow(Request $request,$id)
    {
        $item = JunkModel::with(['LoanType'=>function($query){
            $query->select(['id','attr_value']);
        },'region'=>function($query){
            $query->select(['id','name']);
        },'limit'=>function($query){
            $query->select(['id','attr_value']);
        }])
//        ->where([['status','=',1],['is_check','=',2],['expire_time','>',time()]])
        ->find($id,['id','loan_type','region_id','age','name','time_limit','apply_number','apply_information','job_information','description','source_id','price','is_vip','mobile','status','is_check','create_time','is_check','expire_time','status','loaner_id','city_id']);
        if(count($item))
        {
                $arr = [];
                $item['type'] = $item['LoanType']['attr_value'];
                $item['city'] = $item['region']['name'];
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
                $basic[] = ['attr_value'=>$item['age'],'attr_key'=>'出生'];
                $basic[] = ['attr_value'=>$item['region']['name'],'attr_key'=>'现居'];
                if($item['loaner_id'] == $request->loaner_id){
                    $basic[] = ['attr_value' => $item['mobile'], 'attr_key' => '电话'];
                } else {
                    $basic[] = ['attr_value' => substr($item['mobile'], 0, 3) . '****' . substr($item['mobile'], 7), 'attr_key' => '电话'];
                }
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
                unset($item['loanType']);
                unset($item['loan_type']);
                unset($item['region']);
                unset($item['city']);
                unset($item['age']);
                unset($item['limit']);
                unset($item['source']);
                unset($item['apply_information']);
                unset($item['job_information']);
                unset($item['mobile']);
                $this->setData($item);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }
     /**
     * @param $id
     * @param null $type
     * 甩单：价格
     */
    public function setPrice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'price' => 'required',//价格
            'id'=>'required',//id:loan_id
        ]);
        if (!$validator->fails()) {
            $uid = $this->checkUid($request->header('deviceid'));
            $loaner = LoanerModel::where(['user_id' => $uid])->first(['id', 'loanername']);
            if (count($loaner))
            {
                //订单重新标价甩出
                $info = LoanModel::find($request->id,['id','name','age','loaner_id','user_id','product_id','apply_number','apply_information','time_limit','loan_type','description','mobile','job_information','is_marry','province_id','city_id','region_id','is_vip','mobile']);
                DB::beginTransaction();
                try {
                    JunkModel::create(['name'=>$info->name,'loaner_name'=>$loaner->loanername,'age'=>$info->age,'loaner_id'=>$info->loaner_id,'user_id'=>$info->user_id,'product_id'=>$info->product_id,'apply_number'=>$info->apply_number,'apply_information'=>$info->apply_information,'job_information'=>$info->job_information,'time_limit'=>$info->time_limit,'loan_type'=>$info->loan_type,'description'=>$info->description,'is_marry'=>$info->is_marry,'province_id'=>$info->province_id,'city_id'=>$info->city_id,'region_id'=>$info->region_id,'is_check'=>2,'expire_time'=>time()+$this->expire_time,'source_id'=>$request->id,'price'=>$request->price,'is_vip'=>$info->is_vip,'create_time'=>time(),'mobile'=>$info->mobile]);
                    LoanModel::where(['id'=>$request->id])
                    ->update(['status'=>3,'discard_time'=>time()]);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();//事务回滚
                }
            } else {
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 重新甩单
     */
    public function junkAgain(Request $request)
    {
        $exist = JunkModel::where(['loaner_id'=>$request->loaner_id,'id'=>$request->id,'is_check'=>2])
                ->first();
        if(count($exist))
        {
            $res = JunkModel::where(['id'=>$exist->id])
                ->update(['status'=>1,'expire_time'=>time()+$this->expire_time,'update_time'=>time()]);
            if(!$res)
            {
                $this->setMessage('服务器错误');
                $this->setstatusCode(500);
            }
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

}
