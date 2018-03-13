<?php

namespace App\Api\v1\Mobile\Business;

use App\Api\Models\JunkModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanEvaluateModel;
use App\Api\Models\LoanExamineModel;
use App\Api\Models\LoanModel;
use App\Api\Models\ReportModel;
use App\Api\Models\ShopModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Api\Models\RegionModel;
class ShopController extends BaseController
{
       //店铺主页
    public function index(Request $request){
        $uid=$request->input('uid');
        $create_time=$request->input('create_time',time());
        $shopModel=ShopModel::from('shop as s')
            ->select('ua.true_name as username','ua.city_id','u.header_img','l.score','s.pageviews','s.status','s.check_result','s.loaner_id')
                   ->leftJoin('user as u','u.id','=','s.user_id')
                   ->leftJoin('user_auth as ua','ua.user_id','=','u.id')
                   ->leftJoin('loaner as l','l.user_id','=','s.user_id')
                   ->where(['s.user_id'=>$uid])
                   ->first();
        if($shopModel==null){
            $data=['check_result'=>0];
            return $this->setstatusCode('200')->setMessage('您尚未创建店铺')->setData($data)->result();
        }
        if($shopModel->check_result!=2){
            $data=['check_result'=>$shopModel->check_result];
            $msg='';
            if($shopModel->check_result==1){
                $msg='您的店铺资料正在审核中,请耐心等待';
            }
            if($shopModel->check_result==3){
                $msg='您的店铺资料审核不通过';
            }
            return $this->setstatusCode('200')->setMessage($msg)->setData($data)->result();
        }
        $orders=LoanModel::from('loan as l')
                ->select('l.id','l.name as customer','l.create_time','l.is_vip','l.apply_number','lt.attr_value as loan_type','p.attr_value as period','l.age','r.name as current_place','l.mobile','l.create_time','l.apply_information')
               ->leftJoin('loaner_attr as lt','lt.id','=','l.loan_type')->leftJoin('loaner_attr as p','p.id','=','l.time_limit')
               ->leftJoin('region as r','r.id','=','l.region_id')
               ->where(['l.loaner_id'=>$shopModel->loaner_id,'l.check_result'=>2,'l.type'=>1])
               ->where('l.create_time','<',$create_time)
               ->orderBy('l.create_time','desc')
               ->limit($this->pageSize)
               ->get();
        if($orders !=null){
            foreach ($orders as $key=>$order){
                $order->info=$this->getAttrKeyAndValue($order->apply_information);
                $order->age = date('Y',time()) - substr($order->age,0,strpos($order->age,'-')).'岁' ;
                unset($order->apply_information);
                $orders[$key]=$order;
            }
        }
        $city = RegionModel::find($shopModel->city_id,['name']);
        $shopModel->service_city = $city?$city->name:'';
        $check_result=$shopModel->check_result;
        unset($shopModel->check_result);
        unset($shopModel->loaner_id);
        $data=['check_result'=>$check_result,'shop_info'=>$shopModel,'order'=>$orders];
        return $this->setstatusCode('200')->setMessage('ok')->setData($data)->result();
    }

    /**
     * @param Request $request
     * 用户经理信息
     */
    public function showCreate(Request $request){
        $info = UserModel::with(['auth'=>function($query){
            $query->select(['user_id','true_name','province_id','region_id','city_id','mechanism','photo']);
        }])
//            ->where(['is_disable'=>1,'is_auth'=>2])
            ->where(['is_disable'=>1])
            ->find($request->uid,['id','is_auth']);
        if(count($info))
        {
            $info = $info->toArray();
            $item['true_name'] = $info['auth']['true_name'];
            $province = RegionModel::find($info['auth']['province_id'],['id','name']);
            $city = RegionModel::find($info['auth']['city_id'],['id','name']);
            $region = RegionModel::find($info['auth']['region_id'],['id','name']);
            $info['true_name'] = $info['auth']['true_name'];
            $info['mechanism'] = $info['auth']['mechanism'];
            $info['header_img'] = $info['auth']['photo'];
            if($province||$city||$region)
            {
                $addr['district'] = $region->name;
                $addr['city'] = $city ? $city->name:'';
                $addr['province'] = $province ? $province->name:'';
                $info['address'] = $addr;
            }
            else{
                $info['address'] = null;
            }
            unset($info['auth']);
            $this->setData($info);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }




    //创建店铺
    public function create(Request $request){
        $work_time=$request->input('work_time','');
        $introduce=$request->input('introduce','');
        if($work_time==''){
            return $this->setstatusCode(4002)->setMessage('请填写工作年限')->result();
        }
        if($introduce==''){
            return $this->setstatusCode(4002)->setMessage('请填写合作信息')->result();
        }
        $uid=$request->input('uid');
        $userModel=UserModel::from('user as u')->select('l.id as loaner_id','ua.id as auth_id','l.loanername','ua.mechanism')
                  ->leftJoin('loaner as l','l.user_id','=','u.id')
                  ->leftJoin('user_auth as ua','ua.user_id','=','u.id')->where(['u.id'=>$uid])->first();
        if($userModel==null){
            return $this->setstatusCode(4002)->setMessage('用户信息有误')->result();
        }
        $shopModel=ShopModel::where(['user_id'=>$uid])->first();
        if($shopModel!=null){
            return $this->setstatusCode(4010)->setMessage('请勿重复提交')->result();
        }
        $shopModel=new ShopModel();
        $shopModel->user_id=$uid;
        $shopModel->loaner_id=$userModel->loaner_id;
        $shopModel->auth_id=$userModel->auth_id;
        $shopModel->loaner_name=$userModel->loanername;
        $shopModel->mechanism_name=$userModel->mechanism;
        $shopModel->work_time=$userModel->work_time;
        $shopModel->create_time=time();
        $shopModel->check_result = 2;
        $shopModel->introduce=$introduce;
        if($shopModel->save()){
            return $this->result();
        }else{
            return $this->setstatusCode(500)->setMessage('提交失败,错误原因:'.$shopModel->getMessage())->result();
        }
    }

    //客户订单
    public function customerOrder(Request $request){
        $status=$request->input('status','');  //0=>办理中,1=>待评价,2=>订单记录,不传为全部
        $create_time=$request->input('create_time','');
        $loaner_id=$request->input('loaner_id');
        $refer=$request->input('refer','');
        if($refer==''){
            return $this->setstatusCode(4002)->setMessage('订单来源不能为空')->result();
        }
        if($refer!='customer' && $refer!='junk'){
            return $this->setstatusCode(4002)->setMessage('订单来源参数错误')->result();
        }
        $query=LoanModel::from('loan as l')->select('l.id','l.name as customer','l.create_time','l.is_vip','l.apply_number','lt.attr_value as loan_type','p.attr_value as period','l.age','r.name as current_place','l.mobile','l.create_time','l.apply_information','l.process','is_comment')
            ->leftJoin('loaner_attr as lt','lt.id','=','l.loan_type')
            ->leftJoin('loaner_attr as p','p.id','=','l.time_limit')
            ->leftJoin('region as r','r.id','=','l.region_id')->where(['l.loaner_id'=>$loaner_id,'l.check_result'=>2]);
        if($refer=='customer'){
            $query=$query->where(['l.type'=>1,'l.junk_id'=>0]);
        }else{
            $query=$query->where(['l.type'=>2])->where('l.junk_id','!=',0);
        }
        if($status!=''){
            if($status=='0'){
                $query=$query->where('l.process','<',37);
            }
            if($status=='1'){
                $query=$query->where(['l.is_comment'=>1])->where('l.process','>=',37);
            }
            if($status=='2'){
                $query=$query->where(['l.is_comment'=>2])->where('l.process','>=',37);
            }
        }
        if($create_time!=''){
            $query=$query->where('l.create_time','<',$create_time);
        }
        $orders=$query->orderBy('l.create_time','desc')->limit($this->pageSize)->get(); //process:11=>申请贷款,12=>资料提交,36=>审批资料,37=>审批放款,38=>申请失败
        $data=[];
        if(count($orders)){
            foreach ($orders as $order){
                if($order->apply_information){
                    $order->info=$this->getAttrKeyAndValue($order->apply_information);
                }
                $order->processIds=[];
                $processExamine=LoanExamineModel::select('process')->where(['loan_id'=>$order->id])->get();
                if($processExamine){
                    $order->processIds=array_column($processExamine->toArray(),'process');
                }
                unset($order->apply_information);
                $data[]=$order;
            }
            $this->setData($orders);
        }else{
             $this->setMessage('暂无数据');
             $this->setstatusCode(5000);
        }
        return $this->result();
    }

    //客户订单详情
    public function customerOrderDetail(Request $request){
        $loaner_id=$request->input('loaner_id');
        $id=$request->input('id','');
        if($id==''){
            return $this->setstatusCode(4002)->setMessage('订单id不能为空')->result();
        }
        $order=LoanModel::from('loan as l')->select('l.id','l.loan_account as order_num','l.name as customer','l.create_time','l.is_vip','l.apply_number','lt.attr_value as loan_type','p.attr_value as period','l.age','r.name as current_place','l.mobile','l.create_time','l.apply_information','l.process')
            ->leftJoin('loaner_attr as lt','lt.id','=','l.loan_type')->leftJoin('loaner_attr as p','p.id','=','l.time_limit')
            ->leftJoin('region as r','r.id','=','l.region_id')
            ->where(['l.loaner_id'=>$loaner_id,'l.check_result'=>2,'l.id'=>$id])->first();
        if($order==null){
            return $this->setstatusCode(5000)->setMessage('该订单不存在或已被删除')->result();
        }
        $data=[];
        if($order!=null){
            $order->info=$this->getAttrKeyAndValue($order->apply_information);
            $order->age = date('Y',time()) - substr($order->age,0,strpos($order->age,'-')).'岁';
            unset($order->apply_information);
            //$order->tip=LoanExamineModel::select('describe','create_time')->where(['loaner_id'=>$loaner_id,'loan_id'=>$id])->orderBy('create_time','desc')->get();
            $order->processHistory=[];
            $processExamine=LoanExamineModel::where(['loan_id'=>$order->id])
                ->with(['loaner'=>function($query){
                    $query->select(['loanername','loanername_mobile','id']);
                }])
                ->get();
            if($processExamine){
                foreach($processExamine as &$item)
                {
                    $item['loanername'] = $item['loaner']['loanername'];
                    $item['mobile'] = $item['loaner']['loanername_mobile'];
                    unset($item['loaner']);
                }
                $order->processHistory=$processExamine;
            }
            $processAll = LoanerAttrModel::where([['pid','=',10],['id','<>','38']])
                        ->get(['id','attr_value']);
            $order->processAll = $processAll;
            $data=$order;
        }
        return $this->setData($data)->result();
    }

    //订单拒绝
    public function customerOrderRefuse(Request $request){
        $loaner_id=$request->input('loaner_id');
        $id=$request->input('id','');
        $reason=$request->input('reason','');
        if($id==''){
            return $this->setstatusCode(4002)->setMessage('订单id不能为空')->result();
        }
        if($reason==''){
            return $this->setstatusCode(4002)->setMessage('拒绝原因不能为空')->result();
        }
        $order=LoanModel::where(['loaner_id'=>$loaner_id,'check_result'=>2,'id'=>$id])->first();
        if($order==null){
            return $this->setstatusCode(5000)->setMessage('订单不存在或已被删除')->result();
        }
        if($order->process=='38'){
            return $this->setstatusCode(4010)->setMessage('已拒绝,请勿重复提交')->result();
        }
        $loanExamine=new LoanExamineModel();
        $loanExamine->loan_id=$id;
        $loanExamine->loaner_id=$loaner_id;
        $loanExamine->process=38;
        $loanExamine->create_time=time();
        $loanExamine->status=1;
        $loanExamine->describe='申请被拒绝,原因为'.$reason;
        $order->process=38;
        $order->update_time=time();
        DB::beginTransaction();
        try{
            $loanExamine->save();
            $order->save();
            DB::commit();
            return $this->result();
        }catch (\Exception $e){
            DB::rollaback();
            return $this->setstatusCode(500)->setMessage('操作失败,错误原因:'.$e->getMessage())->result();
        }
    }

    //订单流程
    public function customerOrderProcess(Request $request){
        $id=$request->input('id','');
        $money=$request->input('money','');
        $status=$request->input('status','');  //1->2步传1,2->3步传2,3->4步传3
        $loaner_id=$request->input('loaner_id');
        $mobile=$request->input('mobile');
        if($id==''){
            return $this->setstatusCode(4002)->setMessage('订单id不能为空')->result();
        }
        if($status!=1 && $status!=2 && $status!=3){
            return $this->setstatusCode(4002)->setMessage('订单流程状态有误')->result();
        }
        //process:11=>申请贷款,12=>资料提交,36=>审批资料,37=>审批放款,38=>申请失败
        $order=LoanModel::where(['loaner_id'=>$loaner_id,'check_result'=>2,'id'=>$id])->first();
        if($order==null){
            return $this->setstatusCode(5000)->setMessage('订单不存在或已被删除')->result();
        }
        if($order->process==38){
            return $this->setstatusCode(4010)->setMessage('订单已被拒绝')->result();
        }
        $loanExamine=new LoanExamineModel();
        $loanExamine->loan_id=$id;
        $loanExamine->loaner_id=$loaner_id;
        $loanExamine->create_time=time();
        $loanExamine->status=1;
        $order->update_time=time();
        if($status==1){
            if($order->process>11){
                return $this->setstatusCode(4010)->setMessage('资料已提交,请勿重复操作')->result();
            }
            $loanExamine->process=12;
            $loanExamine->describe='资料符合要求,已安排客户经理'.$mobile.'为您服务';
            $order->process=12;
        }
        if($status==2){
//            if($money==''){
//                return $this->setstatusCode(4002)->setMessage('请填写签约金额')->result();
//            }
            if(!is_numeric($money)){
                return $this->setstatusCode(4002)->setMessage('填写的金额必须是数字')->result();
            }
            if($order->process!=12){
                return $this->setstatusCode(4010)->setMessage('资料审批中,请勿重复提交')->result();
            }
            $loanExamine->process=36;
            $loanExamine->describe='资料已提交至审批,申请签约金额为'.$money.'万元';
            $order->process=36;
        }
        if($status==3){
            if($money==''){
                return $this->setstatusCode(4002)->setMessage('请填写放款金额')->result();
            }
            if(!is_numeric($money)){
                return $this->setstatusCode(4002)->setMessage('填写的金额必须是数字')->result();
            }
            if($order->process!=36){
                return $this->setstatusCode(4010)->setMessage('审批放款中,请勿重复提交')->result();
            }
            $loanExamine->process=37;
            $loanExamine->describe='审批放款中,放款金额为'.$money.'万元';
            $order->process=37;
            $order->loan_number=$money;
            $order->loan_time=time();
        }
        DB::beginTransaction();
        try{
            $loanExamine->save();
            $order->save();
            DB::commit();
            return $this->result();
        }catch (\Exception $e){
            DB::rollback();
            return $this->setstatusCode(500)->setMessage('操作失败,错误原因:'.$e->getMessage())->result();
        }
    }

    //订单评价界面展示印象标签
    public function customerOrderCommentLabel(){
        $labels=LoanerAttrModel::select('id','attr_value as label')->where(['pid'=>4,'function_name'=>'b2c','status'=>1])->orderBy('sort','asc')->get();
        return $this->setMessage('ok')->setData($labels)->result();
    }

    //提交评价
    public function customerOrderComment(Request $request){
        $id=$request->input('id','');
        $score=$request->input('score','');
        $label=$request->input('label_ids',''); //印象id合集,逗号间隔
        $describe=$request->input('describe','');
        if($id==''){
            return $this->setstatusCode('2001')->setMessage('订单id不能为空')->result();
        }
        if($score==''){
            return $this->setstatusCode(4002)->setMessage('评分不能为空')->result();
        }
        if(!is_numeric($score)){
            return $this->setstatusCode(4002)->setMessage('评分必须是数字')->result();
        }
        if($score>5){
            return $this->setstatusCode(4002)->setMessage('评分只能在5以内')->result();
        }
        if(strlen($score)>3){
            return $this->setstatusCode(4002)->setMessage('最多保留一位小数')->result();
        }
        if($label==''){
            return $this->setstatusCode(4002)->setMessage('请选择印象')->result();
        }
        $loanModel=LoanModel::where(['loaner_id'=>$request->input('loaner_id'),'id'=>$id,'is_comment'=>1])->where('process','>=',37)->first();
        if($loanModel==null){
            return $this->setstatusCode(5000)->setMessage('订单不存在或已被评价')->result();
        }
        $loanEvaluate=new LoanEvaluateModel();
        $loanEvaluate->loan_id=$id;
        $loanEvaluate->user_id=$request->input('uid');
        $loanEvaluate->describe=$describe;
        $loanEvaluate->score_avg=$score;
        $loanEvaluate->status=1;
        $loanEvaluate->focus=$label;
        $loanEvaluate->create_time=time();
        $loanModel->is_comment=2;
        $loanModel->update_time=time();
        DB::beginTransaction();
        try{
            $loanEvaluate->save();
            $loanModel->save();
            DB::commit();
            return $this->result();
        }catch (\Exception $e){
            DB::rollback();
            return $this->setstatusCode(500)->setMessage('评价失败,错误原因:'.$e->getMessage())->result();
        }
    }

    //甩单
    public function customerOrderJunk(Request $request){
        $id=$request->input('id','');
        $score=intval($request->input('score',''));
        $loaner_id=$request->input('loaner_id');
        $loanername=$request->input('loanername');
        if($id==''){
            return $this->setstatusCode(4002)->setMessage('订单id不能为空')->result();
        }
        if($score==0){
            return $this->setstatusCode(4002)->setMessage('请输入积分金额')->result();
        }
        if($score<0){
            return $this->setstatusCode(4002)->setMessage('积分金额必须是正整数')->result();
        }
        $loanModel=LoanModel::where(['id'=>$id,'loaner_id'=>$loaner_id])->first();
        if($loanModel==null){
            return $this->setstatusCode(5000)->setMessage('该订单不存在或已被删除')->result();
        }
        if($loanModel->process!=11){
            return $this->setstatusCode(500)->setMessage('该订单已被跟进,不能被甩出')->result();
        }
        if($loanModel->junk_id){
            return $this->setstatusCode(4010)->setMessage('该订单已被甩出,请勿重复操作')->result();
        }
        //junk_loan
        $junkLoanModel=new JunkModel();
        $junkLoanModel->loaner_id=$loaner_id;
        $junkLoanModel->loaner_name=$loanername;
        $junkLoanModel->source_id=$id;
        $junkLoanModel->is_check=2;
        $junkLoanModel->apply_information=$loanModel->apply_information;
        $junkLoanModel->apply_number=$loanModel->apply_number;
        $junkLoanModel->time_limit=$loanModel->time_limit;
        $junkLoanModel->loan_type=$loanModel->loan_type;
        $junkLoanModel->province_id=$loanModel->province_id;
        $junkLoanModel->city_id=$loanModel->city_id;
        $junkLoanModel->region_id=$loanModel->region_id;
        $junkLoanModel->age=$loanModel->age;
        $junkLoanModel->name=$loanModel->name;
        $junkLoanModel->price=$score;
        $junkLoanModel->mobile=$loanModel->mobile;
        $junkLoanModel->description=$loanModel->description;
        $junkLoanModel->is_vip=$loanModel->is_vip;
        $junkLoanModel->create_time=time();
        $junkLoanModel->update_time=time();
        $junkLoanModel->expire_time=time()+3600*self::JUNK_EXPIRE_TIME;
        $junkLoanModel->status=1;
        $junkLoanModel->job_information=$loanModel->job_information;
        $junkLoanModel->is_marry=$loanModel->is_marry;
        //loan
        $loanModel->status=3;
        $loanModel->update_time=time();
        $loanModel->discard_time=time();
        DB::beginTransaction();
        try{
            $junkLoanModel->save();
            $loanModel->junk_id=$junkLoanModel->id;
            $loanModel->save();
            DB::commit();
            return $this->result();
        }catch (\Exception $e){
            DB::rollback();
            return $this->setstatusCode(500)->setMessage('甩单失败,错误原因:'.$e->getMessage())->result();
        }

    }

    //客户详情
    public function customerDetail(Request $request){
        $id=$request->input('id','');
        if($id==''){
            return $this->setstatusCode(4002)->setMessage('订单id不能为空')->result();
        }
        $order=LoanModel::from('loan as l')->select('l.id','l.name as customer','l.create_time','c.name as current_place','l.is_vip','l.apply_number','lt.attr_value as loan_type',
                'p.attr_value as period','l.age','l.mobile','l.is_marry','l.description','l.job_information','l.apply_information')
               ->leftJoin('region as c','c.id','=','l.city_id')->leftJoin('loaner_attr as lt','lt.id','=','l.loan_type')
               ->leftJoin('loaner_attr as p','p.id','=','l.time_limit')->where(['l.id'=>$id])->first();
        if($order==null){
            return $this->setstatusCode(5000)->setMessage('订单不存在')->result();
        }
        $order->job=$this->getAttrKeyAndValue($order->job_information);
        $order->assets=$this->getAttrKeyAndValue($order->apply_information);
        unset($order->job_information);
        unset($order->apply_information);
        return $this->setData($order)->result();
    }

    //举报
    public function report(Request $request){
        $id=$request->input('id','');
        $reason=$request->input('reason','');
        $from_id=$request->input('uid');
        $from_name=$request->input('loanername');
        if($id==''){
            return $this->setstatusCode(4002)->setMessage('订单id不能为空')->result();
        }
        if($reason==''){
            return $this->setstatusCode(4002)->setMessage('请选择举报原因')->result();
        }
        $reportModel=ReportModel::where(['from_uid'=>$from_id,'loan_id'=>$id])->first();
        if($reportModel!=null){
            return $this->setstatusCode(4010)->setMessage('请勿重复提交')->result();
        }
        $loanModel=LoanModel::find($id);
        if($loanModel==null){
            return $this->setstatusCode(5000)->setMessage('订单异常')->result();
        }
        if($loanModel->type==1){ //来自用户申请的单子,举报用户
            $to_id=$loanModel->user_id;
            $to_name=$loanModel->name;
            $type=1;
        }else{ //来自经理,举报经理
            $loanerModel=LoanModel::from('loan as l')->select('le.user_id','le.loanername')->leftJoin('junk_loan as jl','jl.id','=','l.junk_id')
                        ->leftJoin('loaner as le','le.id','=','jl.loaner_id')->where(['l.id'=>$id])->first();
            if($loanerModel==null){
                return $this->setstatusCode(5000)->setMessage('查询经理信息出错')->result();
            }
            $to_id=$loanerModel->user_id;
            $to_name=$loanerModel->loanername;
            $type=3;
        }
        $reportModel=new ReportModel();
        $reportModel->from_uid=$from_id;
        $reportModel->to_uid=$to_id;
        $reportModel->from_name=$from_name;
        $reportModel->to_name=$to_name;
        $reportModel->report_reason=$reason;
        $reportModel->is_examine=1;
        $reportModel->create_time=time();
        $reportModel->update_time=time();
        $reportModel->type=$type;
        $reportModel->loan_id=$id;
        if($reportModel->save()){
            return $this->setMessage('提交成功')->result();
        }else{
            return $this->setstatusCode(500)->setMessage('提交失败')->result();
        }

    }























}
