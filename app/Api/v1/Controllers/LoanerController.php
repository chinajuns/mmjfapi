<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\AnswerModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanEvaluateModel;
use App\Api\Models\LoanModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductAttrValueModel;
use App\Api\Models\ProductCategoryModel;
use App\Api\Models\ProductModel;
use App\Api\Models\ProductOtherModel;
use App\Api\Models\QuestionModel;
use App\Api\Models\ShopAgentModel;
use App\Api\Models\ShopModel;
use App\Api\Models\UserFavoriteModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class LoanerController extends BaseController
{
     /**
     * 顾问搜索匹配
     */
    public function config(){
        $list = LoanerAttrModel::whereIn('id',[1,4])
            ->with(['values'=>function($query){
                $query->select(['id','attr_value as name','pid']);
            }])
            ->get(['attr_value as type','id']);
        if(count($list))
        {
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
     * 搜索顾问
     */
    public function search(Request $request,$region=null){
        $region = $region ? $region :'';
        $area_id = $request->area ? $request->area : '';
        $type = $request->type ? $request->type : '';
        $focus = $request->focus ? $request->focus : '';
        if($request->by && in_array($request->by,['asc','desc']))
        {
            $by = $request->by;
        }else{
            $by = 'desc';
        }
        if($request->order && in_array($request->order,['score','loan_number','max_loan'])){
            $order = $request->order;
        }else{
            $order = 'id';
        }
        if($area_id){//地区覆盖
            $region = $area_id;
        }

        $mobile = $request->mobile?$request->mobile:'';
        if(!$region)
        {//地区
            if($mobile){
                $list = LoanerModel::where([['is_auth','=',3],['loanername_mobile','like','%'.$mobile.'%'],['proxy_number','>',0]])
                    ->orderBy($order, $by)
                    ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag','all_number','loan_number', 'score', 'loan_day', 'attr_ids','city_id'])
                    ->paginate($this->pageSize);
            }else {
                $list = LoanerModel::where([['is_auth','=',3],['proxy_number','>',0]])
                    ->orderBy($order, $by)
                    ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag','all_number','loan_number', 'score', 'loan_day', 'attr_ids','city_id'])
                    ->paginate($this->pageSize);
            }
        }else{
            if($request->type) {
                $type = LoanerAttrModel::find($request->type, ['attr_value'])->attr_value;
            }
            if($mobile){
                $where = [['is_auth','=',3],['city_id','=',$region],['loanername_mobile','like','%'.$mobile.'%'],['proxy_number','>',0]];
            }else {
                $where = [['is_auth','=',3],['city_id','=',$region],['proxy_number','>',0]];
            }
            if($type && $focus){
                $list = LoanerModel::where($where)
                    ->whereRaw("FIND_IN_SET($request->focus,attr_ids)")
                    ->where('tag','like','%'.$type.'%')
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'all_number','loan_number', 'score', 'loan_day','attr_ids','city_id'])
                    ->paginate($this->pageSize);
            }elseif($type && !$focus){
                $list = LoanerModel::where($where)
                    ->where('tag','like','%'.$type.'%')
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'all_number','loan_number', 'score', 'loan_day','attr_ids','city_id'])
                    ->paginate($this->pageSize);

            }elseif(!$type && $focus) {
//                DB::connection()->enableQueryLog();
                $list = LoanerModel::where($where)
                    ->whereRaw("FIND_IN_SET($request->focus,attr_ids)")
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'all_number','loan_number', 'score', 'loan_day','attr_ids'])
                    ->paginate($this->pageSize);
//                $log = DB::getQueryLog();
//                dd($log);
            }else{
                $list = LoanerModel::where($where)
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'all_number','loan_number', 'score', 'loan_day','attr_ids'])
                    ->paginate($this->pageSize);
            }
        }
        if(count($list))
        {
            $list = $list->toArray();
            foreach($list['data'] as &$item)
            {
                if($item['attr_ids'])
                {
                    $tags = LoanerAttrModel::whereIn('id',explode(',',$item['attr_ids']))
                        ->get()
                        ->pluck('attr_value');
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

    public function search1(Request $request,$region=null){
        $region = $region ? $region :'';
        $area_id = $request->area ? $request->area : '';
        $type = $request->type ? $request->type : '';
        $focus = $request->focus ? $request->focus : '';
        if($request->by && in_array($request->by,['asc','desc']))
        {
            $by = $request->by;
        }else{
            $by = 'desc';
        }
        if($request->order && in_array($request->order,['score','loan_number','max_loan'])){
            $order = $request->order;
        }else{
            $order = 'id';
        }
        if($area_id){//地区覆盖
            $region = $area_id;
        }

        $mobile = $request->mobile?$request->mobile:'';
        if(!$region)
        {//地区
            if($mobile){
                $list = LoanerModel::where([['is_display','=', 1],['is_auth','=',3],['loanername_mobile','like','%'.$mobile.'%'],['proxy_number','>',0]])
                    ->orderBy($order, $by)
                    ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'attr_ids','city_id'])
                    ->paginate($this->pageSize);
            }else {
                $list = LoanerModel::where([['is_display' ,'=', 1],['is_auth','=',3],['proxy_number','>',0]])
                    ->orderBy($order, $by)
                    ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'attr_ids','city_id'])
                    ->paginate($this->pageSize);
            }
        }else{
            if($request->type) {
                $type = LoanerAttrModel::find($request->type, ['attr_value'])->attr_value;
            }
            if($mobile){
                $where = [['is_display','=', 1],['is_auth','=',3],['city_id','=',$region],['loanername_mobile','like','%'.$mobile.'%'],['proxy_number','>',0]];
            }else {
                $where = [['is_display','=', 1],['is_auth','=',3],['city_id','=',$region],['proxy_number','>',0]];
            }
            if($type && $focus){
                $list = LoanerModel::where($where)
                    ->whereRaw("FIND_IN_SET($request->focus,attr_ids)")
                    ->where('tag','like','%'.$type.'%')
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'loan_number', 'score', 'loan_day','attr_ids','city_id'])
                    ->paginate($this->pageSize);
            }elseif($type && !$focus){
                $list = LoanerModel::where($where)
                    ->where('tag','like','%'.$type.'%')
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'loan_number', 'score', 'loan_day','attr_ids','city_id'])
                    ->paginate($this->pageSize);

            }elseif(!$type && $focus) {
//                DB::connection()->enableQueryLog();
                $list = LoanerModel::where($where)
                    ->whereRaw("FIND_IN_SET($request->focus,attr_ids)")
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'loan_number', 'score', 'loan_day','attr_ids'])
                    ->paginate($this->pageSize);
//                $log = DB::getQueryLog();
//                dd($log);
            }else{
                $list = LoanerModel::where($where)
                    ->orderBy($order, $by)
                    ->select(['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id',  'max_loan', 'tag', 'loan_number', 'score', 'loan_day','attr_ids'])
                    ->paginate($this->pageSize);
            }
        }
        if(count($list))
        {
            $list = $list->toArray();
            foreach($list['data'] as &$item)
            {
                if($item['attr_ids'])
                {
                    $tags = LoanerAttrModel::whereIn('id',explode(',',$item['attr_ids']))
                        ->get()
                        ->pluck('attr_value');
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
     * @param Request $request
     * 产品经理详情
     */
    public function show(Request $request, $id){
        $uid = $this->checkUid($request->header('deviceid'));
        //判断是否通过认证
        $is_auth = LoanerModel::where(['id'=>$id,'is_display'=>1])
            ->with(['auth'=>function($query){
                $query->select(['is_check','is_pass','user_id']);
            }])
            ->first(['user_id','id']);
//        if(count($is_auth) && $is_auth->auth['is_check'] == 1 && $is_auth->auth['is_pass'] == 1)
        if(count($is_auth))
        {
            //基本信息
            $info = LoanerModel::find($id,['id','loanername as name', 'loanername_mobile as mobile', 'header_img', 'max_loan', 'tag', 'loan_number', 'all_number', 'score', 'loan_day','attr_ids']);

            //认证信息
            $auth = LoanerModel::where(['id'=>$id,'is_auth'=>3])
                ->with(['shop'=>function($query){
                    $query->select(['loaner_id','mechanism_name','introduce','special','service_object'])->where(['status'=>1]);
                }])
                ->first(['user_id','id']);
            if(count($info)){
                if($info->attr_ids)
                {
                    $tags = LoanerAttrModel::whereIn('id',explode(',',$info->attr_ids))
                        ->get()
                        ->pluck('attr_value');
                    $tags = implode(',',$tags->toArray());
                }else{
                    $tags = null;
                }
                $info->tags = $tags;

                //
                if($uid) {
                    $info->is_favorite = UserFavoriteModel::where(['object_id' => $info->id, 'type' => 1, 'user_id' => $uid])->count() ? '1' : '0';
                }else{
                    $info->is_favorite = '0';
                }
                $arr['info'] = $info;

            }else{
                $arr['info'] = null;
            }
            if($auth->shop)
            {
                $auth->mechanism_name = $auth->shop['mechanism_name'];
                $auth->introduce = $auth->shop['introduce'];
                $auth->special = $auth->shop['special'];
                $auth->service_object = $auth->shop['service_object'];
                unset($auth->shop);
                unset($auth->id);
                unset($auth->user_id);
                $arr['auth'] = $auth;
            }else{
                $arr['auth']=null;
            }
            $arr['info'] = $info;

            $this->setData($arr);
        }else{//未通过审核
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }
    /**
     * @param $id
     * 详情列表页面
     */
    public function productList($id)
    {
        $shop = ShopModel::where(['loaner_id'=>$id,'status'=>1])
            ->first();
        if(count($shop)){
            $list = ShopAgentModel::where(['shop_id'=>$shop->id,'is_normal'=>1,'status'=>1])
                ->with(['product'=>function($query){
                    $query->select('id','title','description','rate','time_limit','loan_number','loan_day','apply_peoples','cate_id');
                },'productOther'=>function($query){
                    $query->select(['id','title','description','rate','time_limit','loan_day','loan_number','apply_peoples','cate_id']);
                }])
                ->orderBy('id','desc')
                ->paginate($this->pageSize,['id','shop_id','sys_pro_id','third_pro_id','create_time']);
            if(count($list))
            {
                $list = $list->toArray();
                foreach($list['data'] as &$item)
                {
                    if($item['product']['cate_id'])
                    {
                        $category = ProductCategoryModel::find($item['product']['cate_id']);
                        if(count($category)) {
                            $item['category'] = $category->cate_name;
                            }else{
                            $item['category'] =  '';
                            }
                    }else{
                        $category = ProductCategoryModel::find($item['product_other']['cate_id']);
                        if(count($category)) {
                            $item['category'] = $category->cate_name;
                        }else{
                            $item['category'] =  '';
                        }
                    }
                    if($item['product'] == null)//系统产品
                    {
                        $item['product'] = $item['product_other'];
                        $item['time_limit'] = $item['product']['time_limit'];
                        $item['loan_day'] = $item['product']['loan_day'];
                        $item['loan_number'] = $item['product']['loan_number'];
                        $item['rate'] = $item['product']['rate'];
                    }
                    else{
                        $item['time_limit'] = str_replace(',','-',$item['product']['time_limit']).'个月';
                        $item['loan_day'] = str_replace(',','-',$item['product']['loan_day']).'天';
                        $item['loan_number'] = str_replace(',','-',$item['product']['loan_number']).'万';
                        $item['rate'] = str_replace(',','-',$item['product']['rate']).'%';
                    }
                    unset($item['product_other']);
                    $item['title'] = $item['product']['title'];
                    $item['description'] = $item['product']['description'];
                    $item['apply_peoples'] = $item['product']['apply_peoples'];
                    unset($item['product']);
                    unset($item['sys_pro_id']);
                    unset($item['third_pro_id']);
                }
            }
            $this->setData($list);
        }else{
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }

    /**
     * @return mixed
     * 拉取第三方产品
     */
    public function getProduct(){
//        $url = 'http://192.168.20.134:8080/procenter/GetproDetails.do';
        $url = 'http://192.168.20.129:8080/procenter/GetproDetails.do';
        $res = $this->https_request($url);
        if(count($res)){
            $res = json_decode($res,true);
            $id = [];
            foreach($res['pros'] as $item)
            {
                $exist = ProductOtherModel::where(['title'=>$item['dkpro']])
                    ->first();
                if(!count($exist)) {
                    if($item['dkproxz'])
                    {
                        $proper = ProductCategoryModel::where(['cate_name'=>$item['dkproxz']])
                            ->first();
                        if(count($proper))
                        {
                            $cate_id = $proper->id;
                        }else{
                            $res = ProductCategoryModel::create(['cate_name'=>$item['dkproxz'],'create_time'=>time()]);
                            if($res)
                            {
                                $cate_id = $res->id;
                            }
                        }
                    }else{
                        $cate_id = 0;
                    }
                    $data['channel'] = $item['belong'];//来源、
                    $data['service_city'] = $item['ctiy'];
                    $data['title'] = $item['dkpro'];
                    $data['loan_number'] = $item['dked'];
                    $data['loan_day'] = $item['zkfksj'];
                    $data['rate'] = $item['dkylv'];
                    $data['time_limit'] = $item['dkqx'];
                    $data['property'] = $item['dkproxz'];
                    $data['age'] = $item['nlyq'];
                    $data['income'] = $item['sryq'];
                    $data['work_year'] = $item['gznx'];
                    $data['social_security'] = $item['sbyq'];
                    $data['credit'] = str_replace(' ','',strip_tags($item['xyyq']));
                    $data['credit_overdue'] = $item['zxyqyq'];
                    $data['need_options'] = $item['sxcl'];
                    $data['need_security'] = $item['sfygmbx'];
                    $data['need_trade'] = $item['hyxz'];
                    $data['need_identity'] = $item['sfzm'];
                    $data['url'] = $item['purl'];
                    $data['create_time'] = time();
                    $data['cate_id'] = $cate_id;
                    $insert = ProductOtherModel::insertGetId($data);
                    Redis::hset('products','third:'.$insert,json_encode($data,JSON_UNESCAPED_UNICODE));
                    $id[] = $insert;
                }
            }
            $this->setData($id);
            return $this->result();
        }/*
        $all = ProductOtherModel::all(['id','property']);
        foreach ($all as $item) {
            $proper = ProductCategoryModel::where(['cate_name'=>$item->property])
                ->first();
            if(count($proper))
            {
                ProductOtherModel::where(['id'=>$item->id])
                    ->update(['cate_id'=>$proper->id]);
            }else{
                if($item->property) {
                    $res = ProductCategoryModel::create(['cate_name' => $item->property, 'create_time' => time()]);
                    if ($res) {
                        ProductOtherModel::where(['id' => $item->id])
                            ->update(['cate_id' => $res->id]);
                    }
                }
            }
        }*/
    }

    /**
     * @param $shopid 店铺id
     * @param $id 代理产品表id
     * 单个产品详情
     */
    public function single($shopid,$id){
        $show = ShopAgentModel::find($id,['id','shop_id','sys_pro_id','third_pro_id','create_time','loaner_id']);
        if(count($show))
            {
                $item = ProductModel::find($show->sys_pro_id,['id','title','time_limit','rate','loan_number','loan_day','cate_id','create_time','service_options','apply_peoples as apply_people','option_values','need_data','time_limit_id','loan_number_start','loan_number_end']);
                if(count($item)) {
                    $res = $this->getProductOptions($item->option_values, $item->need_data);
                    $item->loaner_id = $show->loaner_id;
                    $item->options = $res['apply_condition'];
                    $item->need_data = $res['need_data'];
                    $time_limit = ProductAttrValueModel::find($item->time_limit_id);
                    if ($time_limit) {
                        $item->time_limit = $time_limit->attr_value;
                    }
//                    //判断代理
//                    $is_proxy = ShopAgentModel::where(['loaner_id' => $request->loaner_id,'sys_pro_id'=>$item->id, 'status' => 1])->first();
//                    $item->is_proxy = $is_proxy ? 1 : 0;
//                    $item->proxy_time = $is_proxy ? $is_proxy->create_time : 0;
                    //代理人数
                    $item->loan_number = str_replace(',', '-', $item->loan_number) . '万元';
                    $item->rate = str_replace(',', '-', $item->rate) . '%';
                    $type = ProductAttrValueModel::find($item->cate_id);
                    $item->loan_type = $type ? $type->attr_value : '';
                    $item->loan_day = $item->loan_day . '天';
                    $item->platform = 'system';
                    //判断代理人数
//                    $proxy_number = ShopAgentModel::where(['sys_pro_id' => $request->id, 'status' => 1])
//                        ->count();
//                    $item->proxy_number = $proxy_number ? $proxy_number : 0;
                    unset($item->service_options);
                    unset($item->option_values);
                    $this->setData($item);
                }else{
                    $this->setstatusCode(5000);
                    $this->setMessage('暂无数据');
                }
            }else{
                $this->setstatusCode(5000);
                $this->setMessage('暂无数据');
            }
        return $this->result();
    }

    /**
     * @param $id 信贷经理id
     *
     */
    public function question($id){
        $list = QuestionModel::where(['user_id'=>$id,'is_pass'=>2,'status'=>1])
            ->select(['title','create_time','id'])
            ->paginate($this->pageSize);
        if(count($list))
        {
            $list = $list->toArray();
            foreach($list['data'] as &$item){
                $item['comments'] = AnswerModel::where(['question_id'=>$item['id']])->count();
            }
            $this->setData($list);
        }else{
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }

    /**
     * 问答分类
     */
    public function attribute(){
        $attr = LoanerAttrModel::where(['pid'=>13])
            ->get(['id','attr_value as tag']);
        $this->setData($attr);
        return $this->result();
    }

    /**
     * @param $id //信贷经理id
     * 用户评价
     *
     */
    public function average($id){
        $list = LoanModel::where(['c_comment'=>2,'loaner_id'=>$id])
            ->get()
            ->pluck(['id']);
        if(count($list))
        {
            //TODO::优化
            /* $array=array(4,5,1,2,3,1,2,1);
             * $ac=array_count_value($array);
             * */
            $arr = [];
            //综合评分
            $avg = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->avg('score_avg');
            //好评数量
            $excellent = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->where([['score_avg','<',5.0],['score_avg','>=',4.0]])
                ->count();
            //中评数量
            $better = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->where([['score_avg','<',4.0],['score_avg','>=',3.0]])
                ->count();
            //差评数量
            $good = LoanEvaluateModel::whereIn('loan_id',$list->toArray())
                ->where('score_avg','<',3)
                ->count();
            $arr['excellent'] = $excellent ? $excellent : 0;
            $arr['counts'] = count($list);
            $avg = (string)number_format($avg,1);
            if(strpos($avg,'.') !== false)
            {
                $avg = substr($avg,strpos($avg,'.')+1) >5 ? ceil($avg) : (substr($avg,strpos($avg,'.')+1) <5 ? floor($avg):$avg);
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
            $this->setData($arr);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param $id
     * 评价列表
     */
    public function evaluate(Request $request){
        $where = '';
        if($request->type)
        {
            if($request->type == '4')
            {
                $where = [['score_avg', '<', 4.5], ['score_avg', '>=', 4.0]];
            }elseif($request->type == '5')
            {
                $where = ['score_avg'=> 4.5];
            }elseif($request->type == '3')
            {
                $where = [['score_avg','<', 4.0]];
            }
        }
        if($request->id) {
            $ids = LoanModel::where(['is_comment'=>2,'loaner_id'=>$request->id])
                ->get()
                ->pluck(['id']);
            if(count($ids))
            {
                if ($where) {
                    $list = LoanEvaluateModel::whereIn('loan_id', $ids->toArray())
                        ->where($where)
                        ->with(['user'=>function ($query) {
                            $query->select(['id', 'username']);
                        }
                        ])
                        ->orderBy('id', 'desc')
                        ->paginate($this->pageSize,['loan_id','user_id','describe','create_time','score_avg','focus']);
                } else {
                    $list = LoanEvaluateModel::whereIn('loan_id', $ids->toArray())
                        ->with(['user'=>function ($query) {
                            $query->select(['id', 'username']);
                        }])
                        ->orderBy('id', 'desc')
                        ->paginate($this->pageSize,['loan_id','user_id','describe','create_time','score_avg','focus']);
                }
                if (count($list)) {
                    $list = $list->toArray();
                    foreach ($list['data'] as &$item)
                    {
                        $item['username'] = $item['user']['username'];
                        $score = (string)($item['score_avg']);
                        if(strpos($score,'.') !== false)
                        {
                            if(substr($score,strpos($score,'.')+1) > 5)
                            {
                                $score = ceil($score);
                            }elseif(substr($score,strpos($score,'.')+1) < 5)
                            {
                                $score = floor($score);
                            }
                        }
                        $item['score_avg'] = $score;
                        unset($item['user']);

                    }
                    $this->setData($list);
                } else {
                    $this->setMessage('暂无数据');
                    $this->setstatusCode(5000);
                }
            }else{
                $this->setMessage('暂无评价');
                $this->setstatusCode(5000);
            }
        }else{
            $this->setMessage('参数不全');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

}
