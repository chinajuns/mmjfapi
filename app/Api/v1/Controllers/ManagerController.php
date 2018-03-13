<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\ArticleModel;
use App\Api\Models\IntegralListModel;
use App\Api\Models\IntegralModel;
use App\Api\Models\JunkModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanEvaluateModel;
use App\Api\Models\LoanExamineModel;
use App\Api\Models\LoanModel;
use App\Api\Models\RegionModel;
use App\Api\Models\UserAuthModel;
use App\Api\Models\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class ManagerController extends BaseController
{
    use AuthenticatesUsers;
    /**
     * @param $deviceid
     * 用户基本信息
     */
    public function information(Request $request){
        $info = UserModel::where(['is_disable'=>1])
            ->with(['shop'=>function($query){
                $query->select('id','user_id','check_result');
            }])
            ->select(['username','mobile','header_img','integral','id'])
            ->find($request->uid);
        if(count($info)){
            if(count($info->shop))
            {
                $info->check_result = $info->shop['check_result'];//店铺认证情况
            }else{
                $info->check_result = 0;//无店铺
            }
            unset($info->shop);
            //当日积分
            $day = IntegralListModel::where([['user_id','=',$request->uid],['number','>',0],['create_time','>',strtotime(date('Y-m-d',time()))]])
                ->sum('number');
            $month = IntegralListModel::where([['user_id','=',$request->uid],['number','>',0],['create_time','>',strtotime(date('Y-m',time()))]])
                ->sum('number');
            //当月积分
            $info->mobile = substr($info->mobile,0,3).'****'.substr($info->mobile,7);
            $info->day_integral = $day ? $day : 0;
            $info->month_integral = $month ? $month : 0;
            $this->setData($info);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }
    /**
     * 积分
     */
    public function point(Request $request){
        $uid = $this->checkUid($request->header('deviceid'));
        $list = IntegralListModel::where(['user_id'=>$uid])
            ->with(['type'=>function($query){
              $query->select(['id','integral_log']);
            }])
            ->orderBy('id','desc')
            ->paginate($this->pageSize,['id','user_id','integral_id','number','create_time','description']);

        if(count($list))
        {
            $list = $list->toArray();
            foreach($list['data'] as &$item)
            {
                $item['type'] = $item['type']?$item['type']['integral_log']:$item['description'];
                unset($item['integral_id']);
            }
            $this->setData($list);
        }else{
            $this->setstatusCode(5000);
            $this->setMessage('没有数据');
        }
        return $this->result();
    }

    /**
     * @return mixed
     *
     */
    public function pointRule(){
        $rules = IntegralModel::where(['status'=>1])
            ->get(['integral_log as type','integral_description as description','integral_number as number']);
        $this->setData($rules);
        return $this->result($rules);
    }

    /**
     * @param Request $request
     * 信贷经理认证
     */
    public function authentication(Request $request){
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
            'photo'=>'required',//免冠照片
        ]);
        if(!$validator->fails()) {
            $exist = UserAuthModel::where(['user_id'=>$uid])
                ->first(['is_pass','id']);
            $info = UserModel::find($uid, ['mobile', 'header_img']);
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
                                ->update(['true_name' => $request->true_name, 'user_id' => $uid, 'identity_number' => $request->cert_number, 'front_identity' => $request->front_cert, 'back_identity' => $request->back_cert, 'work_card' => $request->work_card,  'mechanism_type' => $request->mechanism_type, 'mechanism' => $request->mechanism, 'department' => $request->department, 'card' => $request->card, 'contract_page' => $request->contract_page, 'logo_personal' => $request->logo_personal, 'update_time' => time(),'is_pass'=>2,'province_id'=>$request->province_id,'city_id'=>$request->city_id,'region_id'=>$request->region_id,'photo'=>$request->photo,'type'=>2]);
                         LoanerModel::where(['id'=>$loaner->id])
                        ->update(['loanername' => $request->true_name, 'loanername_mobile' => $info->mobile, 'loaner_lng' => $request->lng, 'loaner_lat' => $request->lat, 'header_img' => $info->header_img, 'create_time' => time(), 'province_id'=>$request->province_id,'city_id'=>$request->city_id,'region_id'=>$request->region_id, 'user_id' => $uid, 'is_auth' => 2]);
                        UserModel::where(['id' => $uid])
                            ->update(['is_auth' => 2, 'update_time' => time()]);
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
                UserAuthModel::create(['true_name' => $request->true_name, 'user_id' => $uid, 'identity_number' => $request->cert_number, 'front_identity' => $request->front_cert, 'back_identity' => $request->back_cert, 'work_card' => $request->work_card, 'province_id'=>$request->province_id,'city_id'=>$request->city_id,'region_id'=>$request->region_id, 'mechanism_type' => $request->mechanism_type, 'mechanism' => $request->mechanism, 'department' => $request->department, 'card' => $request->card, 'contract_page' => $request->contract_page, 'logo_personal' => $request->logo_personal, 'create_time' => time(),'is_pass'=>2,'photo'=>$request->photo,'type'=>2]);

                LoanerModel::create(['loanername' => $request->true_name, 'loanername_mobile' => $info->mobile, 'loaner_lng' => $request->lng, 'loaner_lat' => $request->lat, 'header_img' => $info->header_img, 'create_time' => time(), 'city_id'=>$request->city_id,'province_id'=>$request->province_id,'region_id'=>$request->region_id,  'user_id' => $uid, 'is_auth' => 2]);
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
     * 甩单:列表
     */
    public function listProduct(Request $request)
    {
        $uid = $this->checkUid($request->header('deviceid'));
        $loaner = LoanerModel::where(['user_id'=>$uid])->first(['id','loanername']);
        if(count($loaner))
        {
            $list = JunkModel::where(['loaner_id'=>$loaner->id,'status'=>1])
                ->with(['source'=>function($query){
                    $query->select(['id','name','age','user_id','region_id','loan_type','apply_number','is_check','apply_information','description','create_time','time_limit']);
                },'LoanType'=>function($query){
                    $query->select(['id','attr_value']);
                },'region'=>function($query){
                    $query->select(['id','name']);
                },'limit'=>function($query){
                    $query->select(['id','attr_value']);
                }])
                ->select(['id','name','age','region_id','loan_type','apply_number','is_check','apply_information','loan_type','price','description','source_id','source_table','create_time','time_limit'])
                ->orderBy('id','desc')
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
                    $item['is_auth'] = 1;
                    $info = LoanerAttrModel::whereIn('id',explode(',',$item['apply_information']))
                        ->with('parent')
                        ->get();
                    if(count($info))
                    {
                        foreach($info as $value)
                        {
                            $arr[] = ['attr_value' => $value->attr_value,'attr_key'=>$value->parent['attr_value']];
                        }
                        $item['info'] = $arr;
                    }
                    unset($item['loan_type']);
                    unset($item['region']);
                    unset($item['limit']);
                    if($item['source'])
                    {
                        $item = $item['source'];
                        $item['source_table'] = 'loan';
                        $user = UserModel::find($item['user_id'],['id','username','is_auth']);
                        $item['name'] = $user->username;
                        $item['is_auth'] = $user->is_auth;
                        $item['city'] = RegionModel::find($item['region_id'],['id','name'])->name;
                        $item['type'] = LoanerAttrModel::find($item['loan_type'],['id','attr_value'])->attr_value;
                        $info = LoanerAttrModel::whereIn('id',explode(',',$item['apply_information']))
                            ->with('parent')
                            ->get();
                        if(count($info))
                        {
                            foreach($info as $value)
                            {
                                $arr[] = ['attr_value' => $value->attr_value,'attr_key'=>$value->parent['attr_value']];
                            }
                            $item['info'] = $arr;
                        }
                    }
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
     * @param Request $request
     * 订单列表
     */
    public function orderList(Request $request){
        $uid = $this->checkUid($request->header('deviceid'));
        $loaner = LoanerModel::where(['user_id'=>$uid])->first();
        if($loaner) {
            if (in_array($request->type,[0,1,2,3]))
            {
                //搜索条件
                if($request->type == 1)//办理中
                {
                    $list = LoanModel::where([['type','=',2],['loaner_id','=', $loaner->id],['status','=',1],['process','<',37]])
                        ->with(['user' => function ($query) {
                            $query->select(['id', 'username', 'is_auth']);
                        }, 'type' => function ($query) {
                            $query->select(['attr_value as type_name', 'id']);
                        }, 'timeLimit' => function ($query) {
                            $query->select(['attr_value as time_limit', 'id']);
                        }, 'region' => function ($query) {
                            $query->select(['id', 'name']);
                        }])
                        ->select(['id','process', 'name', 'age', 'city_id', 'loan_type', 'apply_number', 'is_check', 'apply_information', 'loan_type', 'description', 'create_time', 'time_limit','mobile','process','update_time','junk_id','type as type_'])
                        ->orderBy('update_time','desc')
                        ->orderBy('id','desc')
                        ->paginate($this->pageSize);
                }elseif($request->type == 2){//待评价
                    $list = LoanModel::where([['type','=',2],['loaner_id','=', $loaner->id],['is_comment','=',1],['status','=',1]])
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
                        ->select(['id','process', 'name', 'age', 'city_id', 'loan_type', 'apply_number', 'is_check', 'apply_information', 'loan_type', 'description', 'create_time', 'time_limit','mobile','process','update_time','junk_id','type as type_'])
                        ->orderBy('update_time','desc')
                        ->orderBy('id','desc')
                        ->paginate($this->pageSize);
                }elseif($request->type == 3)//已完成
                {
                    $list = LoanModel::where([['type','=',2],['loaner_id','=', $loaner->id],['status','=',1]])
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
                        ->select(['id','process', 'name', 'age', 'city_id', 'loan_type', 'apply_number', 'is_check', 'apply_information', 'loan_type', 'description', 'create_time', 'time_limit','mobile','update_time','junk_id','type as type_'])
                        ->orderBy('update_time','desc')
                        ->orderBy('id','desc')
                        ->paginate($this->pageSize);
                }else {//全部订单
//                    DB::connection()->enableQueryLog();
                    $list = LoanModel::where([['loaner_id','=',$loaner->id],['status','=',1],['type','=',2]])
                        ->with(['user' => function ($query) {
                            $query->select(['id', 'username', 'is_auth']);
                        }, 'type' => function ($query) {
                            $query->select(['attr_value as type_name', 'id']);
                        }, 'timeLimit' => function ($query) {
                            $query->select(['attr_value as time_limit', 'id']);
                        }, 'region' => function ($query) {
                            $query->select(['id', 'name']);
                        }])
                        ->select(['id','process', 'name', 'age', 'city_id', 'loan_type', 'apply_number', 'is_check', 'apply_information', 'loan_type',  'description', 'create_time', 'time_limit','mobile','process','user_id','update_time','junk_id','type as type_'])
                        ->orderBy('update_time','desc')
                        ->orderBy('id','desc')
                        ->paginate($this->pageSize);
//                    $log = DB::getQueryLog();
//                    dd($log);
                }
                if (count($list)) {
                    $list = $list->toArray();
                    foreach ($list['data'] as &$item) {
                        $info = [];
                        ////////
                        $info[] = ['attr_value' => $item['region']['name'],'attr_key' => '现居'];
                        $info[] = ['attr_value' =>$item['mobile'] , 'attr_key' => '手机'];
                        $info[] = ['attr_value' =>$item['age'] , 'attr_key' => '年龄'];
                        ////////
                        if ($item['apply_information']) {
                            $information = LoanerAttrModel::whereIn('id', explode(',', $item['apply_information']))
                                ->with('parent')
                                ->get();
                            if (count($information)) {
                                foreach ($information as $value) {
                                    $info[] = ['attr_value' => $value->attr_value, 'attr_key' => $value->parent['attr_value']];
                                }
                            }
                        }
                        $item['information'] = $info;
                        $item['apply_user'] = $item['user']['username'];
                        $item['time_limit'] = $item['time_limit']['time_limit'];
                        $item['apply_type'] = $item['type']['type_name'];
                        //审核流水
                        $processList = LoanExamineModel::where(['loan_id'=>$item['id']])
                            ->with(['process'=>function($query){
                                $query->select(['id','attr_value']);
                            },'loaner'=>function($query){
                                $query->select(['id','loanername','loanername_mobile']);
                            }])
                            ->get(['id','loan_id','loaner_id','process','create_time','describe']);
                        if($processList)
                        {
                            $processList = $processList->toArray();
                            foreach($processList as &$val)
                            {
                                $val['process_id'] = $val['process']['id'];
                                $val['process'] = $val['process']['attr_value'];
                                $val['loaner_name'] = $val['loaner']['loanername'];
                                $val['loaner_mobile'] = $val['loaner']['loanername_mobile'];
                                unset($val['loaner']);
                            }
                        }else{
                            $processList = null;
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
//                        $item['processHistory'] = $processList;
                        $item['process_current'] = $processList;
                        $item['is_auth'] = $item['user']['is_auth'];
                        $item['region'] = $item['region']['name'];
                        $item['process_all'] = LoanerAttrModel::where([['pid' ,'=', 10],['id','<>',38]])
                            ->get(['id', 'attr_value']);
//                        unset($item['process']);
                        unset($item['user']);
                        unset($item['mobile']);
//                unset($item['region']);
                        unset($item['apply_information']);
//                        unset($item['time_limit']);
                        unset($item['type']);
                        unset($item['loan_type']);
                    }
                    $this->setData($list);
            }else{
                    $this->setMessage('暂无数据');
                    $this->setstatusCode(5000);
                }
            }
            else{
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
     */
    public function index(Request $request)
    {
        $uid = $request->uid;
        $info = UserModel::where(['id'=>$uid])
            ->with(['shop'=>function($query){
                $query->select(['id','user_id','check_result','spread']);
            }])
            ->first(['id','is_auth']);
        $loaner = LoanerModel::where(['user_id' => $uid])
            ->first(['id']);
        if(count($info) && count($loaner))
        {
            if($info->is_auth >1) {
                if (count($loaner)) {
                    $info->is_publish = JunkModel::where(['loaner_id' => $loaner->id])
                        ->count() ? '1' : '0';
                } else {
                    $info->is_publish = '0';
                }
                $examine = LoanExamineModel::where(['loaner_id' => $loaner->id])
                    ->first();
                $info->examine = count($examine) ? '1' : '0';//订单的审核任务
                if ($info->shop) {
                    $info->shop_auth = $info->shop['check_result'] == 2 ? '1' : '0';//商铺认证
                    $info->spread = $info->shop['spread'] ? '1' : '0';//商铺推广
                } else {
                    $info->shop_auth = '0';
                    $info->spread = '0';
                }
                $info->recharge = '0';//充值
                ///
                $loaner = LoanerModel::where(['user_id'=>$uid])->first(['id']);
                $info->is_publish=JunkModel::where(['loaner_id'=>$loaner->id])
                     ->count() ? '1' : '0';
//                $arr =
//                $info->is_publish = '0';//发布甩单
                ///
//                $info->is_publish=JunkModel::where([''])
//                dd($request->all());
                /*
                 * $loaner = LoanerModel::where(['user_id'=>$uid])->find(['id']);
                 * $info->is_publish=JunkModel::where(['loaner_id'=>$loaner->id])
                 * ->count()
                 *
                 *
                 * */
            }else {
                $info->shop_auth = '0';
                $info->spread = '0';
                $info->recharge = '0';//充值
                $info->shop_auth = '0';
                $info->spread = '0';
                $info->examine = '0';
                $info->is_publish = '0';//发布甩单
            }
            $list = ArticleModel::where(['is_display' => 1])
                ->select('title', 'picture', 'id', 'create_time', 'views')
                ->orderBy('recommend','desc')
                ->orderBy('id','desc')
                ->limit(6)
                ->get();
            $info->article = $list;
            $info->junk = $this->junk();
            unset($info->shop);
            $this->setData($info);
        }
        else
        {
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @return mixed
     * 抢单
     */
    public function junk()
    {
        $list = JunkModel::where([['status' ,'=',1],['is_check','=',2],['expire_time','>',time()]])
            ->with([ 'LoanType' => function ($query) {
                $query->select(['id', 'attr_value']);
            }, 'region' => function ($query) {
                $query->select(['id', 'name']);
            }, 'limit' => function ($query) {
                $query->select(['id', 'attr_value']);
            }])
            ->select(['id', 'name', 'age', 'region_id', 'loan_type', 'apply_number', 'is_check', 'apply_information', 'loan_type', 'price', 'description', 'source_id', 'source_table', 'create_time', 'time_limit','price','mobile','is_vip','city_id'])
            ->orderBy('id', 'desc')
            ->limit(4)
            ->get();
//        dd($list->toArray());
        if (count($list)) {
            $list = $list->toArray();
            foreach ($list as &$item) {
                    $arr = [];
                    $item['type'] = $item['loan_type']['attr_value'];
                    $item['city'] = $item['region']['name'];
                    $item['type'] = $item['loan_type']['attr_value'];
                    $item['time_limit'] = $item['limit']['attr_value'];
                    $item['is_auth'] = 1;
                    $arr[] = ['attr_value' => $item['city'],'attr_key' => '现居'];
                    $arr[] = ['attr_value' =>substr($item['mobile'],0,3).'****'.substr($item['mobile'],7) , 'attr_key' => '手机'];
                    $arr[] = ['attr_value' =>(date('Y') - substr($item['age'],0,strpos($item['age'],'-')+1)).'岁', 'attr_key' => '年龄'];
                    if($item['apply_information'])
                    {
                    $info = LoanerAttrModel::whereIn('id', explode(',', $item['apply_information']))
                        ->with('parent')
                        ->get();
                    if (count($info)) {
                        foreach ($info as $value) {
                            $arr[] = ['attr_value' => $value->attr_value, 'attr_key' => $value->parent['attr_value']];
                        }
                        }
                    }
                    $item['info'] = $arr;
                    unset($item['loan_type']);
                    unset($item['region']);
                    unset($item['limit']);
                unset($item['age']);
                unset($item['source']);
                unset($item['mobile']);
                unset($item['city']);
                }
//                unset($item['age']);
//                unset($item['mobile']);
//                unset($item['city']);
//            }
            return $list;
        }
    }

    /**
     * 认证成功资料
     */
    public  function authDocument(Request $request)
    {
        $is_auth = UserAuthModel::where(['user_id'=>$request->uid])
            ->with('province','city','district')
            ->first(['front_identity','back_identity','true_name','address','is_pass','province_id','city_id','region_id','work_card','card','contract_page','logo_personal','mechanism','mechanism_type','department','is_pass','identity_number','photo']);
        if(count($is_auth))
        {
            $address = '';
            if($is_auth->province) $address =$is_auth->province['name'];
            if($is_auth->city) $address .= ','. $is_auth->city['name'];
            if($is_auth->district) $address .= ','. $is_auth->district['name'];
            $is_auth->address = trim($address,',');
            $is_auth->identity_number = substr($is_auth->identity_number,0,3).'************'.substr($is_auth->identity_number,15);
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
