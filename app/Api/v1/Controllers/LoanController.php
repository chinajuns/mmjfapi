<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanExamineModel;
use App\Api\Models\LoanModel;
use App\Api\Models\NoticeModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductAttrValueModel;
use App\Api\Models\ProductModel;
use App\Api\Models\ShopAgentModel;
use App\Api\Models\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class LoanController extends BaseController
{
    /**
     * @return mixed
     * 筛选
     */
    public function search(Request $request,$region_id=null){
        $redisList = [];//符合条件的产品集合：redis
        $proList = []; //产品库符合条件的集合:mysql
//        $ids = $request->ids;
        if($request->by && in_array($request->by,['asc','desc'])){
            $by = $request->by;
        }else{
            $by = 'asc';
        }
        if($request->order && in_array($request->order,['score','loan_number','max_loan'])){
            $order = $request->order;
        }else{
            $order = 'id';
        }
        if($region_id && !(!$request->loan_day && !$request->loan_number && !$request->time_limit && !$request->type && !$request->credit && !$request->way)) {
            $where = [];
            $orWhere = [];
            //类型
            if ($request->type) {
                $where[] = ['cate_id', '=', $request->type];
            }
            //放款时间
            if ($request->loan_day) {
                $loan_day_attr = ProductAttrValueModel::find($request->loan_day, ['attr_value']);
                if ($request->loan_day != 61) {
                    $day = str_replace('个工作日', '', $loan_day_attr->attr_value);
                    $dayArr = explode('-', $day);
                    $where[] = ['loan_day', '>=', $dayArr[0]];
                    $orWhere[] = ['loan_day', '<=', $dayArr[1]];
                } else {
                    $day = str_replace('个工作日以上', '', $loan_day_attr->attr_value);
                    $where[] = ['loan_day', '>', $day];
                }
            }
            //需求额度
            if ($request->loan_number) {//搜索放款金额
                $loan_number_attr = ProductAttrValueModel::find($request->loan_number, ['attr_value']);
                $number = substr($loan_number_attr->attr_value, 0, strpos($loan_number_attr->attr_value, '万'));
                if ($request->loan_number == 31)//以下
                {
                    $where[] = ['loan_number_start', '<=', $number];
                    $orWhere[] = ['loan_number_end', '<=', $number];
                } elseif ($request->loan_number == 38)//以上
                {
                    $where[] = ['loan_number_end', '>', $number];
                } else {
                    $numberAttr = explode('-', $number);
                    $where[] = ['loan_number_start', '>=', $numberAttr[0]];
                    $orWhere[] = ['loan_number_start', '<', $numberAttr[1]];
                }
            }
            //贷款期限
            if ($request->time_limit) {
                $where[] = ['time_limit_id', '=', $request->time_limit];
            }

            /*查询符合前面个条件条件的产品*/
            if ($where && !$orWhere) {
                $proList = ProductModel::where($where)
                    ->get(['id'])
                    ->pluck('id');
            } elseif ($where && $orWhere) {
                $proList = ProductModel::where($where)
                    ->orWhere($orWhere)
                    ->get(['id'])
                    ->pluck('id');
            }
            //////////////信用情况+还款方式：通过hash 查询
            $credit = '';
            $way = '';
            if ($request->credit) {
                $credit_attr = ProductAttrValueModel::find($request->credit);
                $credit = $credit_attr->attr_value;
            }
            if ($request->way) {
                $loan_way = ProductAttrValueModel::find($request->way);
                $way = $loan_way->attr_value;
            }
            //信用要求
            if ($credit && $way) {
                $condition = [$credit, $way];
            } elseif (!$request->credit && $request->way) {//还款方式
                $condition = [$way];
            } elseif (!$request->credit && $request->way) {
                $condition = [$credit];
            } else {
                $condition = null;
            }
            if ($condition) {
                $res = Redis::hgetall('products');
                $keys = [];
                foreach ($res as $key => $item) {
                    //多个条件：==6
                    if (count($condition) == 1) {
                        if (strpos($item, $condition[0])) {
                            $keys[] = $key;
                        }
                    } elseif (count($condition) == 2) {
                        if (strpos($item, $condition[0]) && strpos($item, $condition[1])) {
                            $keys[] = $key;
                        }
                    }
                }
                if ($keys) {
                    foreach ($keys as $key) {
                        $redisList[] = substr($key, strpos($key, ':') + 1);
                    }
                }
            } else {
                $redisList = [];
            }
            if ($proList && $redisList) {
                $result = array_intersect($redisList, $proList->toArray());
            } elseif (!$proList && $redisList) {
                $result = $redisList;
            } elseif ($proList && !$redisList) {
                $result = $proList->toArray();
            } else {
                $result = [];
            }

        if($result) {
            //最终产品集合
            $loaners = ShopAgentModel::whereIn('sys_pro_id', $result)
                ->get(['loaner_id'])->pluck('loaner_id');
            if (count($loaners)) {
                if ($region_id) {
                    $list = LoanerModel::whereIn('id', $loaners->toArray())
                        ->where([['city_id', '=', $region_id], ['is_auth', '=', 3], ['proxy_number', '>', 0]])
                        ->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'user_id', 'all_number', 'is_auth', 'attr_ids', 'proxy_number'])
                        ->orderBy($order, $by)
                        ->paginate($this->pageSize);
                } else {
                    $list = LoanerModel::whereIn('id', $loaners->toArray())
                        ->where([['is_auth', '=', 3], ['proxy_number', '>', 0]])
                        ->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'user_id', 'all_number', 'is_auth', 'attr_ids', 'proxy_number'])
                        ->orderBy($order, $by)
                        ->paginate($this->pageSize);
                    }
                } else {
                    $list = [];
                }
            }else{
                $list = [];
            }
        }else{
            $list = LoanerModel::where([['is_auth', '=', 3], ['proxy_number', '>', 0],['city_id','=',$region_id]])
                ->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'user_id', 'all_number', 'is_auth', 'attr_ids', 'proxy_number'])
                ->orderBy($order, $by)
                ->paginate($this->pageSize);
        }
        if (count($list)) {
            $list = $list->toArray();
            foreach ($list['data'] as &$item) {
                if ($item['attr_ids']) {
                        $tags = LoanerAttrModel::whereIn('id', explode(',', $item['attr_ids']))
                            ->get()
                            ->pluck('attr_value');
                        $tags = implode(',', $tags->toArray());
                    } else {
                        $tags = null;
                    }
                    $item['tags'] = $tags;
                }
                $this->setData($list);
            } else {
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        return $this->result();
    }

    /**
     * 筛选配置
     */
    public function attrConfig()
    {
        $list = ProductAttrModel::where(['condition'=>'pc'])
            ->with(['values'=>function($query){
                $query->select(['id','attr_id','attr_value as name']);
            }])
            ->orderBy('sort')
            ->get(['attr_key as type','id']);
       if(count($list)){
           $this->setData($list);
       }else{
           $this->setMessage('暂无数据');
           $this->setstatusCode(5000);
       }
        return $this->result();
    }

    /**
     * 贷款申请条件
     */
    public function applyConfig(){
        $basic =  LoanerAttrModel::where(['function_name'=>'junk_loan_basic'])
            ->with(['values'=>function($query){
                $query->select(['id','attr_value as name','pid']);
            }])
            ->orderBy('sort')
            ->get(['id','attr_value as name']);
        $work = LoanerAttrModel::where([['function_name','=','junk_loan_job'],['id','<>',49],['id','<>',48]])
            ->with(['values'=>function($query){
                $query->select(['id','attr_value as name','pid']);
            }])
            ->orderBy('sort')
            ->get(['id','attr_value as name']);
//        DB::connection()->enableQueryLog();
        $need = LoanerAttrModel::where(['function_name'=>'junk_loan_assets','type'=>'pc_loot'])
            ->with(['values'=>function($query){
                $query->select(['id','attr_value as name','pid']);
            }])
            ->orderBy('sort')
            ->get(['id','attr_value as name']);
//        $log = DB::getQueryLog();
//        dd($log);
        $assets = LoanerAttrModel::where(['function_name'=>'junk_loan_assets','type'=>null])
            ->whereNotIn('id',[25,26])
            ->with(['values'=>function($query){
                $query->select(['id','attr_value as name','pid']);
            }])
            ->orderBy('sort')
            ->get(['id','attr_value as name']);
        $this->setData(['work'=>$work,'assets'=>$assets,'basic'=>$basic,'need'=>$need]);
        return $this->result();
    }

    /**
     * @param Request $request
     * 贷款申请
     */
    public function applyLoan(Request $request){
        $uid = $this->checkUid($request->header('deviceid'));
        $validator = Validator::make($request->all(), [
            'name' => 'required',//申请人
            'apply_number' => 'required',//贷款金额
            'region_id' => 'required',//区域
//            'assets_information' => 'required',//申请信息:1,2,3
            'job_information' => 'required',//申请信息:1,2,3
            'age' => 'required',//年龄1990
            'loaner_id'=>'required',//信贷经理id
            'time_limit'=>'required',//贷款期限
            'loan_type'=>'required',//贷款类型
            'is_marry'=>'required',//
            'province_id'=>'required',//省份id
            'city_id'=>'required',//城市id
            'income'=>'required',//收入
            'work_time'=>'required',//工龄
//            'product_id'=>'required'
        ]);
        if (!$validator->fails())
        {
            //TODO::apply RULES
            //TODO::apply 更新 相应的 代理产品 申请数量
            //TODO::产品列表i：代理id
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
            $info = UserModel::find($uid,['mobile']);
            $orderNo = date("YmdHis").rand(1000000,999999);
            DB::beginTransaction();
            try {
                $res = LoanModel::create(['loan_account'=>$orderNo,'user_id'=>$uid,'create_time'=>time(),'apply_number'=>$request->apply_number,'region_id'=>$request->region_id,'name'=>$request->name,'apply_information'=>trim($request->assets_information,','),'job_information'=>trim($job_information,','),'loaner_id'=>$request->loaner_id,'product_id'=>$request->product_id,'age'=>$request->age,'loan_type'=>$request->loan_type,'is_marry'=>$request->is_marry,'time_limit'=>$request->time_limit,'type'=>1,'mobile'=>$info->mobile,'description'=>$request->describe,'province_id'=>$request->province_id,'city_id'=>$request->city_id,'check_result'=>2]);
                //添加信息
                NoticeModel::create(['to_uid'=>$uid,'title'=>'订单信息','content'=>'下单成功','create_time'=>time(),'type'=>1]);
                //TODO::进度控制
                LoanExamineModel::create(['loaner_id'=>$request->loaner_id,'loan_id'=>$res->id,'process'=>11,'create_time'=>time(),'describe'=>'您的申请提交成功,请保持电话畅通,稍后客服与您联系']);
                if($request->id){
                    ShopAgentModel::where(['id'=>$request->id])
                        ->increment('apply_peoples');
                }
                ////
                if($request->product_id)
                {
                    ProductModel::where(['id'=>$request->product_id])
                        ->increment('apply_peoples');
                    ShopAgentModel::where(['sys_pro_id'=>$request->product_id,'loaner_id'=>$request->loaner_id])
                        ->increment('apply_peoples');
                }
                /////
                DB::commit();
                $info = LoanerModel::find($request->loaner_id,['loanername_mobile as mobile']);
                $this->setData(['mobile'=>$info?$info->mobile:'','id'=>$res->id]);
            } catch (\Exception $e) {
                DB::rollback();//事务回滚
                $this->setstatusCode(500);
                $this->setMessage('服务器错误');
            }
        }else{
            $this->setMessage('参数不全');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 快速贷款
     */
    public function quickApply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required',//信贷经理ids
            'id' => 'required',//上次申请贷款id
        ]);
        if (!$validator->fails()) {
            $arr = explode(',', $request->ids);
            $uid = $request->uid;
            $order = LoanModel::find($request->id, ['apply_number', 'region_id', 'name', 'apply_information', 'job_information', 'loaner_id', 'age', 'loan_type', 'is_marry', 'time_limit', 'mobile', 'description', 'province_id', 'city_id']);
            if ($order) {
                foreach ($arr as $item) {
                    $orderNo = date("YmdHis") . rand(100000, 999999);
                    DB::beginTransaction();
                    try {
                        $res = LoanModel::create(['loan_account' => $orderNo, 'user_id' => $uid, 'create_time' => time(), 'apply_number' => $order->apply_number, 'region_id' => $order->region_id, 'name' => $order->name, 'apply_information' => $order->apply_information, 'job_information' => $order->job_information, 'loaner_id' => $item, 'age' => $order->age, 'loan_type' => $order->loan_type, 'is_marry' => $order->is_marry, 'time_limit' => $order->time_limit, 'type' => 1, 'mobile' => $order->mobile, 'description' => $order->describe, 'province_id' => $order->province_id, 'city_id' => $order->city_id, 'check_result' => 2]);
                        //添加信息
                        NoticeModel::create(['to_uid' => $uid, 'title' => '订单信息', 'content' => '下单成功', 'create_time' => time(), 'type' => 1]);
                        //TODO::进度控制
                        LoanExamineModel::create(['loaner_id' => $request->loaner_id, 'loan_id' => $res->id, 'process' => 11, 'create_time' => time(), 'describe' => '您的申请提交成功,请保持电话畅通,稍后客服与您联系']);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollback();//事务回滚
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                }
            } else {
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        } else {
            $this->setMessage('参数不全');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * 推荐信贷经理
     */
    public function recommend()
    {
        $list = LoanerModel::where(['is_display' => 1])
            ->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag', 'loan_day', 'loan_number', 'score', 'all_number', 'is_auth', 'attr_ids'])
            ->orderBy('id', 'desc')->limit(2)->get();
        if (count($list)) {
            foreach ($list as &$item) {
                if ($item->attr_ids) {
                    $tags = LoanerAttrModel::whereIn('id', explode(',', $item->attr_ids))
                        ->get()
                        ->pluck('attr_value', 'id');
                    $tags = implode(',', $tags->toArray());
                } else {
                    $tags = null;
                }
                $item->tags = $tags;
            }
            $this->setData($list);
        } else {
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();

    }





}
