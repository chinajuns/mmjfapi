<?php

namespace App\Api\v1\Mobile\Business;

use App\Api\Models\IntegralListModel;
use App\Api\Models\JunkModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanModel;
use App\Api\Models\LootModel;
use App\Api\Models\RegionModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use DebugBar\DebugBar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends BaseController
{
    /**
     * @return mixed
     * 抢单配置
     */
    public function grabConfig(){
        $config = LoanerAttrModel::where(['type'=>'pc_loot'])
            ->with(['values'=>function($query){
                $query->select(['id','pid','attr_value']);
            }])
            ->get(['id','attr_value']);
        $config = $config->toArray();
        return $this->setData($config)->result();
    }

    //抢单列表
    public function index(Request $request){
        $is_manager = UserModel::where(['id'=>$request->uid,'type'=>2])->first();
        if($is_manager) {
            $is_vip = $request->input('is_vip');
            $region_id = $request->input('region_id');
            $has_house = $request->input('has_house');
            $has_car = $request->input('has_car');
            $create_time = $request->input('create_time', time());

            $query = JunkModel::from('junk_loan as jl')->select('jl.id', 'jl.name', 'jl.create_time', 'jl.is_vip', 'jl.apply_number', 'lt.attr_value as loan_type', 'la.attr_value as period', 'jl.price', 'jl.age', 'r.name as current_place', 'jl.mobile', 'jl.source_id', 'jl.apply_information')
                ->leftJoin('loaner_attr as la', 'la.id', '=', 'jl.time_limit')
                ->leftJoin('region as r', 'r.id', '=', 'jl.city_id')
                ->leftJoin('loaner_attr as lt', 'lt.id', '=', 'jl.loan_type')
                ->where([['jl.status' ,'=',1], ['jl.is_check' ,'=', 2],['jl.expire_time', '>', time()]]);
            if ($is_vip) {
                $query = $query->where(['jl.is_vip' => $is_vip]);
            }
            if ($region_id) {
                $query = $query->where(['jl.region_id' => $region_id]);
            }
            if ($has_house) {
                $query = $query->whereRaw("FIND_IN_SET($has_house,apply_information)");
            }
            if ($has_car) {
                $query = $query->whereRaw("FIND_IN_SET($has_car,apply_information)");
            }
//        DB::connection()->enableQueryLog();
            $list = $query->where('jl.expire_time', '>', $create_time)->orderBy('jl.create_time', 'desc')->limit($this->pageSize)->get();
//        $log = DB::getQueryLog();
//        dd($log);
            if ($list == null) {
                $this->setstatusCode(5000)->setMessage('暂无订单')->result();
            }
            $data = [];
            if (count($list)) {
                foreach ($list as $key => $item) {
                    $value = [];
                    $value['id'] = $item->id;
                    $value['customer'] = $item->name;
                    if ($item->source_id) {
                        $user = LoanModel::from('loan as l')->select('u.username', 'r.name as region')->leftJoin('user as u', 'u.id', '=', 'l.user_id')->
                        leftJoin('region as r', 'r.id', '=', 'l.region_id')->where(['l.id' => $item->source_id])->first();
                        $value['customer'] = $user ? $user->username : 'unknown';
                        $current_place = $user ? $user->region : '未知地区';
                    } else {
                        $current_place = $item->current_place;
                    }
                    $value['create_time'] = $item->create_time;
                    $value['is_vip'] = $item->is_vip;
                    $value['apply_number'] = $item->apply_number;
                    $value['loan_type'] = $item->loan_type;
                    $value['period'] = $item->period;
                    $value['score'] = $item->price;
                    $mobile = substr_replace($item->mobile, '****', 3, 4);
                    $info = [['attr_key' => '出生', 'attr_value' => date('Y',time()) - substr($item->age,0,strpos($item->age,'-')).'岁' ], ['attr_key' => '现居', 'attr_value' => $current_place], ['attr_key' => '手机', 'attr_value' => $mobile]];
                    $attrs = LoanerAttrModel::from('loaner_attr as la')->select('laf.attr_value as attr_key', 'la.attr_value')->leftJoin('loaner_attr as laf', 'laf.id', '=', 'la.pid')
                        ->whereIn('la.id', explode(',', $item->apply_information))->get();
                    if ($attrs != null) {
                        $attrs = $attrs->toArray();
                        $info = array_merge($info, $attrs);
                    }
                    $value['info'] = $info;
                    $data[] = $value;
                }
                $this->setData($data);
            } else {
                $this->setstatusCode(5000);
                $this->setMessage('暂无数据');
            }
        }else{
            $this->setstatusCode(5000);
            $this->setMessage('暂无信贷经理信息');
        }
       return $this->result();
    }

    //抢单详情
    public function detail(Request $request){
        $is_manager = UserModel::where(['id'=>$request->uid,'type'=>2])->first();
        if($is_manager) {
            $id = $request->input('id');
            if (!$id) {
                return $this->setstatusCode('2001')->setMessage('订单id不能为空')->result();
            }
            $order = JunkModel::from('junk_loan as jl')->select('jl.loaner_id', 'jl.id', 'jl.name', 'jl.create_time', 'jl.is_vip', 'jl.apply_number', 'lt.attr_value as loan_type', 'la.attr_value as period', 'jl.price as score', 'jl.age', 'r.name as current_place', 'jl.mobile', 'jl.is_marry', 'jl.source_id', 'jl.job_information', 'jl.apply_information', 'jl.description','jl.status')
                ->leftJoin('loaner_attr as lt', 'lt.id', '=', 'jl.loan_type')->leftJoin('loaner_attr as la', 'la.id', '=', 'jl.time_limit')->leftJoin('region as r', 'r.id', '=', 'jl.region_id')->leftJoin('loaner as le', 'le.id', '=', 'jl.loaner_id')->where(['jl.id' => $id])->first();
            if ($order == null) {
                return $this->setstatusCode(5000)->setMessage('该订单不存在')->result();
            }
            $data = [];
            $data['id'] = $order->id;
            $data['customer'] = $order->name;
            $data['create_time'] = $order->create_time;
            if ($order->source_id) {
                $user = LoanModel::from('loan as l')->select('u.username', 'r.name as region')->leftJoin('user as u', 'u.id', '=', 'l.user_id')->
                leftJoin('region as r', 'r.id', '=', 'l.region_id')->where(['l.id' => $order->source_id])->first();
                $value['customer'] = $user ? $user->username : 'unknown';
                $current_place = $user ? $user->region : '未知地区';
            } else {
                $current_place = $order->current_place;
            }
            $data['current_place'] = $current_place;
            $data['description'] = $order->description;
            $data['is_vip'] = $order->is_vip;
            $data['apply_number'] = $order->apply_number;
            $data['loan_type'] = $order->loan_type;
            $data['period'] = $order->period;
            $data['score'] = $order->score;
            $data['status'] = $order->status;
            $data['basic']['age'] = date('Y', time()) - explode('-', $order->age)[0].'岁';
            $lootModel = LootModel::where(['junk_id' => $id, 'user_id' => $request->uid, 'status' => 1])->first();
            if ($lootModel != null)
            {
                $data['basic']['mobile'] = $order->mobile;
                $data['purchased'] = 1;
            } else {
                $data['basic']['mobile'] = substr_replace($order->mobile, '****', 3, 4);
                $data['purchased'] = 0;
            }
            $data['basic']['is_marry'] = $order->is_marry;
            $job = LoanerAttrModel::from('loaner_attr as la')->select('laf.attr_value as attr_key', 'la.attr_value')->leftJoin('loaner_attr as laf', 'laf.id', '=', 'la.pid')
                ->whereIn('la.id', explode(',', $order->job_information))->get();
            if (count($job)) {
                $data['job'] = $job->toArray();
            } else {
                $data['job'] = [];
            }
            $assets = LoanerAttrModel::from('loaner_attr as la')->select('laf.attr_value as attr_key', 'la.attr_value')->leftJoin('loaner_attr as laf', 'laf.id', '=', 'la.pid')
                ->whereIn('la.id', explode(',', $order->apply_information))->get();
            if ($assets != null) {
                $data['assets'] = $assets;
            } else {
                $data['assets'] = [];
            }
            $this->setData($data);
        }else{
            $this->setstatusCode(5000);
            $this->setMessage('暂无信贷经理信息');
        }
        return $this->result();
    }



    //检查抢单条件
    public function checkPurchase(Request $request){
        $uid=$request->input('uid');
        $userModel=UserModel::select('is_auth','integral as my_score')->where(['id'=>$uid,'type'=>2])->first();
        if($userModel==null){
            return $this->setstatusCode('2001')->setMessage('当前账号信息有误')->result();
        }
        return $this->setstatusCode('200')->setMessage('ok')->setData($userModel->toArray())->result();
    }

    //抢单确认支付
    public function purchase(Request $request){
        $id=$request->input('id');
        if(!$id){
            return $this->setstatusCode('2001')->setMessage('抢单id不能为空')->result();
        }
        $junkModel=JunkModel::from('junk_loan as jl')->select('jl.*','l.user_id as loan_user_id','le.user_id as sell_user_id')
            ->leftJoin('loan as l','l.id','=','jl.source_id')
            ->leftJoin('loaner as le','le.id','=','jl.loaner_id')
            ->where(['jl.id'=>$id])->first();
        if($junkModel==null){
            return $this->setstatusCode(5000)->setMessage('该订单不存在')->result();
        }
        if($junkModel->status!=1){
            return $this->setstatusCode(5000)->setMessage('该订单已被抢或已过期')->result();
        }
        $loanerModel=LoanerModel::select('id')->where(['user_id'=>$request->input('uid')])->first();
        if($loanerModel==null){
            return $this->setstatusCode(5000)->setMessage('信贷经理身份尚未认证')->result();
        }
        $user_buy=UserModel::find($request->input('uid'));
        if($user_buy==null){
            return $this->setstatusCode(5000)->setMessage('当前用户信息有误')->result();
        }
        if($user_buy->integral<$junkModel->price){
            return $this->setstatusCode(5000)->setMessage('对不起,您的积分余额不足')->result();
        }
        $user_sell=UserModel::find($junkModel->sell_user_id);
        if($user_sell==null){
            return $this->setstatusCode(5000)->setMessage('甩出该单的用户信息异常')->result();
        }
        DB::beginTransaction();
        try{
            //新建订单表
            $loanModel=new LoanModel();
            $loanModel->name=$junkModel->name;
            $loanModel->age=$junkModel->age;
            $loanModel->loan_account=date('YmdHis',time()).mt_rand(100000,999999);
            $loanModel->loaner_id=$loanerModel->id;
            $loanModel->user_id=$junkModel->loan_user_id;
            $loanModel->is_comment=1;
            $loanModel->status=1;
            $loanModel->apply_number=$junkModel->apply_number;
            $loanModel->junk_id=$junkModel->id;
            $loanModel->check_result=2;
            $loanModel->type=2;
            $loanModel->region_id=$junkModel->region_id;
            $loanModel->apply_information=$junkModel->apply_information;
            $loanModel->time_limit=$junkModel->time_limit;
            $loanModel->loan_type=$junkModel->loan_type;
            $loanModel->description=$junkModel->description;
            $loanModel->mobile=$junkModel->mobile;
            $loanModel->create_time=time();
            $loanModel->update_time=time();
            $loanModel->job_information=$junkModel->job_information;
            $loanModel->is_marry=$junkModel->is_marry;
            //改变甩单表状态
            $junkModel->status=3;
            $junkModel->update_time=time();
            //loot_customer
            $lootCustomer=new LootModel();
            $lootCustomer->user_id=$request->input('uid');
            $lootCustomer->loaner_id=$loanerModel->id;
            $lootCustomer->junk_id=$junkModel->id;
            $lootCustomer->status=1;
            $lootCustomer->create_time=time();
            $lootCustomer->update_time=time();
            $user_buy->integral -=$junkModel->price;
            $user_sell->integral +=$junkModel->price;

            $junkModel->save();
            $loanModel->save();
            $lootCustomer->save();
            $user_buy->save();
            $user_sell->save();
            //user_integral
            IntegralListModel::create([
                'user_id'=>$request->input('uid'),
                'integral_id'=>12,
                'number'=>$junkModel->price,
                'total'=>$user_buy->integral,
                'create_time'=>time(),
                'update_time'=>time(),
                'description'=>'抢单成功,消耗'.$junkModel->price.'积分',
                'desc'=>'junk_loan_id为'.$junkModel->id
            ]);
            IntegralListModel::create([
                'user_id'=>$junkModel->sell_user_id,
                'integral_id'=>13,
                'number'=>$junkModel->price,
                'total'=>$user_sell->integral,
                'create_time'=>time(),
                'update_time'=>time(),
                'description'=>'订单被抢成功,增加'.$junkModel->price.'积分',
                'desc'=>'junk_loan_id为'.$junkModel->id
            ]);
            DB::commit();
            return $this->setstatusCode('200')->setMessage('ok')->result();
        }catch (\Exception $e){
           DB::rollback();
            return $this->setstatusCode(500)->setMessage('抢单失败,错误原因:'.$e->getMessage())->result();
        }
    }


    //展示发布甩单条件
    public function junkAttr(){
        $loan_period=LoanerAttrModel::select('id','attr_value as period')->where(['pid'=>30])->get();
        $loan_type=LoanerAttrModel::select('id','attr_value as loan_type')->where(['pid'=>1])->get();
        $attr_job=LoanerAttrModel::select('id','attr_value as name')
            ->whereNotIn('id',[48,49])
            ->where(['function_name'=>'junk_loan_job'])
            ->orderBy('sort','asc')->get();
        if($attr_job!=null){
            foreach ($attr_job as $k=>$v){
                $v->values=LoanerAttrModel::select('id','attr_value')->where(['pid'=>$v->id])->get();
            }
        }
        $attr_assets=LoanerAttrModel::select('id','attr_value as name')->where(['function_name'=>'junk_loan_assets'])->orderBy('sort','asc')->get();
        if($attr_assets!=null){
            foreach ($attr_assets as $k2=>$item){
                $item->values=LoanerAttrModel::select('id','attr_value')->where(['pid'=>$item->id])->get();
            }
        }
        $data=['loan_period'=>$loan_period,'loan_type'=>$loan_type,'attr_job'=>$attr_job,'attr_assets'=>$attr_assets];
        return $this->setstatusCode('200')->setMessage('ok')->setData($data)->result();
    }

    /**
     * @param Request $request
     * 发布甩单
     */
    public function junkPublish(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',//贷款用户
            'apply_number' => 'required',//贷款金额
            'region_id' => 'required',//区域
            'job_information' => 'required',//申请信息:1,2,3
            'assets_information' => 'required',//申请信息:1,2,3
            'age' => 'required',//年龄
            'loan_type' => 'required',//年龄
            'mobile'=>'required',
            'is_marry'=>'required',
            'time_limit'=>'required',
            'describe'=>'required',
            'price'=>'required',
            'province_id'=>'required',
            'city_id'=>'required'
        ]);
        if (!$validator->fails()) {
                $insert = JunkModel::create(['loaner_id'=>$request->loaner_id,'loaner_name'=>$request->loanername,'apply_number'=>$request->apply_number,'time_limit'=>$request->time_limit,'loan_type'=>$request->loan_type,'region_id'=>$request->region_id,'province_id'=>$request->province_id,'city_id'=>$request->city_id,'age'=>$request->age,'name'=>$request->name,'price'=>$request->price,'mobile'=>$request->mobile,'description'=>$request->describe,'create_time'=>time(),'job_information'=>$request->job_information,'apply_information'=>$request->assets_information,'is_marry'=>$request->is_marry]);
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

    //甩单列表
    public function junkList(Request $request){
        $create_time=$request->input('create_time',time());
        $loaner_id=$request->input('loaner_id');
        $status=$request->input('status',0); //0=>全部,1=>审核(审核中,审核失败),2=>进行中,3=>已成交,4=>已过期
        if(!in_array($status,[0,1,2,3,4])){
            return $this->setstatusCode('2002')->setMessage('甩单状态异常')->result();
        }
        $query=JunkModel::from('junk_loan as jl')
            ->select('jl.id','jl.price as score','jl.name as customer','jl.create_time','jl.is_vip','jl.apply_number','lt.attr_value as loan_type','p.attr_value as period','jl.age','c.name as current_place','jl.mobile','jl.apply_information','jl.create_time','jl.is_check','jl.status','jl.expire_time','jl.price')
            ->leftJoin('loaner_attr as lt','lt.id','=','jl.loan_type')
            ->leftJoin('loaner_attr as p','p.id','=','jl.time_limit')->leftJoin('region as c','c.id','=','jl.city_id')
                  ->where(['jl.loaner_id'=>$loaner_id]);
        if($status==1){
            $query=$query->where(['jl.status'=>1])->where('jl.is_check','<>',2)->orderBy('jl.is_check','asc');
        }elseif ($status==2){
            $query=$query->where(['jl.is_check'=>2,'jl.status'=>1])->where('jl.expire_time','>',time());
        }elseif ($status==3){
            $query=$query->where(['jl.is_check'=>2,'jl.status'=>3]);
        }elseif ($status==4){
            $query=$query->where(['jl.is_check'=>2])->where('jl.status','!=',3)->where('jl.expire_time','!=','')
                ->where('jl.expire_time','<',time());
        }else{ //0

        }
        if($create_time)
        {
            $query=$query->where('jl.create_time','<',$create_time);
        }
        $junkList=$query
            ->orderBy('jl.create_time','desc')
            ->orderBy('id','desc')
            ->limit($this->pageSize)->get();
//        dd($junkList->toArray());
        if(count($junkList)){
            foreach ($junkList as $key=>$junk){
//                dd($junk->toArray());
                if($junk->is_check==1 && $junk->status==1){
                    $junk->label='审核中';
                    $junk->order_status=1;
                }
                if($junk->is_check==3 && $junk->status==1){
                    $junk->label='审核失败';
                    $junk->order_status=-1;
                }
                if($junk->is_check==2 && $junk->status==1 && $junk->expire_time>time()){
                    $junk->label='进行中';
                    $junk->order_status=2;
                }
                if($junk->is_check==2 && $junk->status==3){
                    $junk->label='已成交';
                    $junk->order_status=3;
                }
                if($junk->is_check==2 && $junk->status!=3 && $junk->expire_time && $junk->expire_time<time()){
                    $junk->label='已过期';
                    $junk->order_status=4;
                }
                if($junk->apply_information){
                    $junk->info=$this->getAttrKeyAndValue($junk->apply_information);
                }else{
                    $junk->info = null;
                }
                $junk->age = date('Y',time()) - substr($junk->age,0,strpos($junk->age,'-')).'岁';
                unset($junk->apply_information);
//                unset($junk->is_check);
//                unset($junk->status);
//                unset($junk->expire_time);
                $junkList[$key]=$junk;
            }
            $this->setData($junkList);
        }
        else{
            $this->setstatusCode(5000)->setMessage('暂无数据');
        }
        return $this->result();
    }

    //甩单详情
    public function junkDetail(Request $request){
        $id=$request->input('id','');
        $loaner_id=$request->input('loaner_id');
        if($id==''){
            return $this->setstatusCode(4002)->setMessage('订单id不能为空')->result();
        }
        $junk=JunkModel::from('junk_loan as jl')->select('jl.id','jl.is_check','jl.status','jl.expire_time','jl.create_time','jl.name as customer','c.name as current_place','jl.is_vip',
              'jl.apply_number','lt.attr_value as loan_type','p.attr_value as period','jl.age','jl.mobile','jl.is_marry','jl.job_information','jl.apply_information','jl.description')
              ->leftJoin('region as c','c.id','=','jl.city_id')->leftJoin('loaner_attr as lt','lt.id','=','jl.loan_type')->leftJoin('loaner_attr as p','p.id','=','jl.time_limit')
              ->where(['jl.id'=>$id,'jl.loaner_id'=>$loaner_id])->first();
        if($junk==null){
            return $this->setstatusCode(5000)->setMessage('订单不存在或已被删除')->result();
        }
        if($junk->job_information){
            $junk->job=$this->getAttrKeyAndValue($junk->job_information);
        }
        if($junk->apply_information){
            $junk->assets=$this->getAttrKeyAndValue($junk->apply_information);
        }
        $expire_time=$junk->expire_time;
        unset($junk->job_information);
        unset($junk->apply_information);
        unset($junk->expire_time);
        if($junk->is_check==1 && $junk->status==1){
            $junk->junk_status=1;
            $junk->label='审核中';
        }
        if($junk->is_check==2 && $junk->status==1 && $expire_time>time()){
            $junk->junk_status=2;
            $junk->label='进行中';
            $junk->expire_time=$expire_time;
        }
        if($junk->is_check==2 && $junk->status==3){
            $junk->junk_status=3;
            $junk->label='已成交';
        }
        if($junk->is_check==3){
            $junk->junk_status=-1;
            $junk->label='审核失败';
        }
        if($junk->is_check==2 &&  $junk->status!=3 && $expire_time<time()){
            $junk->junk_status=4;
            $junk->label='已过期';
        }
        $junk->age = date('Y', time()) - explode('-', $junk->age)[0].'岁';
        unset($junk->is_check);
        unset($junk->status);
        return $this->setData($junk)->result();
    }

    //重新甩单
    public function junkAgain(Request $request){
        $id=$request->input('id','');//已过期甩单的id
        $loaner_id=$request->input('loaner_id');
        if($id==''){
            return $this->setstatusCode('2001')->setMessage('甩单id不能为空')->result();
        }
        $junkModel=JunkModel::where(['id'=>$id,'loaner_id'=>$loaner_id])->where('status','<>',3)->first();
        if($junkModel==null){
            return $this->setstatusCode(5000)->setMessage('该甩单不存在或已被删除')->result();
        }
        if($junkModel->expire_time>time()){
            return $this->setstatusCode('2003')->setMessage('该订单尚未过期或已更新')->result();
        }
        if(($junkModel->expire_time+3*24*3600)>time()){
            return $this->setstatusCode('2004')->setMessage('重复甩单需在上次过期3日后')->result();
        }
        if(($junkModel->create_time+30*24*3600)<time()){
            return $this->setstatusCode('2005')->setMessage('距离初次甩单已超过1个月,不能再甩了')->result();
        }
        $junkModel->update_time=time();
        $junkModel->expire_time=time()+3600*self::JUNK_EXPIRE_TIME;
        if($junkModel->save()){
            return $this->result();
        }else{
            return $this->setstatusCode(500)->setMessage('重复甩单失败')->result();
        }

    }





















}
