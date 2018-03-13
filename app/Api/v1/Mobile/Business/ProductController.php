<?php

namespace App\Api\v1\Mobile\Business;

use App\Api\Models\LoanerModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductAttrValueModel;
use App\Api\Models\ProductCategoryModel;
use App\Api\Models\ProductModel;
use App\Api\Models\ProductOtherModel;
use App\Api\Models\ShopAgentModel;
use App\Api\Models\ShopModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends BaseController
{
    public $pageSize = 10;

    //获取代理过的系统产品id合集
    public function getAgentedSysProductIds($loaner_id){
        $ids=[];
        $agents=ShopAgentModel::select('sys_pro_id')->where(['loaner_id'=>$loaner_id,'status'=>1])->where('sys_pro_id','!=','')->get();
        if($agents!=null){
            $ids=array_column($agents->toArray(),'sys_pro_id');
        }
        return $ids;
    }

    //获取代理过的第三方产品id合集
    public function getAgentedOtherProductIds($loaner_id){
        $ids=[];
        $agents=ShopAgentModel::select('third_pro_id')->where(['loaner_id'=>$loaner_id,'status'=>1])->where('third_pro_id','!=','')->get();
        if($agents!=null){
            $ids=array_column($agents->toArray(),'third_pro_id');
        }
        return $ids;
    }

    //未代理产品表
    public function index(Request $request)
    {
        $cate_id = $request->input('cate_id', '');
        $create_time = $request->input('create_time', '');
        $keyword = $request->input('keyword', '');
        $loaner_id=$request->input('loaner_id');
        $ids_sys=$this->getAgentedSysProductIds($loaner_id);
        $ids_other=$this->getAgentedOtherProductIds($loaner_id);
        $product_sys = ProductModel::from('product as p')->select('p.id', 'pc.cate_name', 'p.loan_number', 'p.time_limit', 'p.rate', 'p.proxy_peoples as apply_people', 'p.create_time', 'p.source')
            ->leftJoin('product_category as pc', 'pc.id', '=', 'p.cate_id')->where(['p.is_display'=>1])
            ->whereNotIn('p.id',$ids_sys);
        if ($cate_id != '') {
            $product_sys = $product_sys->where(['p.cate_id' => $cate_id]);
        }
        if ($create_time != '') {
            $product_sys = $product_sys->where('p.create_time', '<', $create_time);
        }
        if ($keyword != '') {
            $product_sys = $product_sys->where('pc.cate_name', 'like', "%$keyword%");
        }
        $product_all = ProductOtherModel::from('product_others as po')->select('po.id', 'pc.cate_name', 'po.loan_number', 'po.time_limit', 'po.rate', 'po.apply_peoples as apply_people', 'po.create_time', 'po.source')
            ->leftJoin('product_category as pc', 'pc.id', '=', 'po.cate_id')->where(['po.is_display'=>1])
            ->whereNotIn('po.id',$ids_other);
        if ($cate_id != '') {
            $product_all = $product_all->where(['po.cate_id' => $cate_id]);
        }
        if ($create_time != '') {
            $product_all = $product_all->where('po.create_time', '<', $create_time);
        }
        if ($keyword != '') {
            $product_all = $product_all->where('pc.cate_name', 'like', "%$keyword%");
        }
        $product_all = $product_all->union($product_sys)->orderBy('create_time', 'desc')->limit($this->pageSize)->get();
        $cates = ProductCategoryModel::select('id as cate_id', 'cate_name')->where(['is_display' => 1, 'status' => 1])->orderBy('sort', 'asc')->get();
        $data = ['cate' => $cates, 'product' => $product_all];
        return $this->setstatusCode('200')->setMessage('ok')->setData($data)->result();
    }

    /**
     * @param $id
     * 产品详情
     */
    public function detail(Request $request){
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
     * 可代理列表
     */
    public function myProduct(Request $request)
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
//        $where1 = [];
        $where = [];
        $pageNumber = $request->pageNumber ? ($request->pageNumber-1)*$this->pageSize : 0;
        if($request->type == 'all')
        {//可以代理产品
            if ($request->create_time) {
                $where[] = ['create_time', '<', $request->create_time];
            }
            if ($request->cate) {//贷款类型:
                $where[] = ['cate_id', '=', $request->cate];
//                $where1[] = ['cate_id', '=', $request->cate];
            }
            if ($request->keywords) {//标题
                $where[] = ['title', 'like', '%' . $request->keywords . '%'];
//                $where1[] = ['title', 'like', '%' . $request->keyword . '%'];
            }
            if($sysIds)
            {
                //DB::connection()->enableQueryLog();
                $list = ProductModel::whereNotIn('id',$sysIds)
                    ->where($where)
                    ->orderBy('id','desc')
                    ->orderBy('create_time','desc')
                    ->skip($pageNumber)
                    ->take($this->pageSize)
                    ->get(['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id']);
            }
            else
            {
                $list = ProductModel::where($where)
//                    ->select(['id','source','create_time','apply_peoples','title'])
                    ->orderBy('id','desc')
                    ->orderBy('create_time','desc')
                    ->skip($pageNumber)
                    ->take($this->pageSize)
                    ->get(['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id']);
            }
        }
        elseif($request->type == 'mine')
        {//我代理的产品
            if ($request->create_time) {
                $where[] = ['create_time', '<', $request->create_time];
            }
//            if ($request->cate) {//贷款类型:
//                $where[] = ['cate_id', '=', $request->cate];
//                $where1[] = ['cate_id', '=', $request->cate];
//            }
//            if ($request->title) {//标题
//                $where[] = ['title', 'like', '%' . $request->title . '%'];
//                $where1[] = ['title', 'like', '%' . $request->title . '%'];
//            }
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
                    ->get(['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples','option_values','need_data','time_limit_id']);
            }
            else
            {
                $list = null;
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
                $item->loan_day = $item->loan_day?$item->loan_day.'天':'无';
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
     * @return mixed
     * 产品类型
     */
    public function otherType(){
        $list = ProductAttrValueModel::where(['attr_id'=>5])->get(['attr_value as cate_name','id']);
        $this->setData($list);
        return $this->result();
    }








}
