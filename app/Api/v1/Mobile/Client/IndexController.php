<?php

namespace App\Api\v1\Mobile\Client;

use App\Api\Models\LoanExamineModel;
use Illuminate\Http\Request;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanEvaluateModel;
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
use App\Api\Models\UserFavoriteModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class IndexController extends BaseController
{
    /**
     * 找顾问
     */
    public function manager(){
        $list = LoanerModel::where([['is_auth','=',3],['proxy_number','>',0]])
            ->with(['auth'=>function($query){
                $query->select('user_id','photo');
            }])
            ->select(['id','loanername as name','max_loan','tag','all_number','loan_day','score','attr_ids','user_id','proxy_number'])
            ->orderBy('id','desc')->limit(6)->get();
        if(count($list)){
            foreach($list as &$item)
            {
                $item->header_img = $item->auth['photo'];
                if($item->attr_ids)
                {
                    $tags = LoanerAttrModel::whereIn('id',explode(',',$item->attr_ids))
                        ->get()
                        ->pluck('attr_value');
                    $tags = implode(',',$tags->toArray());
                }else{
                    $tags = null;
                }
                unset($item->auth);
                $item->tags = $tags;
            }
            $this->setData($list);
        }else{
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
        $list = ProductAttrModel::where(['condition_app' => 'app'])
            ->with(['values' => function ($query) {
                $query->select(['id', 'attr_id', 'attr_value as options']);
            }])
            ->orderBy('sort_app')
            ->get(['attr_key as name', 'id','condition_app']);
        if (count($list)) {
            $this->setData($list);
        } else {
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 搜索信贷经理
     */
    public function search(Request $request)
    {
        $ids = $request->ids;
        if ($ids) {
            $arr = explode(',', $ids);
            $conditionArr = ProductAttrValueModel::whereIn('id', $arr)->pluck('attr_value');
            $condition = $conditionArr->toArray();
            $res = Redis::hgetall('products');
            $keys = [];
            foreach ($res as $key => $item) {
                //多个条件：==4
                if (count($condition) == 1) {
                    if (strpos($item, $condition[0])) {
                        $keys[] = $key;
                    }
                } elseif (count($condition) == 2) {
                    if (strpos($item, $condition[0]) && strpos($item, $condition[1])) {
                        $keys[] = $key;
                    }
                } elseif (count($condition) == 3) {
                    if (strpos($item, $condition[0]) && strpos($item, $condition[1]) && strpos($item, $condition[2])) {
                        $keys[] = $key;
                    }
                } elseif (count($condition) == 4) {
                    if (strpos($item, $condition[0]) && strpos($item, $condition[1]) && strpos($item, $condition[2]) && strpos($item, $condition[3])) {
                        $keys[] = $key;
                    }
                }
            }
            if (count($keys)) {
                /**************/
                $third = [];
                $system = [];
                foreach ($keys as $k) {
                    if (strpos($k, 'system') !== false) {
                        $system[] = substr($k, strpos($k, 'system:') + 7);
                    } else {
                        $third[] = substr($k, strpos($k, 'third:') + 6);
                    }
                }
                /**************/
                $system ? sort($system) : [];
                $third ? sort($third) : [];
                /**************/
//                DB::connection()->enableQueryLog();
                $loaners = ShopAgentModel::whereIn('sys_pro_id', $system)
                    ->orWhereIn('third_pro_id', $third)
                    ->get(['loaner_id'])->pluck('loaner_id');
//                $log = DB::getQueryLog();
//                dd($log);
                if (count($loaners)) {
                    $list = LoanerModel::whereIn('id', $loaners->toArray())
                        ->select(['id', 'loanername', 'loanername_mobile', 'header_img', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'user_id', 'all_number', 'is_auth', 'attr_ids'])
                        ->orderBy('id', 'desc')
                        ->paginate($this->pageSize);
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
                } else {
                    $this->setMessage('暂无数据');
                    $this->setstatusCode(5000);
                }
            } else {
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
        } else {
//            DB::connection()->enableQueryLog();
            $list = LoanerModel::where(['is_display' => 1])
                ->select(['id', 'loanername', 'loanername_mobile', 'header_img', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'user_id', 'all_number', 'is_auth', 'attr_ids'])
                ->orderBy('id', 'desc')
                ->paginate($this->pageSize);
//            $log = DB::getQueryLog();
//            dd($log);
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
        }
        return $this->result();
    }


    /**
     * @param Request $request
     * @return mixed
     * 举报用户
     */
    public function report(Request $request)
    {
        $uid = $this->checkUid($request->header('deviceid'));
        $validator = Validator::make($request->all(), [
            'to_uid' => 'required',//信贷经理uid
            'loan_id' => 'required',//订单id
            'comment' => 'required',//举报内容
        ]);
        if (!$validator->fails()) {
            $exist = ReportModel::where(['to_uid' => $request->to_uid, 'from_uid' => $uid, 'type' => 2])
                ->first();
            if (!count($exist)) {
                DB::beginTransaction();
                try {
                    $res = ReportModel::create(['create_time' => time(), 'to_uid' => $request->to_uid, 'from_uid' => $uid, 'report_reason' => $request->comment, 'type' => 1, 'loan_id' => $request->loan_id]);
                    $from = UserModel::find($uid, ['username']);
                    $to = UserModel::find($request->to_uid, ['username']);
                    ReportModel::where(['id' => $res->id])
                        ->update(['from_name' => $from->username, 'to_name' => $to->username]);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();//事务回滚
                    $this->setstatusCode(500);
                    $this->setMessage('服务器错误');
                }
            } else {
                $this->setstatusCode(4010);
                $this->setMessage('重复举报');
            }
        } else {
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param $shopid 店铺id
     * @param $id 代理产品表id
     * 单个产品详情
     */
    public function single(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required',//平台
            'id' => 'required',//产品id
            'loaner_id' => 'required',//信贷经理id
        ]);
        if (!$validator->fails()) {
            $agent = ShopAgentModel::where(['loaner_id' => $request->loaner_id, 'sys_pro_id' => $request->id])->first();
            if (count($agent)) {
                if ($request->platform == 'system') {
                    $product = Redis::hget('products', 'system:' . $request->id);
                    if ($product) {
                        $product = json_decode($product);
                        $arr['title'] = $product->title;
                        $arr['id'] = $request->id;
                        $arr['loaner_id'] = $request->loaner_id;
                        $arr['rate'] = strpos(implode(',', $product->rate) . '%', ',') ? str_replace(',', '-', implode(',', $product->rate) . '%') : implode(',', $product->rate) . '%';
                        $arr['time_limit'] = strpos(implode(',', $product->time_limit) . '个月', ',') ? str_replace(',', '-', implode(',', $product->time_limit) . '个月') : implode(',', $product->time_limit) . '个月';
                        $arr['loan_number'] = strpos(implode(',', $product->loan_number) . '万元', ',') ? str_replace(',', '-', implode(',', $product->loan_number) . '万元') : implode(',', $product->loan_number) . '万元';
                        $arr['loan_day'] = $product->loan_day . '天';

                        $arr['category'] = $product->cate;
                        $number = ProductModel::find($request->id, ['apply_peoples']);
                        $arr['apply_people'] = $number ? $number->apply_peoples : 0;
                        $arr['need_data'] = implode(',', $product->need_data);
                        $arr['options'] = $product->service_options;
                        $arr['platform'] = 'system';
                        //代理时间

                        $agent = ShopAgentModel::where(['loaner_id' => $request->loaner_id, 'sys_pro_id' => $request->id])->first();
                        $arr['agent_time'] = $agent ? $agent->create_time : 0;
                        $this->setData($arr);
                    } else {
                        $this->setMessage('产品不存在');
                        $this->setstatusCode(5000);
                    }
                } elseif ($request->platform == 'third') {
                    $product = ProductOtherModel::find($request->id, ['id',
                        'title', 'rate', 'time_limit', 'property as cate', 'age', 'credit', 'loan_day', 'loan_number', 'service_city', 'need_options as need_data', 'need_identity', 'need_trade', 'work_year', 'income', 'repayment', 'need_security']);
                    if (count($product)) {
                        $arr['loaner_id'] = $request->loaner_id;
                        $arr['id'] = $request->id;
                        $arr['title'] = $product->title;
                        $arr['rate'] = $product->rate;
                        $arr['time_limit'] = $product->time_limit;
                        $arr['category'] = $product->cate;
                        $arr['loan_day'] = $product->loan_day;
                        $arr['loan_number'] = $product->loan_number;
                        $arr['apply_people'] = ProductOtherModel::find($request->id, ['apply_peoples'])->apply_peoples;
                        $arr['platform'] = 'third';
                        $arr['need_data'] = $product->need_data;
                        $arr['options'] = [];
                        if ($product->credit) {
                            $arr['options'][] = ['option_name' => '征信要求', 'option_values' => $product->credit];
                        }
                        if ($product->identity) {
                            $arr['options'][] = ['option_name' => '身份要求', 'option_values' => $product->identity];
                        }
                        if ($product->social_security) {
                            $arr['options'][] = ['option_name' => '是否需要购买保险', 'option_values' => $product->social_security];
                        }
                        $agent = ShopAgentModel::where(['loaner_id' => $request->loaner_id, 'sys_pro_id' => $request->id])->first();
                        $arr['agent_time'] = $agent ? $agent->create_time : 0;
                        $this->setData($arr);
                    } else {
                        $this->setMessage('产品不存在');
                        $this->setstatusCode(5000);
                    }
                }
            } else {
                $this->setMessage('暂无代理记录');
                $this->setstatusCode(5000);
            }
        }else{
                $this->setMessage('参数不全');
                $this->setstatusCode(4002);
            }
        return $this->result();
    }


    /**
     * 贷款申请条件
     */
    public function applyConfig()
    {
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
      //        $log = DB::getQueryLog();
//        dd($log);
        $assets = LoanerAttrModel::where(['function_name'=>'junk_loan_assets'])
            ->with(['values'=>function($query){
                $query->select(['id','attr_value as name','pid']);
            }])
            ->orderBy('sort')
            ->get(['id','attr_value as name']);
        $this->setData(['work'=>$work,'assets'=>$assets,'basic'=>$basic]);
        return $this->result();
    }

    /**
     * @param Request $request
     * 贷款申请
     */
    public function applyLoan(Request $request)
    {
        $uid = $this->checkUid($request->header('deviceid'));
        $validator = Validator::make($request->all(), [
            'name' => 'required',//申请人
            'apply_number' => 'required',//贷款金额
            'region_id' => 'required',//区域
            'assets_information' => 'required',//申请信息:1,2,3
            'job_information' => 'required',//申请信息:1,2,3
            'age' => 'required',//年龄1990
            'loaner_id' => 'required',//信贷经理id
            'time_limit' => 'required',//贷款期限
            'loan_type' => 'required',//贷款类型
            'is_marry' => 'required',//
            'province_id' => 'required',//省份id
            'city_id' => 'required',//城市id
        ]);
        if (!$validator->fails()) {
            //TODO::apply RULES
            //TODO::apply 更新 相应的 代理产品 申请数量
            $info = UserModel::find($uid, ['mobile']);
            $orderNo = date("YmdHis") . rand(100000, 999999);
            DB::beginTransaction();
            try {
                $res = LoanModel::create(['loan_account' => $orderNo, 'user_id' => $uid, 'create_time' => time(), 'apply_number' => $request->apply_number, 'region_id' => $request->region_id, 'name' => $request->name, 'apply_information' => $request->assets_information, 'job_information' => $request->job_information, 'loaner_id' => $request->loaner_id, 'product_id' => $request->product_id, 'age' => $request->age, 'loan_type' => $request->loan_type, 'is_marry' => $request->is_marry, 'time_limit' => $request->time_limit, 'type' => 1, 'mobile' => $info->mobile, 'description' => $request->describe, 'province_id' => $request->province_id, 'city_id' => $request->city_id, 'check_result' => 2]);
                //添加信息
                NoticeModel::create(['to_uid' => $uid, 'title' => '订单信息', 'content' => '下单成功', 'create_time' => time(), 'type' => 1]);
                //TODO::进度控制
                LoanExamineModel::create(['loaner_id' => $request->loaner_id, 'loan_id' => $res->id, 'process' => 11, 'create_time' => time(), 'describe' => '您的申请提交成功,请保持电话畅通,稍后客服与您联系']);
                DB::commit();
                $info = LoanerModel::find($request->loaner_id, ['loanername_mobile as mobile']);
                $this->setData(['mobile' => $info->mobile, 'id' => $res->id]);
            } catch (\Exception $e) {
                DB::rollback();//事务回滚
                $this->setstatusCode(500);
                $this->setMessage('服务器错误');
            }
        } else {
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
                        $res = LoanModel::create(['loan_account' => $orderNo, 'user_id' => $uid, 'create_time' => time(), 'apply_number' => $order->apply_number, 'region_id' => $order->region_id, 'name' => $order->name, 'apply_information' => $order->assets_information, 'job_information' => $order->job_information, 'loaner_id' => $item, 'age' => $order->age, 'loan_type' => $order->loan_type, 'is_marry' => $order->is_marry, 'time_limit' => $order->time_limit, 'type' => 1, 'mobile' => $order->mobile, 'description' => $order->describe, 'province_id' => $order->province_id, 'city_id' => $order->city_id, 'check_result' => 2]);
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

    /**
     * 检查是否收藏
     */
    public function checkFavorite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',//信贷经理1|文章2
            'id' => 'required',//id
        ]);
        if (!$validator->fails())
        {
            $exist = UserFavoriteModel::where(['user_id' => $request->uid, 'object_id' =>  $request->id, 'type' => $request->type])->first();
            if(count($exist))
            {
                $this->setData(['is_favorite'=>1]);
            }else{
                $this->setData(['is_favorite'=>0]);
            }
        }
        else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param $id
     * 评价列表
     */
    public function evaluate(Request $request){
        $validator = Validator::make($request->all(), [
            'type' => 'required',//信贷经理1|文章2
            'id' => 'required',//id
        ]);
        if (!$validator->fails()) {
            $ids = LoanModel::where(['is_comment' => 2, 'loaner_id' => $request->id])
                ->get(['id'])
                ->pluck('id');
            if (count($ids)) {
                if ($request->type == 5) {
                    $where = [['score_avg', '=', 5], ['status', '=', 1]];
                } elseif ($request->type == 4) {
                    $where = [['score_avg', '<', 5], ['score_avg', '>=', 4], ['status', '=', 1]];
                } elseif ($request->type == 3) {
                    $where = [['score_avg', '<', 4], ['status', '=', 1]];
                } else {
                    $where = ['status' => 1];
                }
                $list = LoanEvaluateModel::whereIn('loan_id', $ids->toArray())
                    ->with(['user' => function ($query) {
                        $query->select(['id', 'username']);
                    }])
                    ->where($where)
                    ->orderBy('id', 'desc')
                    ->paginate($this->pageSize, ['loan_id', 'user_id', 'describe', 'create_time', 'score_avg', 'focus']);
                if (count($list)) {
                    $list = $list->toArray();
                    foreach ($list['data'] as &$item) {
                        $item['username'] = $item['user']['username'];
                        $score = (string)($item['score_avg']);
                        if (strpos($score, '.') !== false) {
                            if (substr($score, strpos($score, '.') + 1) > 5) {
                                $score = ceil($score);
                            } elseif (substr($score, strpos($score, '.') + 1) < 5) {
                                $score = floor($score);
                            }
                        }
                        $item['score_avg'] = (double)$score;
                        unset($item['user']);
                    }
                    $this->setData($list);
                } else {
                    $this->setMessage('暂无信息');
                    $this->setstatusCode(5000);
                }
            } else {
                $this->setstatusCode(5000);
                $this->setMessage('暂无数据');
            }
        }
        else{
            $this->setstatusCode(4002);
            $this->setMessage('参数不全');
        }
        return $this->result();
    }

    /**
     * @param $id //信贷经理id
     * 用户评价
     */
    public function average($id){
        //验证token
        $list = LoanModel::where(['is_comment'=>2,'loaner_id'=>$id])
            ->get()
            ->pluck(['id']);
        if(count($list))
        {
            //TODO::优化
            $arr = [];
            //综合评分
            $avg = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->avg('score_avg');
            //好评数量
            $excellent = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->where(['score_avg'=>5.0])
                ->count();
            //中评数量
            $better = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->where([['score_avg','<',5.0],['score_avg','>=',4.0]])
                ->count();
            //差评数量
            $good = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->where('score_avg','<',4.0)
                ->count();
            $arr['excellent'] = $excellent ? $excellent : 0;
            $arr['counts'] = count($list);
            $avg = (string)number_format($avg,1);
            if(strpos($avg,'.') !== false)
            {
                $avg = substr($avg,strpos($avg,'.')+1) >5 ? ceil($avg) : (substr($avg,strpos($avg,'.')+1) <5 ? floor($avg):$avg);
            }else{
                $avg = 0;
            }
            $arr['average'] = $avg;
            $arr['better'] = $better ? $better : 0;
            $arr['good'] = $good ? $good : 0;
            //标签频率
            $focus = LoanEvaluateModel::whereIn('loan_id', $list->toArray())
                ->get()
                ->pluck(['focus']);
            if(count($focus))
            {
                $tags = '';
                foreach($focus as $item)
                {
                    $tags .= ','.$item;
                }
                $tag = array_count_values(explode(',',trim($tags,',')));
                if($tag)
                {
                    $tagArr = [];
                    foreach($tag as $k=>$t)
                    {
                        $exist = LoanerAttrModel::find($k);
                        if(count($exist)) {
                            $tagArr[] = ['tag'=>$exist->attr_value,'times'=>$t];
                        }
                    }
                    $arr['tag'] = $tagArr;
                }else {
                    $arr['tag'] = null;
                }
            }
            else{
                $arr['tag'] = null;
            }
            $this->setData($arr ? $arr : null);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 检查是否有未读消息
     */
    public function checkNotice(Request $request){
        $exist = NoticeModel::where(['to_uid'=>$request->uid,'status'=>1])
            ->count();
        if($exist)
        {
            $this->setData(['no_read'=>'1']);
        }
        else{
            $this->setData(['no_read'=>'0']);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 搜索顾问
     */
    public function mapLoaner(Request $request){
        if($request->city) {
            $loanerArr=[];//最终产品集合
            $city = RegionModel::where(['name' => str_replace('市', '', $request->city)])->first(['id']);
            if(!(!$request->loan_day && !$request->loan_number && !$request->time_limit && !$request->type && !$request->way)) {
                ///产品库信息
                $where = [];
                $orWhere = [];
                if ($request->loan_day) {//搜索放款时间
                    //id
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
                //类型
                if ($request->type) {
                    $where[] = ['cate_id', '=', $request->type];
                }

                /*查询符合条件的产品*/
                $proList = [];
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
                ///////////////////还款方式：Redis
                $way = '';
                $redisList = [];
                if ($request->way) {
                    $loan_way = ProductAttrValueModel::find($request->way);
                    $way = $loan_way->attr_value;
                }
                if ($way) {
                    $res = Redis::hgetall('products');
                    $keys = [];
                    foreach ($res as $key => $item) {
                        //多个条件：==6
                        if (strpos($item, $way)) {
                            $keys[] = $key;
                        }
                    }
                    if ($keys) {
                        foreach ($keys as $key) {
                            $redisList[] = substr($key, strpos($key, ':') + 1);
                        }
                    }
                }
                //////////最终集合
                if ($proList && $redisList) {
                    $result = array_intersect($redisList, $proList->toArray());
                } elseif (!$proList && $redisList) {
                    $result = $redisList;
                } elseif ($proList && !$redisList) {
                    $result = $proList->toArray();
                } else {
                    $result = [];
                }
                //查询符合条件的信贷经理
                if($result) {
                    //最终产品集合
                    $loanerArr = ShopAgentModel::whereIn('sys_pro_id', $result)
                        ->get(['loaner_id'])->pluck('loaner_id');
                }
                if($loanerArr)
                {
                    $loanerArr = $loanerArr->toArray();
                    $loanerArr = array_unique($loanerArr);//信贷经理集合
                    $loanerList = LoanerModel::where([['city_id', '=', $city->id], ['is_auth', '=', 3], ['proxy_number', '>', 0]])
                        ->whereIn('id', $loanerArr)
                        ->orderBy('id', 'desc')
                        ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'attr_ids', 'loaner_lng', 'loaner_lat', 'is_auth'])
                        ->paginate($this->pageSize);
                }else{
                    $loanerList = [];
                }
            }else{
                $whereCity = [];
                if($city){
                    $whereCity[] = ['city_id','=',$city->id];
                }
                if($request->region){
                    $whereCity[] = ['region_id','=',$request->region];
                }
                if(!$whereCity) {
                    $loanerList = LoanerModel::where([['is_auth', '=', 3], ['proxy_number', '>', 0]])
                        ->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'user_id', 'all_number', 'is_auth', 'attr_ids', 'proxy_number','loaner_lng','loaner_lat','city_id','region_id'])
                        ->orderBy('id', 'desc')
                        ->paginate($this->pageSize);
                }else{
                    $loanerList = LoanerModel::where([['is_auth', '=', 3], ['proxy_number', '>', 0]])
                        ->where($whereCity)
                        ->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'user_id', 'all_number', 'is_auth', 'attr_ids', 'proxy_number','loaner_lng','loaner_lat','city_id','region_id'])
                        ->orderBy('id', 'desc')
                        ->paginate($this->pageSize);
                }
            }
        if (count($loanerList))
        {
                $result = $loanerList->toArray();
                foreach ($result['data'] as &$item) {
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
                //区域数据统计：
                $areas = RegionModel::where(['pid' => $city->id])
                    ->get(['id', 'name','lat','lng']);
                foreach ($areas as &$area)
                {
                    if($request->region){
                        if($area->id == $request->region) {
                            if ($loanerArr) {
                                $area->counts = LoanerModel::where(['city_id' => $city->id, 'region_id' => $area->id])
                                    ->whereIn('id', $loanerArr)
                                    ->where([['is_auth', '=', 3], ['proxy_number', '>', 0]])
                                    ->count();
                            } else {
                                $area->counts = LoanerModel::where(['city_id' => $city->id, 'region_id' => $area->id])
                                    ->where([['is_auth', '=', 3], ['proxy_number', '>', 0]])
                                    ->count();
                            }
                        }else{
                            $area->counts = 0;
                        }
                    }else {
                        if ($loanerArr) {
                            $area->counts = LoanerModel::where(['city_id' => $city->id, 'region_id' => $area->id])
                                ->whereIn('id', $loanerArr)
                                ->where([['is_auth', '=', 3], ['proxy_number', '>', 0]])
                                ->count();
                        } else {
                            $area->counts = LoanerModel::where(['city_id' => $city->id, 'region_id' => $area->id])
                                ->where([['is_auth', '=', 3], ['proxy_number', '>', 0]])
                                ->count();
                        }
                    }
                    if(!$area->lng) {
                        $lat_lng = $this->get_lng_lat($area->name);
                        if ($lat_lng) {
                            $area->lat = $lat_lng['location']['lat'];
                            $area->lng = $lat_lng['location']['lng'];
                            RegionModel::where(['id'=>$area->id])
                                ->update(['lat'=>$lat_lng['location']['lat'],'lng'=>$lat_lng['location']['lng']]);
                        } else {
                            $area->lat = '';
                            $area->lng = '';
                        }
                    }
                }
                $this->setData(['list' => $result, 'map' => $areas]);
            } else {
                $this->setstatusCode(5000);
                $this->setMessage('暂无信贷经理');
            }
        }else{
            $this->setstatusCode(4002);
            $this->setMessage('城市必须');
        }
        return $this->result();

    }

    /**
     * 顾问搜索匹配
     */
    public function config(){
        $list = ProductAttrModel::where(['id'=>5])
            ->with(['values'=>function($query){
                $query->select(['id','attr_value as name','attr_id']);
            }])
            ->get(['id']);
        if(count($list))
        {
            $this->setData($list);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }
}
