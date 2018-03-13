<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\ArticleModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductAttrValueModel;
use App\Api\Models\ProductModel;
use App\Api\Models\ProductOtherModel;
use App\Api\Models\RegionModel;
use App\Api\Models\ShopAgentModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class IndexController extends BaseController
{
    public function index(Request $request){
//       $res = Redis::hset('website1', 'google', "www.g.cn");
//       Redis::hset('users', '1',' {name:Jack,age:28,location:shanghai}');
//        Redis::hset('users', '2',' {name:Jack,age:29,location:chengdu}');
        //返回整个hash表元素
        $res = Redis::hgetall('products');
//        $res = Redis::hgetall('users');
        //condition
        $condition = '信用贷';
        $keys = [];
        foreach($res as $key =>$item){
           if(strpos($item,$condition))
           {
               $keys[] =  $key;
           }
        }
        dump($keys);
        //查询相应信贷经理
//        Product::whereIn('id',$keys)->with(['user'=>function($query){
//            $query->select(['']);
//        }])->get();
    }

    /**
     * 找顾问
     */
    public function manager(){
        $list = LoanerModel::where([['is_auth','=',3],['proxy_number','>',0]])
            ->with(['auth'=>function($query){
                $query->select('user_id','photo');
            }])
        ->select(['id','loanername as name','max_loan','tag','all_number','loan_day','score','attr_ids','user_id'])
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
     * 咨询
     */
    public function article(){
        $list = ArticleModel::where(['is_display'=>1])
            ->select('title','picture','id','create_time','views')
            ->orderBy('recommend','desc')
            ->limit(6)
            ->get();
        if(!count($list)){
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }else{
            $this->setData($list);
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
            ///产品库信息
            $where = [];
            $orWhere = [];
            if ($request->loan_day)
            {//搜索放款时间
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
                    $numberAttr = explode('-',$number);
                    $where[] = ['loan_number_start', '>=', $numberAttr[0]];
                    $orWhere[] = ['loan_number_start', '<', $numberAttr[1]];
                }
            }
            if ($request->rate) {//搜索贷款利率
                $rate = ProductAttrValueModel::find($request->rate, ['attr_value']);
                if ($request->rate == 65)//以上
                {
                    $where[] = ['rate_start', '>', $rate->attr_value];
                    $orWhere[] = ['rate_start', '>', $rate->attr_value];
                } else {
                    $rateArr = explode(',', $rate->attr_value);
                    $where[] = ['rate_start', '>=', $rateArr[0]];
                    $orWhere[] = ['rate_start', '<=', $rateArr[1]];
                }
            }

            /*查询符合条件的产品*/
            if ($where && !$orWhere) {
                $list = ProductModel::where($where)
                    ->get(['id'])
                    ->pluck('id');
            } elseif ($where && $orWhere) {
                $list = ProductModel::where($where)
                    ->orWhere($orWhere)
                    ->get(['id'])
                    ->pluck('id');
            } else {
                $list = [];
            }
//            dd($list);
            //查询符合条件的信贷经理
            if ($list) {
                $loanerList = ShopAgentModel::whereIn('sys_pro_id', $list->toArray())
                    ->get()
                    ->pluck('loaner_id');
                if (count($loanerList)) {
                    $loanerList = $loanerList->toArray();
                    $loanerList = array_unique($loanerList);//信贷经理集合
                    ///信贷经理条件
                    $city = RegionModel::where(['name' => str_replace('市', '', $request->city)])->first(['id']);
                    if ($city) {
                        $area = $request->region ? $request->region : '';
                        $region = '';
                        if ($area) {
                            $region = $area;
                        }
                        if ($request->by && in_array($request->by, ['asc', 'desc'])) {
                            $by = $request->by;
                        } else {
                            $by = 'desc';
                        }
                        if ($request->order && in_array($request->order, ['score', 'loan_number', 'max_loan'])) {
                            $order = $request->order;
                        } else {
                            $order = 'id';
                        }

//                        DB::connection()->enableQueryLog();
                        if ($request->region) {//
                            $result = LoanerModel::where([ 'city_id' => $city->id, 'region_id' => $request->region,'is_auth'=>3])
                                ->whereIn('id',$loanerList)
                                ->orderBy($order, $by)
                                ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'attr_ids', 'loaner_lng', 'loaner_lat', 'is_auth'])
                                ->paginate($this->pageSize);
                        } else {
                            $result = LoanerModel::where([ 'city_id' => $city->id,'is_auth'=>3])
                                ->whereIn('id',$loanerList)
                                ->orderBy($order, $by)
                                ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'attr_ids', 'loaner_lng', 'loaner_lat', 'is_auth'])
                                ->paginate($this->pageSize);
                        }
//                        $log = DB::getQueryLog();
//                        dd($log);
                        if (count($result))
                        {
                            $result = $result->toArray();
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
                            //区域分组：
//                            if (!$region) {
                                $areas = RegionModel::where(['pid' => $city->id])
                                    ->get(['id', 'name','lat','lng']);
//                        dump($areas->toArray());
                                foreach ($areas as &$area)
                                {
                                    $area->counts = LoanerModel::where(['city_id' => $city->id, 'region_id' => $area->id])->whereIn('id',$loanerList)
                                        ->where(['is_auth'=>3])
                                        ->count();
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
//                            }
//                        else {
//                                $areas = null;
//                            }
                            $this->setData(['list' => $result, 'map' => $areas]);
                        } else {
                            $this->setstatusCode(5000);
                            $this->setMessage('暂无数据');
                        }
                    } else {
                        $this->setstatusCode(5000);
                        $this->setMessage('暂无该城市');

                    }
                }else{
                    $this->setstatusCode(5000);
                    $this->setMessage('暂无符合条件的信贷经理');
                }
            }else{
                $this->setstatusCode(5000);
                $this->setMessage('暂无符合条件的产品');
            }
        }else{
            $this->setstatusCode(4002);
            $this->setMessage('地区必须');
        }
        return $this->result();

    }

    public function test()
    {
       $all = LoanerModel::all();
        $arr = [];
        foreach ($all as $item) {
            $count = ShopAgentModel::where(['loaner_id'=>$item->id])
                ->count();
            if($count)
            {
                LoanerModel::where(['id'=>$item->id])
                    ->update(['proxy_number'=>$count]);
            }
            $arr[] = ['id'=>$item->id,'number'=>$count];
        }
        return $arr;
    }

    /**
     * 快速搜索配置
     */
    public function quickConfig(){
        $loan_day = ProductAttrValueModel::where(['attr_id'=>2])->get(['attr_value','id']);
        $loan_number = ProductAttrValueModel::where(['attr_id'=>9])->get(['attr_value','id']);
        $rate = ProductAttrValueModel::where(['attr_id'=>13])->get(['attr_value','id']);
        $number = LoanModel::count();
        $all['loan_day'] = $loan_day;
        $all['loan_number'] = $loan_number;
        $all['rate'] = $rate;
        $all['number'] = $number;
        $this->setData($all);
        return $this->result();
    }

    /**
     * 搜索查询
     */
    public function quickSearch1(Request $request){
//        $where = [['proxy_number','>',0]];
//        $orWhere = [];
//        if($request->loan_day)
//        {//搜索放款时间
//            //id
//            $loan_day_attr = ProductAttrValueModel::find($request->loan_day,['attr_value']);
//            if($request->loan_day != 61){
//                $day = str_replace('个工作日','',$loan_day_attr->attr_value);
//                $dayArr = explode('-',$day);
//                $where[] = ['loan_day','>=',$dayArr[0]];
//                $orWhere[] = ['loan_day','<=',$dayArr[1]];
//            }else{
//                $day = str_replace('个工作日以上','',$loan_day_attr->attr_value);
//                $where[] = ['loan_day','>',$day];
//            }
//        }
//        if($request->loan_number)
//        {//搜索放款金额
//            $loan_number_attr = ProductAttrValueModel::find($request->loan_number,['attr_value']);
//            $number = substr($loan_number_attr->attr_value,0,strpos($loan_number_attr->attr_value,'万'));
//            if($request->loan_number!= 31)//以下
//            {
//                $where[] = ['loan_number_start','<=',$number];
//                $orWhere[] = ['loan_number_end','<=',$number];
//            }elseif($request->loan_number!= 38)//以上
//            {
//                $where[] = ['loan_number_end','>',$number];
//            }else{
//                $numberAttr = explode($number,'-');
//                $where[] = ['loan_number_start','>=',$numberAttr[0]];
//                $orWhere[] = ['loan_number_end','<=',$numberAttr[1]];
//            }
//        }
//        if($request->rate)
//        {//搜索贷款利率
//            $rate = ProductAttrValueModel::find($request->rate,['attr_value']);
//            if($request->rate == 65)//以上
//            {
//                $where[] = ['rate_start','>',$rate->attr_value];
//                $orWhere[] = ['rate_end','>',$rate->attr_value];
//            }else{
//                $rateArr = explode(',',$rate->attr_value);
//                $where[] = ['rate_start','>=',$rateArr[0]];
//                $orWhere[] = ['rate_start','<=',$rateArr[1]];
//            }
//        }
//        /*查询符合条件的产品*/
//        if($where && !$orWhere){
//            $list = ProductModel::where($where)
//                ->get(['id'])
//                ->pluck('id');
//        }elseif($where && $orWhere){
//            $list = ProductModel::where($where)
//                ->orWhere($orWhere)
//                ->get(['id'])
//                ->pluck('id');
//        }else{
//            $list = [];
//        }
//        //查询符合条件的信贷经理
//        if($list) {
//            $loanerList = ShopAgentModel::whereIn('sys_pro_id', $list->toArray())
//                ->get()
//                ->pluck('loaner_id');
//            if(count($loanerList))
//            {
//                $loanerList = $loanerList->toArray();
//                $loanerList = array_unique($loanerList);
//                $result = LoanerModel::where(['is_display' => 1,'city_id'=>$city->id,'region_id'=>$region])
//                    ->whereIn('id',$loanerList)
//                    ->where('proxy_number','>',0)
////                    ->orderBy($order, $by)
//                    ->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'header_img', 'region_id', 'max_loan', 'tag', 'loan_number', 'score', 'loan_day', 'attr_ids', 'loaner_lng', 'loaner_lat','is_auth'])
//                    ->paginate(2);
//                dd($result->toArray());
//            }else{
//                $this->setMessage('暂无数据');
//                $this->setData(5000);
//            }
//        }
//        else {
//            $this->setMessage('暂无数据');
//            $this->setData(5000);
//        }
//        return $this->result();

    }
}
