<?php

namespace App\Api\v1\Mobile\Client;

use App\Api\Models\JunkModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductModel;
use App\Api\Models\RegionModel;
use App\Api\Models\ShopAgentModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class LoanController extends BaseController
{
    public function index()
    {
        $list = LoanerModel::where(['is_auth'=>2,'is_display'=>1])
            ->paginate($this->pageSize,['id','loanername','loanername_mobile','tag','max_loan','loan_number','all_number','score','loan_day','is_auth','header_img','attr_ids']);
       if(count($list))
       {
           $list = $list->toArray();
           foreach($list['data'] as &$item)
           {
               if($item['attr_ids'])
               {
                   $tags = LoanerAttrModel::whereIn('id',explode(',',$item['attr_ids']))
                       ->get()
                       ->pluck('attr_value','id');
                   $tags = implode(',',$tags->toArray());
               }else{
                   $tags = null;
               }
               $item['tags'] = $tags;
           }
           $this->setData($list);
       }else{
           $this->setstatusCode(5000);
           $this->setMessage('暂无数据');
       }
        return $this->result();
    }
    /**
     * 搜索配置
     */
    public function config()
    {
        $config = ProductAttrModel::with(['values'=>function($query){
                $query->select(['id','attr_id','attr_value']);
            }])
            ->find(5,['id','attr_key']);
        $focus = LoanerAttrModel::with(['values'=>function($query){
            $query->where(['function_name'=>'c2b'])
            ->select(['id','attr_value','pid']);
        }])
            ->find(4,['attr_value as attr_key','id']);
        $arr['type'] = $config;
        $arr['focus'] = $focus;
        $this->setData($arr);
        return $this->result();
    }

    /**
     * 城市查詢
     */
    public function region(Request $request)
    {
        if($request->name)
        {
            $info = RegionModel::with(['district'=>function($query){
                $query->select(['name','id','pid']);
            }])
                ->where(['name'=>str_replace('市','',$request->name),'type'=>2])
            ->first(['name','id']);
            if(!count($info))
            {
                $this->setstatusCode(5000);
                $this->setMessage('暂无数据');
            }
            $this->setData($info);
        }else{
            $this->setstatusCode(4002);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }


    /**
     * @param Request $request
     * 测试
     */
    public function web(Request $request)
    {
        $is_vip=$request->input('is_vip','');
        $region_id=$request->input('region_id','');
        $has_house=$request->input('has_house','');
        $has_car=$request->input('has_car','');
        $create_time=$request->input('create_time',time());
        $query=JunkModel::from('junk_loan as jl')->select('jl.id','jl.name','jl.create_time','jl.is_vip','jl.apply_number','jl.title','la.attr_value','jl.price','jl.age','r.name as current_place','jl.mobile','jl.source_id','jl.apply_information')
            ->leftJoin('loaner_attr as la','la.id','=','jl.time_limit')
            ->leftJoin('region as r','r.id','=','jl.region_id')
            ->where(['jl.status'=>1]);

        if($is_vip!=''){
            $query=$query->where(['jl.is_vip'=>$is_vip]);
        }
        if($region_id!=''){
            $query=$query->where(['jl.region_id'=>$region_id]);
        }
//        if($has_house!=''){
//            if($has_house=='1'){
//                $query=$query->whereRaw("FIND_IN_SET(45,apply_information)");
//            }
//            if($has_house=='0'){
//                $query=$query->whereRaw("FIND_IN_SET(46,apply_information)");
//            }
//        }
//        if($has_car!=''){
//            if($has_house=='1'){
//                $query=$query->whereRaw("FIND_IN_SET(42,apply_information)");
//            }
//            if($has_house=='0'){
//                $query=$query->whereRaw("FIND_IN_SET(44,apply_information)");
//            }
//        }
//        DB::connection()->enableQueryLog();
        $list=$query->where('jl.create_time','<',1512113142)
            ->orderBy('jl.create_time','desc')
            ->limit($this->pageSize)->get();
//        $log = DB::getQueryLog();
        dd($list->toArray());

//        dd($log);
    }

    /**
     *
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'city' => 'required',//定位城市：成都市
        ]);
        if(!$validator->fails()) {
            $city = RegionModel::where(['type'=>2,'name'=>str_replace('市','',$request->city)])->first();
            if(count($city)) {
                $city_id = $city ? $city->id : '';
                $type = $request->input('type', '');//产品类型
                $focus = $request->input('focus_id', '');//印象
                $region_id = $request->input('region_id', '');//地区
                if ($type || $focus || $region_id)
                {
                    /**产品类型**/
                    $loanerList = [];
                    if ($type) {
                        $productList = ProductModel::where(['cate_id' => $type])
                            ->get()->pluck('id');
                        if (count($productList)) {
                            /*******产品ids*******/
                            $loanerList = ShopAgentModel::whereIn('sys_pro_id', $productList->toArray())
                                ->get(['loaner_id'])->pluck('loaner_id');
                            if (count($loanerList)) {
                                $loanerList = $loanerList->toArray();
                                $loanerList = array_unique($loanerList);
                            }
                        }
                    }
                    /////////////////
                    if ($region_id)//区域筛选
                    {
                        $where = [['region_id', '=', $region_id], ['is_auth', '=', 3]];
                    } else {
                        $where = ['is_auth' => 3,'city_id'=>$city_id];
                    }
                    if ($focus && $loanerList) {
                        $list = LoanerModel::where($where)
                            ->whereRaw("FIND_IN_SET($focus,attr_ids)")
                            ->orderBy('update_time','desc')
                            ->orderBy('id', 'desc')
                            ->paginate($this->pageSize, ['id', 'loanername', 'loanername_mobile', 'tag', 'max_loan', 'loan_number', 'all_number', 'score', 'loan_day', 'is_auth', 'header_img', 'attr_ids', 'region_id']);
                    } elseif (!$focus && $loanerList) {
                        $list = LoanerModel::where($where)
                            ->whereIn('id', $loanerList)
                            ->orderBy('id', 'desc')
                            ->paginate($this->pageSize, ['id', 'loanername', 'loanername_mobile', 'tag', 'max_loan', 'loan_number', 'all_number', 'score', 'loan_day', 'is_auth', 'header_img', 'attr_ids', 'region_id']);
                    } elseif(($focus && !$loanerList)) {
                        $list = LoanerModel::where($where)
                            ->whereRaw("FIND_IN_SET($focus,attr_ids)")
                            ->orderBy('update_time','desc')
                            ->orderBy('id', 'desc')
                            ->paginate($this->pageSize, ['id', 'loanername', 'loanername_mobile', 'tag', 'max_loan', 'loan_number', 'all_number', 'score', 'loan_day', 'is_auth', 'header_img', 'attr_ids', 'region_id']);
                    }else{
                        $list = LoanerModel::where($where)
                            ->orderBy('update_time','desc')
                            ->orderBy('id', 'desc')
                            ->paginate($this->pageSize, ['id', 'loanername', 'loanername_mobile', 'tag', 'max_loan', 'loan_number', 'all_number', 'score', 'loan_day', 'is_auth', 'header_img', 'attr_ids', 'region_id']);
                    }
                } else {
                    $list = LoanerModel::where(['is_auth' => 3])
                        ->orderBy('update_time','desc')
                        ->orderBy('id', 'desc')
                        ->paginate($this->pageSize, ['id', 'loanername', 'loanername_mobile', 'tag', 'max_loan', 'loan_number', 'all_number', 'score', 'loan_day', 'is_auth', 'header_img', 'attr_ids', 'region_id']);
                }
                if (count($list)) {
                    $list = $list->toArray();
                    foreach ($list['data'] as &$item)
                    {
                        if ($item['attr_ids']) {
                            $tags = LoanerAttrModel::whereIn('id', explode(',', $item['attr_ids']))
                                ->get()
                                ->pluck('attr_value', 'id');
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
            }else{
                $this->setMessage('城市不存在');
                $this->setstatusCode(4002);
            }
        }else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }



}

