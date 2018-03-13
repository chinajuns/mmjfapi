<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\IntegralListModel;
use App\Api\Models\IntegralModel;
use App\Api\Models\JunkModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanEvaluateModel;
use App\Api\Models\LoanExamineModel;
use App\Api\Models\LoanModel;
use App\Api\Models\NoticeModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductAttrValueModel;
use App\Api\Models\ProductCategoryModel;
use App\Api\Models\ProductModel;
use App\Api\Models\ProductOtherModel;
use App\Api\Models\RegionModel;
use App\Api\Models\ReportModel;
use App\Api\Models\ShopAgentModel;
use App\Api\Models\ShopModel;
use App\Api\Models\UserModel;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class ShopController extends BaseController
{
    /**
     * @param Request $request
     * @return mixed
     * 判断是否认证
     */
   public function index(Request $request){
       $is_auth = UserModel::with(['auth','shop'=>function($query){
                $query->select(['id','check_result','user_id']);
       }])
           ->where(['is_disable'=>1,'is_auth'=>3])
           ->find($request->uid);
       if(!$is_auth)
       {
           if($is_auth->shop)
           {
               $this->setData(['is_auth'=>$is_auth->is_auth,'check_result'=>$is_auth->shop['check_result']]);
           }else {
               $this->setData(['is_auth' => $is_auth->is_auth,'check_result'=>0]);
           }
       }
       else{
           $this->setData(['is_auth'=>3,'check_result'=>0]);
       }
       return $this->result();
   }

    /**
     * @param Request $request
     * 用户经理信息
     */
    public function information(Request $request,$id=null){
        $info = UserModel::with(['auth'=>function($query){
            $query->select(['user_id','true_name','province_id','city_id','region_id','mechanism','photo']);
        }])
//            ->where(['is_disable'=>1,'is_auth'=>2])
            ->where(['is_disable'=>1])
            ->find($request->uid,['id','is_auth']);
        if(count($info))
        {
            $info = $info->toArray();
            $info['true_name'] = $info['auth']['true_name'];
            $info['mechanism'] = $info['auth']['mechanism'];
            $info['header_img'] = $info['auth']['photo'];
            $province = RegionModel::find($info['auth']['province_id'],['name']);
            $city = RegionModel::find($info['auth']['city_id'],['name']);
            $district = RegionModel::find($info['auth']['region_id'],['name']);
            $shop = ShopModel::where(['user_id'=>$request->uid])->first(['id','special','introduce','work_time','service_object']);
            if(count($shop)) {
                $info['special'] = $shop->special;
                $info['introduce'] = $shop->introduce;
                $info['work_time'] = $shop->work_time;
                $info['shop_id'] = $shop->id;
                $info['service_object'] = $shop->service_object;
            }
            $info['address'] = ($province?$province->name:'') .','. ($city?$city->name:'') .','.($district?$district->name:'');
            unset($info['auth']);
            $this->setData($info);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 申请店铺
     */
    public function applyShop(Request $request){
        $validator = Validator::make($request->all(), [
            'service_object' => 'required',//适合人群
            'work_time'=>'required',//从业时间
            'special' => 'required',//特点
            'introduce'=>'required',//简介
            'action'=>'required',//add|update
//            'shop_id'=>'required'
        ]);
        if (!$validator->fails())
        {
            if($request->action == 'add') {
                $exist = ShopModel::where(['user_id' => $request->uid])
                    ->first();
                $loaner = LoanerModel::where(['user_id' => $request->uid])
                    ->with(['auth' => function ($query) {
                        $query->select(['id', 'user_id', 'true_name', 'mechanism']);
                    }])
                    ->first();
                if (!count($exist) && count($loaner))
                {
                    $res = ShopModel::create(['user_id' => $request->uid, 'loaner_name' => $loaner->auth['true_name'], 'mechanism_name' => $loaner->auth['mechanism'], 'auth_id' => $loaner->auth['id'], 'work_time' => $request->work_time, 'special' => $request->special, 'service_object' => $request->service_object, 'introduce' => $request->introduce, 'create_time' => time(),'loaner_id' => $loaner->id,'check_result'=>2]);
                    if(!$res)
                    {
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                } else {
                    if (count($exist)) {
                        $this->setstatusCode(4010);
                        $this->setMessage('重复申请');
                    } else {
                        $this->setstatusCode(5000);
                        $this->setMessage('没有该经理人信息');
                    }
                }
            }elseif($request->shop_id && $request->action == 'update'){//修改店铺
                $exist = ShopModel::where(['user_id'=>$request->uid,'id'=>$request->shop_id])
                    ->first();
                if(count($exist))
                {
                    $res = ShopModel::where(['id'=>$request->shop_id])
                        ->update(['work_time'=>$request->work_time,'special'=>$request->special,'service_object'=>$request->service_object,'introduce'=>$request->introduce,'update_time'=>time(),'check_result'=>2]);
                    if(!$res){
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                }else{
                    $this->setstatusCode(5000);
                    $this->setMessage('暂无数据');
                }
            }else{
                $this->setMessage('参数错误');
                $this->setstatusCode(4002);
            }
        }else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 店铺详情
     */
    public function showHeader(Request $request){
        $info = UserModel::where(['is_auth'=>3,'is_disable'=>1])
        ->with(['auth'=>function($query){
            $query->select(['true_name','address','user_id','province_id','city_id','region_id']);
        },'shop'=>function($query){
            $query->select(['pageviews','user_id','id','introduce','loaner_id']);
        },'loaner'=>function($query){
            $query->select(['user_id','score']);
        }])
            ->select(['is_auth','header_img','id'])
            ->find($request->uid);
        if(count($info))
        {
            $info->true_name = $info->auth['true_name'];
            $province = RegionModel::find($info->auth['province_id']);
            $city = RegionModel::find($info->auth['city_id']);
            $region = RegionModel::find($info->auth['region_id']);
            $info->address = ($province ? $province->name :'').','.($city?$city->name:'').','.($region?$region->name:'');
            $info->views = $info->shop['pageviews'];
            $info->shop_id = $info->shop['id'];
            $info->introduce = $info->shop['introduce'];
            $info->loaner_id = $info->shop['loaner_id'];
            $info->score = $info->loaner['score'];
            //代理产品数量
            $info->agent = ShopAgentModel::where(['id'=>$info->shop_id,'status'=>1])->count();
            //申请人数
            $info->apply_number = LoanModel::where(['loaner_id'=>$info->loaner_id])->count();
            unset($info->auth);
            unset($info->shop);
            unset($info->loaner);
            ShopModel::where(['id'=>$info->shop['id']])->increment('pageviews');
            $this->setData($info);
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 订单列表
     */
    public function orderList(Request $request){
        $loaner = LoanerModel::where(['user_id'=>$request->uid])->first();
        if($loaner) {
            if (in_array($request->type,[1,2,3])) {
                //搜索条件:TODO::查询type为2订单
                if($request->type == 1)//办理中
                {
                    $list = LoanModel::where([['loaner_id','=', $loaner->id],['process','<',37],['type','=',1],['status','=',1]])
                        ->with(['user' => function ($query) {
                            $query->select(['id', 'username', 'is_auth']);
                        }, 'type' => function ($query) {
                            $query->select(['attr_value as type_name', 'id']);
                        }, 'timeLimit' => function ($query) {
                            $query->select(['attr_value as time_limit', 'id']);
                        }, 'region' => function ($query) {
                            $query->select(['id', 'name']);
                        }])
                        ->select(['id','name','age','user_id','apply_number','process','junk_id','type','region_id','score','apply_information','job_information','time_limit','loan_type','mobile','create_time','job_information','is_marry','is_vip','is_comment','junk_id'])
                        ->orderBy('update_time','desc')
                        ->orderBy('id','desc')
                        ->paginate($this->pageSize);
                }elseif($request->type == 2){//待评价
                    $list = LoanModel::where([['loaner_id','=', $loaner->id],['is_comment','=',1],['type','=',1],['status','=',1]])
                        ->whereIn('process',[37,38])
                        ->with(['user' => function ($query) {
                            $query->select(['id', 'username', 'is_auth']);
                        }, 'type' => function ($query) {
                            $query->select(['attr_value as type_name', 'id']);
                        }, 'timeLimit' => function ($query) {
                            $query->select(['attr_value as time_limit', 'id']);
                        }, 'region' => function ($query) {
                            $query->select(['id', 'name']);
                        }])
                        ->select(['id','name','age','user_id','apply_number','process','junk_id','type','region_id','score','apply_information','job_information','time_limit','loan_type','mobile','create_time','job_information','is_marry','is_vip','is_comment','junk_id'])
                        ->orderBy('update_time','desc')
                        ->orderBy('id','desc')
                        ->paginate($this->pageSize);
                }elseif($request->type == 3)//已完成
                {
                    $list = LoanModel::where([['loaner_id','=', $loaner->id],['type','=',1],['status','=',1]])
                        ->whereIn('process',[37,38])
                        ->with(['user' => function ($query) {
                            $query->select(['id', 'username', 'is_auth']);
                        }, 'type' => function ($query) {
                            $query->select(['attr_value as type_name', 'id']);
                        }, 'timeLimit' => function ($query) {
                            $query->select(['attr_value as time_limit', 'id']);
                        }, 'region' => function ($query) {
                            $query->select(['id', 'name']);
                        }])
                        ->select(['id','name','age','user_id','apply_number','process','junk_id','type','region_id','score','apply_information','job_information','time_limit','loan_type','mobile','create_time','job_information','is_marry','is_vip','is_comment','junk_id','city_id'])
                        ->orderBy('update_time','desc')
                        ->orderBy('id','desc')
                        ->paginate($this->pageSize);
                }
            } else {//全部订单
                $list = LoanModel::where(['loaner_id' => $loaner->id,'type'=>1,'status'=>1])
                    ->with(['user' => function ($query) {
                        $query->select(['id', 'username', 'is_auth']);
                    }, 'type' => function ($query) {
                        $query->select(['attr_value as type_name', 'id']);
                    }, 'timeLimit' => function ($query) {
                        $query->select(['attr_value as time_limit', 'id']);
                    }, 'region' => function ($query) {
                        $query->select(['id', 'name']);
                    }])
                    ->select(['id','name','age','user_id','apply_number','process','junk_id','type','region_id','score','apply_information','job_information','time_limit','loan_type','mobile','create_time','job_information','is_marry','is_vip','is_comment','junk_id','city_id'])
                    ->orderBy('id','desc')
                    ->paginate($this->pageSize);
            }
            if (count($list))
            {
                $list = $list->toArray();
                foreach ($list['data'] as &$item)
                {
                    $info = [];
                    if ($item['apply_information'])
                    {
                        $information = LoanerAttrModel::whereIn('id', explode(',', $item['apply_information']))
                            ->with('parent')
                            ->get();
                        if (count($information))
                        {
                            $info[] = ['attr_value' =>(date('Y') - substr($item['age'],0,strpos($item['age'],'-')+1)), 'attr_key' => '年龄'];
                            $info[] = ['attr_value' => $item['region']['name'],'attr_key' => '现居'];
                            $info[] = ['attr_value' =>$item['mobile'], 'attr_key' => '手机'];
                            foreach ($information as $k => $v) {
                                $info[] = ['attr_value' => $v->attr_value, 'attr_key' => $v->parent['attr_value']];
                            }
                            $item['information'] = $info;
                        }
                        else {
                            $item['information'] = null;
                        }
                    }
                    $item['apply_user'] = $item['user']['username'];
                    $item['time_limit'] = $item['time_limit']['time_limit'];
                    $item['apply_type'] = $item['type']['type_name'];
                    $item['is_auth'] = $item['user']['is_auth'];
                    $item['region'] = $item['region']['name'];
                    $item['process_all'] = LoanerAttrModel::where([['pid' ,'=', 10],['id','<>',38]])
                        ->get(['id', 'attr_value']);
                    $processing = LoanExamineModel::where(['loan_id' => $item['id']])
                        ->with(['process' => function ($query) {
                            $query->select(['id', 'attr_value', 'pid']);
                        }, 'loaner' => function ($query) {
                            $query->select(['id', 'loanername as name', 'loanername_mobile as mobile']);
                        }])
                        ->get(['loan_id', 'id', 'process', 'describe', 'create_time', 'loaner_id']);
                    if ($processing) {
                        $processing = $processing->toArray();
                        foreach ($processing as &$val) {
                            $val['value'] = $val['process']['attr_value'];
                            $val['id'] = $val['process']['id'];
                            $val['loanername'] = $val['loaner']['name'];
                            $val['mobile'] = $val['loaner']['mobile'];
                            unset($val['process']);
                            unset($val['loaner']);
                            unset($val['loan_id']);
                        }
                        $item['processing'] = $processing;
                    } else {
                        $item['processing'] = null;
                    }
                    if($item['junk_id']){
                        $junk = JunkModel::find($item['junk_id'],['price']);
                        if($junk){
                            $item['price'] = $junk->price;
                        }else{
                            $item['price'] = 0;
                        }
                    }else{
                        $item['price'] = 0;
                    }
                    unset($item['user']);
                    unset($item['region']);
                    unset($item['apply_information']);
                    unset($item['job_information']);
                    unset($item['type']);
//                    unset($item['mobile']);
                    unset($item['age']);
                }
                $this->setData($list);
            }
            else{
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
            return $this->result();
        }
    }

    /**
     * @param Request $request
     * @return mixed
     * 用户点评
     */
    public function evaluate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'score' => 'required',//评分
            'loan_id'=>'required',//订单id
            'tags' => 'required',//特点:ids
            'comment'=>'required',//评价
        ]);
        if (!$validator->fails()) {
            $exist = LoanModel::where(['loaner_id'=>$request->loaner_id,'is_comment'=>1,'id'=>$request->loan_id])
                ->whereIn('process',[37,38])
                ->first();
            $is_comment = LoanEvaluateModel::where(['loan_id' => $request->loan_id, 'user_id' => $request->uid])
                ->first();
            if(count($exist) && $is_comment == null)
            {
                DB::beginTransaction();
                try {
                LoanEvaluateModel::create(['loan_id' => $request->loan_id, 'user_id' => $request->uid, 'describe' => $request->comment, 'focus' => $request->tags, 'create_time' => time(), 'score_avg' => $request->score]);

                LoanModel::where(['id'=>$request->loan_id])
                    ->update(['is_comment'=>2]);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();//事务回滚
                    $this->setstatusCode(500);
                    $this->setMessage('服务器错误');
                }
            }else{
                $this->setMessage('订单错误');
                $this->setstatusCode(5000);
            }
        }else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * 用户标签
     */
    public function userTags()
    {
        $tags = LoanerAttrModel::where(['pid'=>4,'function_name'=>'b2c'])
            ->get(['attr_value','id']);
        $this->setData($tags);
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 举报用户
     */
    public function report(Request $request){
        $validator = Validator::make($request->all(), [
            'to_uid' => 'required',//
            'loan_id'=>'required',//
            'comment'=>'required',//举报内容
        ]);
        if (!$validator->fails())
        {
            $exist = ReportModel::where(['to_uid'=>$request->to_uid,'from_uid'=>$request->uid,'type'=>1])
                ->first();
            if(!count($exist)) {
                DB::beginTransaction();
                try {
                    $res = ReportModel::create(['create_time' => time(), 'to_uid' => $request->to_uid, 'from_uid' => $request->uid, 'report_reason' => $request->comment, 'type' => 1, 'loan_id' => $request->loan_id]);
                    $from = UserModel::find($request->uid, ['username']);
                    $to = UserModel::find($request->to_uid, ['username']);
                    ReportModel::where(['id' => $res->id])
                        ->update(['from_name' => $from->username, 'to_name' => $to->username]);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();//事务回滚
                    $this->setstatusCode(500);
                    $this->setMessage('服务器错误');
                }
            }else{
                $this->setstatusCode(4010);
                $this->setMessage('重复举报');
            }
        }else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param $id
     * 订单详情
     */
    public function show(Request $request,$id)
    {
        $loaner = LoanerModel::where(['user_id'=>$request->uid])->first();
        if($loaner) {
            $item =  LoanModel::with(['user' => function ($query) {
                $query->select(['id', 'username', 'is_auth']);
            }, 'type' => function ($query) {
                $query->select(['attr_value as type_name', 'id']);
            }, 'timeLimit' => function ($query) {
                $query->select(['attr_value as time_limit', 'id']);
            }, 'region' => function ($query) {
                $query->select(['id', 'name']);
            }])
            ->where(['id'=>$id,'loaner_id'=>$loaner->id])
            ->select(['id','name', 'create_time', 'apply_number', 'process', 'user_id', 'loan_type', 'process', 'time_limit', 'apply_information', 'region_id', 'age','mobile','is_vip','city_id'])
            ->first();
            if($item)
            {
                $item = $item->toArray();
                $info = [];
                if ($item['apply_information']) {
                    $information = LoanerAttrModel::whereIn('id', explode(',', $item['apply_information']))
                        ->with('parent')
                        ->get();
                    if (count($information)) {
                        $info[] = ['attr_value' => (date('Y') - substr($item['age'],0,strpos($item['age'],'-')+1)) , 'attr_key' => '年龄'];
                        $info[] = ['attr_value' => $item['region']['name'],'attr_key' => '现居'];
                        $info[] = ['attr_value' =>$item['mobile'] , 'attr_key' => '手机'];
                        foreach ($information as $k => $v) {
                            $info[] = ['attr_value' => $v->attr_value, 'attr_key' => $v->parent['attr_value']];
                        }
                        $item['information'] = $info;
                    } else {
                        $item['information'] = null;
                    }
                } else {
                    $item['information'] = null;
                }
                $item['time_limit'] = $item['time_limit']['time_limit'];
                $item['type'] = $item['type']['type_name'];
                $item['region'] = $item['region']['name'];
                $item['process_all'] = LoanerAttrModel::where([['pid' ,'=', 10],['id','<>',38]])
                    ->get(['id', 'attr_value']);
                unset($item['age']);
                unset($item['user']);
                unset($item['apply_information']);
                unset($item['loan_type']);
                unset($item['mobile']);
                //审核流水
                $processList = LoanExamineModel::where(['loan_id'=>$id])
                    ->with(['process'=>function($query){
                        $query->select(['id','attr_value']);
                    },'loaner'=>function($query){
                        $query->select(['id','loanername','loanername_mobile']);
                    }])
                    ->orderBy('id','desc')
                    ->get(['id','loan_id','loaner_id','process','create_time','describe']);

                if($processList)
                {
                    $processList = $processList->toArray();
                    foreach($processList as &$list)
                    {
                        $list['process_id'] = $list['process']['id'];
                        $list['process'] = $list['process']['attr_value'];
                        $list['loaner_name'] = $list['loaner']['loanername'];
                        $list['loaner_mobile'] = $list['loaner']['loanername_mobile'];
                        unset($list['loaner']);
                    }
                }
                $item['processHistory'] = $processList;
                $this->setData($item);
            }else{
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        }else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 进度设置
     * //TODO::进度完成控制
     */
    public function setProcess(Request $request){
        $validator = Validator::make($request->all(), [
            'loan_id' => 'required',//订单id
            'process_id'=>'required',//进度id
//            'describe' => 'required',//备注
//            $request->loan_number //放款金额
        ]);
        if (!$validator->fails() && in_array($request->process_id,[12,36,37,38])) {
            $loaner = LoanerModel::where(['user_id' => $request->uid])->first();
            $exist = LoanModel::where(['loaner_id' => $loaner->id, 'id' => $request->loan_id])
                ->first();
            $isExamine = LoanExamineModel::where(['process' => $request->process_id, 'loan_id' => $request->loan_id])
                ->first();
            $userInfo = UserModel::find($request->uid,['integral']);
            $info = LoanModel::find($request->loan_id);
            $process = LoanerAttrModel::find($request->process_id);
            if($request->process_id == 38) {//失败
                $message = $process->attr_value.'失败！';
                $is_success = 2;
            }
            else{
                $message = $process->attr_value.'成功！';
                $is_success = 1;
            }
            $integral = IntegralModel::find(19);
            if($integral){
                $number = $integral->integral_number;
            }else{
                $number = 5;
            }
            if($exist && !$isExamine)
            {
                if($request->process_id == 12){
                    $describe = '签约金额'.$request->loan_number.'万元';//前端参数传错了
                }elseif($request->process_id == 37){
                    $describe = '放款金额'.$request->loan_number.'万元';//前端参数传错了
                }else{
                    $describe = '';
                }
                DB::beginTransaction();
                try {
                    LoanExamineModel::create(['process' => $request->process_id, 'loan_id' => $request->loan_id, 'loaner_id' => $loaner->id, 'create_time' => time(), 'describe' => $describe ? $describe : (($request->process_id != 38)?'通过':'失败')]);
                    if($request->process_id == 37)//审批放款
                    {
                        LoanModel::where(['id' => $request->loan_id])
                            ->update(['process' => $request->process_id,'loan_day'=>ceil(time()/$exist->create_time),'loan_time'=>time(),'loan_number'=>$request->loan_number]);
                    }else{
                        LoanModel::where(['id' => $request->loan_id])
                            ->update(['process' => $request->process_id]);
                    }
                    //积分变更
                    IntegralListModel::create(['user_id'=>$request->uid,'integral_id'=>19,'number'=>$number,'total'=>$userInfo->integral+$number,'create_time'=>time(),'description'=>'订单审核奖励'.$number.'积分','desc'=>'订单id:'.$request->loan_id]);
                    //TODO::
                    UserModel::where(['id'=>$request->uid])
                        ->increment('integral',$number);
                    if($info->user_id) {
                        NoticeModel::create(['title' => '订单信息', 'content' => $message, 'create_time' => time(), 'is_success' => $is_success, 'type' => 1, 'to_uid' => $info->user_id,'from_uid'=>$request->uid]);
                    }
                    if($request->process_id == 37) {
                        //信贷经理相应参数变更：
                        //放款天数
                        $avg_day = LoanModel::where(['process'=>37,'loaner_id'=>$request->loaner_id])
                            ->sum('loan_day');
                        $day = ceil((time() - $exist->create_time) / 86400);
                        if ($avg_day) {
                            $loan_day = ceil(($day + $avg_day) / 2);
                        } else {
                            $loan_day = $day;
                        }
                        //放款金额:总
                        $all_number = LoanModel::where(['process'=>37,'loaner_id'=>$request->loaner_id])
                            ->sum('loan_number');
                        //30天金额
                        $loan_number = LoanModel::where(['process'=>37,'loaner_id'=>$request->loaner_id])
                            ->where('loan_time','>=',time()-29*86400)
                            ->sum('loan_number');
                        LoanerModel::where(['id'=>$request->loaner_id])
                            ->update(['loan_number'=>$loan_number+$request->loan_number,'loan_day'=>$loan_day,'all_number'=>$all_number+$request->loan_number,'update_time'=>time()]);
                    }
                    $show = $this->show($request,$request->loan_id);
                    $this->setData($show['data']);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();//事务回滚
                    $this->setstatusCode(500);
                    $this->setMessage('服务器错误');
                }
            }else{
                if(count($isExamine)){
                    $this->setMessage('重复审核');
                    $this->setstatusCode(4010);
                }else {
                    $this->setMessage('暂无数据');
                    $this->setstatusCode(5000);
                }
            }
        }else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * 代理产品
     */
    public function otherProduct(Request $request, $type){
        if($type == 'all')
        {//可代理产品
            $list = ProductOtherModel::where(['is_display'=>1])
                ->select(['title','time_limit','loan_day','loan_number','rate','description','create_time','apply_peoples','property'])
                ->paginate($this->pageSize);
        }
       elseif($type=='mine')
       {//我代理的产品
               $list = ShopAgentModel::where([['third_pro_id', '>', 0], ['is_normal', '=', 1],['loaner_id','=',$request->loaner_id]])
                ->with(['productOther'=>function($query){
                    $query->select(['id','title', 'time_limit', 'loan_day', 'loan_number', 'rate', 'description', 'create_time', 'apply_peoples', 'property']);
                }])
                ->select(['third_pro_id'])
                ->paginate($this->pageSize);
               if(count($list))
               {
                    $list = $list->toArray();
                   foreach($list['data'] as &$item)
                   {
                       $item['id'] = $item['product_other']['id'];
                       $item['title'] = $item['product_other']['title'];
                       $item['time_limit'] = $item['product_other']['time_limit'];
                       $item['loan_day'] = $item['product_other']['loan_day'];
                       $item['loan_number'] = $item['product_other']['loan_number'];
                       $item['rate'] = $item['product_other']['rate'];
                       $item['description'] = $item['product_other']['description'];
                       $item['create_time'] = $item['product_other']['create_time'];
                       $item['apply_peoples'] = $item['product_other']['apply_peoples'];
                       $item['property'] = $item['product_other']['property'];
                       unset($item['product_other']);
                       unset($item['third_pro_id']);
                   }
               }else{
                   $list = [];
               }
           }
           else
           {
               $list = [];
           }
        if($list)
        {
            $this->setData($list);
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 代理筛选
     */
    public function otherSearch(Request $request)
    {
        $name = $request->name ? $request->name : '';
        $type = $request->type ? $request->type : '';
        if($name || $type) {
            if($name && !$type){
                $list = ProductOtherModel::where([['is_display','=', 1],['title','like','%'.$name.'%']])
                    ->select(['title', 'time_limit', 'loan_day', 'loan_number', 'rate', 'description', 'create_time', 'apply_peoples','property'])
                    ->paginate($this->pageSize);
            }elseif(!$name && $type){
                $list = ProductOtherModel::where(['is_display'=> 1,'property'=>$type])
                    ->select(['title', 'time_limit', 'loan_day', 'loan_number', 'rate', 'description', 'create_time', 'apply_peoples','property'])
                    ->paginate($this->pageSize);
            }else{
                $list = ProductOtherModel::where([['is_display','=', 1],['title','like','%'.$name.'%'],['property','=',$type]])
                    ->select(['title', 'time_limit', 'loan_day', 'loan_number', 'rate', 'description', 'create_time', 'apply_peoples','property'])
                    ->paginate($this->pageSize);
            }
            if ($list)
            {
                $this->setData($list);
            } else {
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        } else {
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 可代理列表
     */
    public function getList(Request $request)
    {
        //系统产品代理记录
        $sysIds = ShopAgentModel::where([['loaner_id','=',$request->loaner_id],['sys_pro_id','>',0],['status','=',1]])
            ->orderBy('update_time','desc')
            ->orderBy('id','desc')
            ->get()
            ->pluck('sys_pro_id');
        if(count($sysIds))
        {
            $sysIds = $sysIds->toArray();
        }else{
            $sysIds = null;
        }
        $where1 = [];
        $where = [];
        $pageNumber = $request->pageNumber ? ($request->pageNumber-1)*$this->pageSize : 0;
        if($request->type == 'all')
        {//可以代理产品
            if ($request->create_time) {
                $where = [['create_time', '<', $request->create_time], ['is_hot', '>', 0]];
            } else {
                $where = [['is_hot', '>', 0]];
                $where1 = [['is_hot', '>', 0]];
            }
            if ($request->cate) {//贷款类型:
                $where[] = ['cate_id', '=', $request->cate];
                $where1[] = ['cate_id', '=', $request->cate];
            }
            if ($request->title) {//标题
                $where[] = ['title', 'like', '%' . $request->title . '%'];
                $where1[] = ['title', 'like', '%' . $request->title . '%'];
            }
            if($sysIds)
            {
    //            DB::connection()->enableQueryLog();
                $list = ProductModel::whereNotIn('id',$sysIds)
                    ->where($where)
//                    ->select(['id','source','create_time','apply_peoples','title'])
                    ->orderBy('id','desc')
                    ->orderBy('create_time','desc')
                    ->skip($pageNumber)
                    ->take($this->pageSize)
                    ->get(['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id','loan_number_start','loan_number_end']);
                $count = ProductModel::whereNotIn('id',$sysIds)
//                    ->select(['id','source','create_time','apply_peoples','title'])
                    ->where($where1)
                    ->count();
            }
            else
            {
                $list = ProductModel::where($where)
//                    ->select(['id','source','create_time','apply_peoples','title'])
                    ->orderBy('id','desc')
                    ->orderBy('create_time','desc')
                    ->skip($pageNumber)
                    ->take($this->pageSize)
                    ->get(['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id','loan_number_start','loan_number_end']);
                $count = ProductModel::where($where)
                    ->count();
            }
        }
        elseif($request->type == 'mine'){//我代理的产品
            if ($request->create_time) {
                $where = [['create_time', '<', $request->create_time], ['is_hot', '>', 0]];
            } else {
                $where = [['is_hot', '>', 0]];
            }
            if ($request->cate) {//贷款类型:
                $where[] = ['cate_id', '=', $request->cate];
                $where1[] = ['cate_id', '=', $request->cate];
            }
            if ($request->title) {//标题
                $where[] = ['title', 'like', '%' . $request->title . '%'];
                $where1[] = ['title', 'like', '%' . $request->title . '%'];
            }
            if($sysIds)
            {
//            DB::connection()->enableQueryLog();
                $list = ProductModel::whereIn('id',$sysIds)
                    ->where($where)
//                    ->select(['id','source','create_time','apply_peoples','title'])
                    ->orderBy('id','desc')
                    ->orderBy('create_time','desc')
                    ->skip($pageNumber)
                    ->take($this->pageSize)
                    ->get(['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id','loan_number_start','loan_number_end']);
                $count = ProductModel::whereIn('id',$sysIds)
                    ->where($where)
//                    ->select(['id','source','create_time','apply_peoples','title'])
                    ->orderBy('id','desc')
                    ->orderBy('create_time','desc')
                    ->count();
            }
            else
            {
                $list = null;
                $count = 0;
            }
        }else{
           $list = null;
        }
//        dd($list->toArray());
        if(count($list)) {
            foreach ($list as &$item) {
               $res = $this->getProductOptions($item->option_values,$item->need_data);
                $item->options = $res['apply_condition'];
                $item->need_data = $res['need_data'];
                $time_limit = ProductAttrValueModel::find($item->time_limit_id);
                if($time_limit)
                {
                    $item->time_limit = $time_limit->attr_value;
                }
                //判断代理
                $is_proxy = ShopAgentModel::where(['loaner_id' => $request->loaner_id,'sys_pro_id'=>$item->id, 'status' => 1])->first();
                $item->is_proxy = $is_proxy ? 1: 0;
                $item->proxy_time = $is_proxy ? $is_proxy->create_time: 0;
                //代理人数
                $item->loan_number = str_replace(',','-',$item->loan_number).'万元';
                $item->rate = str_replace(',','-',$item->rate).'%';
                $type = ProductAttrValueModel::find($item->cate_id);
                $item->loan_type = $type ? $type->attr_value:'';
                $item->loan_day = $item->loan_day.'天';
                $item->platform = 'system';
                unset($item->service_options);
                unset($item->option_values);
            }
           $this->setData(['list'=>$list,'total'=>$count,'current'=>$request->pageNumber?$request->pageNumber:1,'totalPage'=>ceil($count/$this->pageSize)]);
        }
        else{
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }
    /**
     * @return mixed
     * 产品类型
     */
    public function otherType(){
        $list = ProductAttrValueModel::where(['attr_id'=>5])->get(['attr_value as cate_name','id']);
        $this->setData($list);
        return $this->result();
    }

    /**
     * @param $id
     * 产品详情
     */
    public function otherShow(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required',//产品id
        ]);
        if (!$validator->fails()) {
                $item = ProductModel::find($request->id,['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id','loan_number_start','loan_number_end']);
                if(count($item)) {
                    $res = $this->getProductOptions($item->option_values, $item->need_data);
                    $item->options = $res['apply_condition'];
                    $item->need_data = $res['need_data'];
                    $time_limit = ProductAttrValueModel::find($item->time_limit_id);
                    if ($time_limit) {
                        $item->time_limit = $time_limit->attr_value;
                    }
                    //判断代理
                    $is_proxy = ShopAgentModel::where(['loaner_id' => $request->loaner_id,'sys_pro_id'=>$item->id, 'status' => 1])->first();
                    $item->is_proxy = $is_proxy ? 1 : 0;
                    $item->proxy_time = $is_proxy ? $is_proxy->create_time : 0;
                    //代理人数
                    $item->loan_number = str_replace(',', '-', $item->loan_number) . '万元';
                    $item->rate = str_replace(',', '-', $item->rate) . '%';
                    $type = ProductAttrValueModel::find($item->cate_id);
                    $item->loan_type = $type ? $type->attr_value : '';
                    $item->loan_day = $item->loan_day . '天';
                    $item->platform = 'system';
                    //判断代理人数
                    $proxy_number = ShopAgentModel::where(['sys_pro_id' => $request->id, 'status' => 1])
                        ->count();
                    $item->proxy_number = $proxy_number ? $proxy_number : 0;
                    unset($item->service_options);
                    unset($item->option_values);
                    $this->setData($item);
                }
                else{
                    $this->setMessage('暂无数据');
                    $this->setstatusCode(5000);
                }
        }else{
            $this->setMessage('参数不全');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 代理
     */
    public function setAgent(Request $request){
        $validator = Validator::make($request->all(), [
            'action' => 'required',//操作
            'id'=>'required',//产品id
//            'platform' =>'required',//平台
        ]);
        if (!$validator->fails()) {//店铺认证+信贷经理认证-》代理
            $loaner = LoanerModel::where(['user_id'=>$request->uid])->first();
            $shop = ShopModel::where(['user_id'=>$request->uid,'status'=>1])->first();
            //TODO::代理产品申请
            if(count($shop))
            {
                $where = ['loaner_id' => $loaner->id,'sys_pro_id'=>$request->id,'shop_id'=>$shop->id];
                if($request->action == 'add')
                {
                    $exist = ShopAgentModel::where(array_merge($where,['status'=>1]))
                        ->first();
//                    dd($where);
                    $cancel = ShopAgentModel::where(array_merge($where,['status'=>2]))
                        ->first();
                    if (!count($exist) && !count($cancel))//未代理|未取消
                    {
                        $where =array_merge($where, ['create_time' => time()]);
                        DB::beginTransaction();
                        try {
                            ShopAgentModel::create($where);
                            LoanerModel::where(['id'=>$loaner->id])
                                ->increment('proxy_number');
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollback();//事务回滚
                            $this->setstatusCode(500);
                            $this->setMessage('服务器错误');
                        }
                    } elseif(!count($exist) && count($cancel)){//未代理|已取消
                        DB::beginTransaction();
                        try {
                            ShopAgentModel::where(['id'=>$cancel->id])
                                ->update(['status'=>1,'update_time'=>time()]);
                            LoanerModel::where(['id'=>$loaner->id])
                                ->increment('proxy_number');
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollback();//事务回滚
                            $this->setstatusCode(500);
                            $this->setMessage('服务器错误');
                        }
                    }else{
                        $this->setMessage('重复申请');
                        $this->setstatusCode(4010);
                    }
                }
                else
                {//取消代理，更改状态
                    $where = array_merge($where,['status'=>1]);
                    $exist = ShopAgentModel::where($where)
                        ->first();
                    if (count($exist))
                    {
                        $res = ShopAgentModel::where(['id'=>$exist->id])->update(['status'=>2,'update_time'=>time()]);
                        if (!$res) {
                            $this->setMessage('服务器错误');
                            $this->setstatusCode(500);
                        }
                    } else {
                        $this->setMessage('暂无数据');
                        $this->setstatusCode(5000);
                    }
                }
            }else{//无店铺
                $this->setMessage('无店铺');
                $this->setstatusCode(5000);
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
     * 分享样式
     */
    public function shareInfo(Request $request){
        $exist = ShopModel::where(['user_id'=>$request->uid])
            ->first(['id','introduce']);
        $proxy_number = ShopAgentModel::where(['loaner_id'=>$request->loaner_id,'shop_id'=>$exist->id,'status'=>1])
            ->count();
        $apply_number = LoanModel::where(['loaner_id'=>$request->loaner_id,'status'=>1])->count();
        $info['proxy_number'] = $proxy_number;
        $info['apply_number'] = $apply_number;
        $info['introduce'] = $exist?$exist->introduce:'';
        $this->setData($info);
        return $this->result();
    }

    /**
     * 分享后的顶部信息
     * id 店铺id
     */
    public function getHeader($id){
        $shopInfo = ShopModel::with(['loaner'=>function($query){
                $query->select(['id','loanername','loanername_mobile','id','is_auth','user_id','header_img','score','city_id']);
            }])
            ->find($id,['id','wechat','special','loaner_id','pageviews']);
        if(count($shopInfo)) {
            $info['special'] = $shopInfo->special;
            $info['name'] = $shopInfo->loaner['loanername'];
            $info['is_auth'] = $shopInfo->loaner['is_auth'];
            $info['score'] = $shopInfo->loaner['score'];
            $city = RegionModel::find($shopInfo->loaner['city_id'],['name']);
            $info['city'] = $city ? $city->name:'暂无';
            $info['views'] = $shopInfo->pageviews;
            $proxy_number = ShopAgentModel::where(['loaner_id'=>$shopInfo->loaner['id'],'shop_id'=>$shopInfo->id,'status'=>1])
                ->count();
            $apply_number = LoanModel::where(['loaner_id'=>$shopInfo->loaner['id'],'status'=>1])->count();
            $info['proxy_number'] = $proxy_number;
            $info['header_img'] = $this->imgUrl.$shopInfo->loaner['header_img'];
            $info['apply_number'] = $apply_number;
            $info['introduce'] = $shopInfo?$shopInfo->special:'';
            $this->setData($info);
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }
    /**
     * 编辑名片信息
     */
    public  function getInformation(Request $request){
        $url = $request->url;
        $shopInfo = ShopModel::where(['user_id'=>$request->id])
            ->with(['loaner'=>function($query){
                $query->select(['loanername','loanername_mobile','id']);
            }])
            ->first(['id','wechat','special','loaner_id']);
        if(count($shopInfo)) {
            $info['wechat'] = $shopInfo->wechat;
            $info['special'] = $shopInfo->special;
            $info['name'] = $request->loanername;
            $info['mobile'] = $request->loaner_mobile;
            if ($url) {
                $qrcode = $this->scerweima($url);
                ShopModel::where(['user_id' => $request->id])
                    ->update(['qrcode' => $qrcode]);
                $info['qrcode'] = $this->imgUrl . $qrcode;
            }
            $this->setData($info);
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * 分享名片信息
     */
    public  function shareInformation(Request $request){
        $url = $request->url?$request->url:'http://api.kuanjiedai.com/index.html';
        $shopInfo = ShopModel::where(['user_id'=>$request->id])
            ->with(['loaner'=>function($query){
                $query->select(['loanername','loanername_mobile','id']);
            }])
            ->first(['id','wechat','special','loaner_id']);
        if(count($shopInfo)) {
            $info['wechat'] = $shopInfo->wechat;
            $info['special'] = $shopInfo->special;
            $info['name'] = $shopInfo->loaner['loanername'];
            $info['mobile'] = $shopInfo->loaner['loanername_mobile'];
            $qrcode = $this->scerweima($url);
            ShopModel::where(['user_id'=>$request->id])
                ->update(['qrcode'=>$qrcode]);
            $info['qrcode'] = $this->imgUrl. $qrcode;
            $this->setData($info);
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * 编辑名片信息
     */
    public  function updateInformation(Request $request){
        $validator = Validator::make($request->all(), [
            'wechat' => 'required',//微信
            'special' => 'required',//操作
        ]);
        if (!$validator->fails()) {//
            ShopModel::where(['loaner_id'=>$request->loaner_id])
                ->update(['wechat'=>$request->wechat,'special'=>$request->special]);
        }
        else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 已代理列表
     */
    public function getProxyList($id)
    {
        //系统产品代理记录
        $sysIds = ShopAgentModel::where([['shop_id','=',$id],['sys_pro_id','>',0],['status','=',1]])
            ->orderBy('update_time','desc')
            ->orderBy('id','desc')
            ->get()
            ->pluck('sys_pro_id');
        if(count($sysIds))
        {
            $sysIds = $sysIds->toArray();
        }else{
            $sysIds = null;
        }
            if($sysIds)
            {
                $list = ProductModel::whereIn('id',$sysIds)
                    ->orderBy('id','desc')
                    ->orderBy('create_time','desc')
                    ->limit($this->pageSize)
                    ->get(['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id','loan_number_start','loan_number_end']);
            }
            else{
                $list = null;
            }
        if(count($list))
        {
            $shop = ShopModel::find($id,['loaner_id']);
            foreach($list as &$item)
            {
                $res = $this->getProductOptions($item->option_values,$item->need_data);
                $item->options = $res['apply_condition'];
                $item->need_data = $res['need_data'];
                $time_limit = ProductAttrValueModel::find($item->time_limit_id);
                if($time_limit)
                {
                    $item->time_limit = $time_limit->attr_value;
                }
                //判断代理
                $is_proxy = ShopAgentModel::where(['shop_id' => $id,'sys_pro_id'=>$item->id, 'status' => 1])->first();
                $item->is_proxy = 1;
                $item->proxy_time = $is_proxy ? $is_proxy->create_time: 0;
                //代理人数
                $item->loan_number = str_replace(',','-',$item->loan_number).'万元';
                $item->rate = str_replace(',','-',$item->rate).'%';
                $type = ProductAttrValueModel::find($item->cate_id);
                $item->loan_type = $type ? $type->attr_value:'';
                $item->loan_day = $item->loan_day.'天';
                $item->apply_peoples = ShopAgentModel::where(['sys_pro_id'=>$item->id,'loaner_id'=>$shop->loaner_id])->count();
                $item->platform = 'system';
                unset($item->service_options);
                unset($item->option_values);
            }
            $this->setData($list);
        }
        else{
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }
    /**
     * @param $id
     * 订单详情
     */
    public function proxyShow(Request $request){
        $validator = Validator::make($request->all(), [
            'platform' => 'required',//平台
            'id' => 'required',//产品id
            'loaner_id'=>'required',
        ]);
        if (!$validator->fails()) {
            if ($request->platform == 'system') {
                $product = Redis::hget('products', 'system:' . $request->id);
                if ($product) {
                    $product = json_decode($product);
                    $arr['title'] = $product->title;
                    $arr['id'] = $request->id;
//                        $arr['loaner_id'] = $request->loaner_id;
                    $arr['rate'] = strpos(implode(',', $product->rate) . '%', ',') ? str_replace(',', '-', implode(',', $product->rate) . '%') : implode(',', $product->rate) . '%';
                    $arr['time_limit'] = strpos(implode(',', $product->time_limit) . '个月', ',') ? str_replace(',', '-', implode(',', $product->time_limit) . '个月') : implode(',', $product->time_limit) . '个月';
                    $arr['loan_number'] = strpos(implode(',', $product->loan_number) . '万元', ',') ? str_replace(',', '-', implode(',', $product->loan_number) . '万元') : implode(',', $product->loan_number) . '万元';
                    $arr['loan_day'] = $product->loan_day . '天';

                    $arr['loan_type'] = $product->cate;
                    $number = ProductModel::find($request->id, ['apply_peoples']);
                    $arr['apply_peoples'] = $number ? $number->apply_peoples : 0;
                    $arr['need_data'] = implode(',', $product->need_data);
                    $arr['options'] = $product->service_options;
                    $arr['platform'] = 'system';
                    //代理时间
                    $agent = ShopAgentModel::where(['loaner_id' => $request->loaner_id, 'sys_pro_id' => $request->id])->first();
                    if (count($agent)) {
                        $arr['is_proxy'] = 1;
                        $arr['proxy_time'] = $agent->create_time;
                    } else {
                        $arr['is_proxy'] = 0;
                        $arr['proxy_time'] = 0;
                    }
                    $this->setData($arr);
                } else {
                    $this->setMessage('产品不存在');
                    $this->setstatusCode(5000);
                }
            } elseif ($request->platform == 'third') {
                $product = ProductOtherModel::find($request->id, ['id',
                    'title', 'rate', 'time_limit', 'property as cate', 'age', 'credit', 'loan_day', 'loan_number', 'service_city', 'need_options as need_data', 'need_identity', 'need_trade', 'work_year', 'income', 'repayment', 'need_security']);
                if (count($product)) {
//                        $arr['loaner_id'] = $request->loaner_id;
                    $arr['id'] = $request->id;
                    $arr['title'] = $product->title;
                    $arr['rate'] = $product->rate;
                    $arr['time_limit'] = $product->time_limit;
                    $arr['loan_type'] = $product->cate;
                    $arr['loan_day'] = $product->loan_day;
                    $arr['loan_number'] = $product->loan_number;
                    $arr['apply_people'] = ProductOtherModel::find($request->id, ['apply_peoples'])->apply_peoples;
                    $arr['platform'] = 'third';
                    $arr['need_data'] = trim($product->need_data, ' ');
                    $arr['options'] = [];
                    if ($product->credit) {
                        $arr['options'][] = ['option_name' => '征信要求', 'option_values' => $product->credit];
                    }
                    if ($product->identity) {
                        $arr['options'][] = ['option_name' => '身份要求', 'option_values' => $product->identity];
                    }
                    if ($product->income) {
                        $arr['options'][] = ['option_name' => '收入', 'option_values' => $product->income];
                    }
                    if ($product->work_year) {
                        $arr['options'][] = ['option_name' => '工作要求', 'option_values' => $product->work_year];
                    }
                    if ($product->social_security) {
                        $arr['options'][] = ['option_name' => '是否需要购买保险', 'option_values' => $product->social_security];
                    }
                    //代理时间
                    $agent = ShopAgentModel::where(['loaner_id' => $request->loaner_id, 'sys_pro_id' => $request->id])->first();
                    if (count($agent)) {
                        $arr['is_proxy'] = 1;
                        $arr['proxy_time'] = $agent->create_time;
                    } else {
                        $arr['is_proxy'] = 0;
                        $arr['proxy_time'] = 0;
                    }
                    $this->setData($arr);
                } else {
                    $this->setMessage('产品不存在');
                    $this->setstatusCode(5000);
                }
            }
        }else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     */
    public function getQrcode(Request $request)
    {
        $url = $this->scerweima($request->url);
        $this->setData(['qrcode'=>$this->imgUrl.$url]);
        return $this->result();
    }
}
