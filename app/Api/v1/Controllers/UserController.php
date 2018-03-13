<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\AnswerModel;
use App\Api\Models\FeedbackModel;
use App\Api\Models\IntegralListModel;
use App\Api\Models\IntegralModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanEvaluateModel;
use App\Api\Models\LoanModel;
use App\Api\Models\NoticeModel;
use App\Api\Models\QuestionModel;
use App\Api\Models\RegionModel;
use App\Api\Models\ReportModel;
use App\Api\Models\UserAuthModel;
use App\Api\Models\UserFavoriteModel;
use App\Api\Models\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    /**
     * @param $deviceid
     * 用户基本信息
     */
    public function information(Request $request){
        $uid = $request->uid;
        $info = UserModel::where(['is_disable'=>1])
            ->select(['username','mobile','header_img','integral','is_auth'])
            ->find($uid);
        if(count($info)){
            $info->mobile = substr($info->mobile,0,3).'****'.substr($info->mobile,7);
            $this->setData($info);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * 办理进度
     */
    public function process(Request $request){
            $uid = $request->uid;
            //贷款进度
            $list  = LoanModel::with(['examine'=>function($query)
            {
                $query->with(['process'=>function($query){
                    $query->select(['attr_value as process','id']);
                },'loaner'=>function($query){
                    $query->select(['id','loanername as name','loanername_mobile as mobile']);
                }]);
            },'loaner'=>function($query){
                $query->select(['id','loanername as name','loanername_mobile as mobile' ,'create_time','header_img','max_loan','all_number','tag','attr_ids','score']);
            }])
            ->where(['user_id'=>$uid,'status'=>1])
            ->orderBy('id','desc')
            ->paginate(4,['loan_account','id','user_id','status','process','score','loaner_id','c_comment as is_comment']);
            if(count($list))
            {
                $list = $list->toArray();
               foreach($list['data'] as &$item)
               {
                   $item['score'] = $item['loaner']['score'];
                   if($item['loaner']['attr_ids'])
                   {
                       $tags = LoanerAttrModel::whereIn('id',explode(',',$item['loaner']['attr_ids']))
                           ->get()
                           ->pluck('attr_value');
                       $tags = implode(',',$tags->toArray());
                   }
                   else
                   {
                       $tags = null;
                   }
                   $item['loaner']['tags'] = $tags;
                   /*贷款进度*/
                   $item['all_process'] = LoanerAttrModel::whereIn('id',[11,12,36,37])
                       ->get(['id','attr_value']);
               }
                $this->setData($list);
            }else{
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
            return $this->result();
        }
    /**
     *贷款历史
     */
    public function history(Request $request){
            $uid = $request->uid;
            //贷款进度
            $list  = LoanModel::with(['loaner'=>function($query){
                $query->select(['id','loanername as name','loanername_mobile as mobile' ,'create_time','header_img','max_loan','all_number','tag','attr_ids','score']);
            }])
                ->where([['user_id','=',$uid],['status','=',1],['process','>=',37]])
                ->orderBy('id','desc')
                ->select(['loan_account','id','user_id','status','process','score','loaner_id','c_comment as is_comment'])
                ->paginate($this->pageSize);
            if(count($list)){
                $list = $list->toArray();
                foreach($list['data'] as &$item)
                {
                    if($item['loaner']['attr_ids'])
                    {
                        $tags = LoanerAttrModel::whereIn('id',explode(',',$item['loaner']['attr_ids']))
                            ->get()
                            ->pluck('attr_value');
                        $tags = implode(',',$tags->toArray());
                    }else{
                        $tags = null;
                    }
                    $item['loaner']['tags'] = $tags;
                    //信贷经理信息
                    /*贷款进度*/
                    $item['all_process'] = LoanerAttrModel::whereIn('id',[11,12,36,37])
                        ->get(['id','attr_value']);
//                    dd($process);
                }
                $this->setData($list);
            }else{
                $this->setMessage('暂无数据');
                $this->setstatusCode(5000);
            }
           return $this->result();
    }


    /**
     * 用户点评记录
     */
    public function scoreList(Request $request)
    {
            $uid = $request->uid;
            $list = LoanModel::with(['score'=>function($query) use ($uid){
                $query->where(['user_id'=>$uid])->select(['score_avg','loan_id']);
            },'loaner'=>function($query){
                $query->select(['id','loanername as name','header_img','max_loan','tag','all_number','attr_ids','score','loan_number']);
            }])
                ->where(['user_id'=>$uid])
                ->select(['id','loaner_id','c_comment as is_comment'])
                ->orderBy('update_time','desc')
                ->orderBy('id','desc')
                ->paginate($this->pageSize);
           if(count($list))
           {
               $list = $list->toArray();
               foreach($list['data'] as &$item)
               {
                   $item['score'] = $item['score']['score_avg'];
                   if($item['loaner']['attr_ids'])
                   {
                       $tags = LoanerAttrModel::whereIn('id',explode(',',$item['loaner']['attr_ids']))
                           ->get()
                           ->pluck('attr_value');
                       $tags = implode(',',$tags->toArray());
                   }else{
                       $tags = null;
                   }
                   $item['loaner']['tags'] = $tags;
               }
               $this->setData($list);
           }else{
               $this->setstatusCode(5000);
               $this->setMessage('暂无记录');
           }
        return $this->result();
    }

    /**
     * @param $deviceid
     * @return mixed
     * 发布评论
     */
    public function addScore(Request $request){
        $uid = $request->uid;
        $validator = Validator::make($request->all(), [
            'score' => 'required',//评星{"14":5,"15":5,"16":5}
            'focus' =>'required',
            'id' => 'required'//订单id
        ]);
        if(!$validator->fails())
        {
            $exist = LoanModel::where(['id' => $request->id,'c_comment'=>1])->first();

            if (count($exist))
            {
                //查询信贷经理
                $loaner  = LoanModel::with(['loaner'=>function($query){
                    $query->select(['id','attr_ids','score']);
                }])
                ->find($request->id,['loaner_id']);
                $str = $loaner->loaner['attr_ids'];
                if($request->focus) {
                    $str = trim($loaner->loaner['attr_ids'],',') . ',' . $request->focus;
                    $attrArr = explode(',',$str);
                    $attrArr = array_unique($attrArr);
                    sort($attrArr);
                    $str = implode(',',$attrArr);
                    $str = trim($str,',');
                    }
//                dd($str);
                //评论内容
                $total = 0;
                $comment = $request->comment ? $request->comment : '';
                $focus = $request->focus ? $request->focus : '';
                foreach (json_decode($request->score) as $item)
                {
                    $total += $item;
                }
                $avg = $total / 3;
                //更新平均分
                if($loaner->loaner['score']) {
                    $avgScore = ($loaner->loaner['score'] + $avg) / 2;
                }else{
                    $avgScore = $avg;
                }
                $userInfo = UserModel::find($request->uid);
                $integral = IntegralModel::find(20);
                if($integral){
                    $number = $integral->integral_number;
                }else{
                    $number = 10;
                }
                DB::beginTransaction();
                try {
                    LoanEvaluateModel::create(['loan_id' => $request->id, 'user_id' => $uid, 'describe' => $comment, 'score_str' => $request->score, 'score_avg' => $avg, 'focus' => $focus,'create_time'=>time()]);
                    LoanModel::where(['id'=>$request->id])->update(['c_comment'=>2]);
                    LoanerModel::where(['id'=>$loaner->loaner['id']])
                        ->update(['attr_ids'=>$str,'score'=>$avgScore,'update_time'=>time()]);
                    UserModel::where(['id'=>$request->uid])->increment('integral',$number);
                    IntegralListModel::create(['user_id'=>$request->uid,'integral_id'=>20,'number'=>$number,'total'=>$userInfo->integral+$number,'create_time'=>time(),'description'=>'评价奖励'.$number.'积分','desc'=>'订单id:'.$request->id]);
                    //更新印象标签
                    DB::commit();
                } catch (\Exception $e){
                    DB::rollback();//事务回滚
                }
            }else{
                $this->setstatusCode(5000);
                $this->setMessage('暂无订单');
            }
        }
        else {
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * 评价参数
     */
    public function scoreType(){
        //评论类型
        $list = LoanerAttrModel::where(['pid'=>13])
            ->get(['id','attr_value']);
        //印象
        $focus = LoanerAttrModel::where(['pid'=>4])
            ->get(['id','attr_value']);
        if(count($list))
        {
            $arr['type'] = $list;
            $arr['focus'] = $focus;
            $this->setData($arr);
        }
        else {
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 删除订单
     */
    public function  deleteLoan(Request $request){
        $uid = $request->uid;
        $validator = Validator::make($request->all(), [
            'id' => 'required'//要删除的订单
        ]);
        if(!$validator->fails()) {
            if(strpos($request->id,',') !== false){
                $arr = explode(',',$request->id);
                foreach($arr as $item)
                {
                    if($item){
                        $exist = LoanModel::where(['user_id' => $uid, 'id' => $request->id])
                            ->first();
                        if (count($exist)) {
                            LoanModel::where(['user_id' => $uid, 'id' => $request->id])
                                ->update(['status' => 2]);
                        }
                    }
                }
            }else {
                $exist = LoanModel::where(['user_id' => $uid, 'id' => $request->id])
                    ->first();
                if (count($exist)) {
                    $update = LoanModel::where(['user_id' => $uid, 'id' => $request->id])
                        ->update(['status' => 2]);
                    if (!$update) {
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                } else {
                    $this->setstatusCode(5000);
                    $this->setMessage('暂无数据');
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
     * @param Request $request
     * @param $deviceid
     * @return mixed
     */
    public function setFavorite(Request $request){
        $uid = $request->uid;
        $validator = Validator::make($request->all(), [
            'id' => 'required',//要删除的订单:object_id
            'type' => 'required',//信贷经理1，文章2
            'action'=>'required'//add||cancel
        ]);
        if(!$validator->fails()) {
            if($request->action == 'add')
            {
                $exist = UserFavoriteModel::where(['type'=>$request->type,'object_id'=>$request->id,'user_id'=>$uid])->first();
                if(!count($exist))
                {
                    $res = UserFavoriteModel::create(['user_id'=>$uid,'type'=>$request->type,'object_id'=>$request->id,'create_time'=>time()]);
                    if(!$res){
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                }else{
                    $this->setstatusCode(4010);
                    $this->setMessage('重复操作');
                }
            }else {
                if(strpos($request->id,',')=== false)
                {
                    $exist = UserFavoriteModel::where(['type' => $request->type, 'object_id' => $request->id, 'user_id' => $uid])->first();
                    if (count($exist))
                    {
                        $res = UserFavoriteModel::where(['object_id' => $request->id])->delete();
                        if (!$res) {
                            $this->setstatusCode(500);
                            $this->setMessage('服务器错误');
                        }
                    } else {
                        $this->setstatusCode(5000);
                        $this->setMessage('暂无数据');
                    }
                }else{
                    $ids = explode(',',$request->id);
                    foreach($ids as $item)
                    {
                        if($item)
                        {
                            $exist =  UserFavoriteModel::where(['type' => $request->type, 'object_id' => $item, 'user_id' => $uid])->first();
                            if($exist){
                                UserFavoriteModel::where(['id' => $exist->id])->delete();
                            }
                        }
                    }
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
     * @param Request $request
     * @param $deviceid
     * @return mixed
     * 收藏列表
     */
    public function favoriteList(Request $request)
    {
        $uid = $request->uid;
        $validator = Validator::make($request->all(), [
            'type' => 'required',//信贷经理1，文章2
        ]);
        if(!$validator->fails()) {
            if($request->type == 1)
            {
                $list = UserFavoriteModel::where(['user_id' => $uid, 'type' => 1])
                    ->with(['loaner' => function ($query) {
                        $query->select(['max_loan', 'id', 'loanername as name', 'loanername_mobile as mobile', 'tag', 'header_img','max_loan','loan_number','all_number','attr_ids']);
                    }])
                    ->select(['id','object_id'])
                    ->orderBy('id','desc')
                    ->paginate($this->pageSize);
                if (count($list))
                {
                    $list = $list->toArray();
                    foreach ($list['data'] as &$item) {
                        if($item['loaner']['attr_ids'])
                        {
                            $tags = LoanerAttrModel::whereIn('id',explode(',',$item['loaner']['attr_ids']))
                                ->get()
                                ->pluck('attr_value','id');
                            $tags = implode(',',$tags->toArray());
                        }else{
                            $tags = null;
                        }
                        $item['loaner']['tags'] = $tags;
                    }
                    $this->setData($list);
                }else{
                    $this->setMessage('暂无数据');
                    $this->setstatusCode(5000);
                }
            }
            else
            {//文章收藏管理
                $list = UserFavoriteModel::where(['user_id' => $uid, 'type' => 2])
                    ->with(['article'=>function($query){
                        $query->select(['id','title','views','create_time','picture','introduce']);
                    }])
                    ->select(['id','object_id'])
                    ->orderBy('id','desc')
                    ->paginate($this->pageSize);
               if(count($list))
               {
                    $list = $list -> toArray();
                   foreach($list['data'] as &$item)
                   {
                       unset($item['id']);
                       $item['id'] = $item['article']['id'];
                       $item['title'] = $item['article']['title'];
                       $item['views'] = $item['article']['views'];
                       $item['picture'] = $item['article']['picture'];
                       $item['create_time'] = $item['article']['create_time'];
                       $item['introduce'] = $item['article']['introduce'];
                        unset($item['article']);
                   }
                   $this->setData($list);
               }else{
                   $this->setstatusCode(5000);
                   $this->setMessage('暂无数据');
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
     * @param $deviceid
     * 积分流水
     */
    public function pointList(Request $request){
        $uid = $request->uid;
        $list = IntegralListModel::where(['user_id'=>$uid])
            ->with(['type'=>function($query){
                $query->select(['id','integral_log as name']);
            }])
            ->select(['id','integral_id','number','create_time','description'])
            ->orderBy('id','desc')
            ->paginate($this->pageSize);
        if(count($list)){
            $list  = $list ->toArray();
            foreach($list['data'] as &$item)
            {
                $item['name'] = $item['type']['name'];
                unset($item['type']);
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
     * @return mixed
     *  积分规则
     */
    public function pointRules()
    {
        $list = IntegralModel::where(['status' => 1])->get(['integral_log as name', 'integral_description as describe', 'integral_number as number', 'create_time']);
        if (count($list)) {
            $this->setData($list);
        } else {
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }
    /**
     * @param Request $request
     * @param $deviceid
     * @return mixed
     * 用户反馈
     */
    public function feedback(Request $request){
        $uid = $request->uid;
        $validator = Validator::make($request->all(), [
            'comment' => 'required|min:5',//信贷经理1，文章2
        ]);
        if(!$validator->fails()) {
            $res = FeedbackModel::create(['user_id'=>$uid,'content'=>$request->comment,'create_time'=>time()]);
            if(!$res){
                $this->setstatusCode(500);
                $this->setMessage('服务器错误');
            }
        }
        else {
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 提交审核资料
     */
    public function document(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:2',//真实名字
            'front_cert' =>'required',//正面
            'back_cert' =>'required',//背面
            'province_id'=>'required',//省份id
            'city_id'=>'required',//城市id
            'district_id'=>'required',//地区id
            'number'=>'required'//身份证号
        ]);
        if(!$validator->fails()) {
            $is_auth = UserAuthModel::where(['user_id'=>$request->uid])
                ->first(['id','is_pass']);
            if(count($is_auth) && $is_auth->is_pass !==4)
            {
                $this->setstatusCode(4010);
                $this->setMessage('重复提交');
            }else {
                if ($is_auth == null ) {
                    DB::beginTransaction();
                    try {
                        UserAuthModel::create(['true_name' => $request->name, 'front_identity' => $request->front_cert, 'back_identity' => $request->back_cert, 'user_id' => $request->uid, 'create_time' => time(), 'is_pass' => 2, 'province_id' => $request->province_id, 'city_id' => $request->city_id, 'region_id' => $request->district_id,'identity_number'=>$request->number,'type'=>1]);
                        UserModel::where(['id' => $request->uid])
                            ->update(['is_auth' => 2]);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollback();//事务回滚
                    }
                }
                else{
                    DB::beginTransaction();
                    try
                    {
                        UserAuthModel::where(['id'=>$is_auth->id])
                            ->update(['true_name' => $request->name, 'front_identity' => $request->front_cert, 'back_identity' => $request->back_cert, 'user_id' => $request->uid, 'update_time' => time(), 'is_pass' => 2, 'province_id' => $request->province_id, 'city_id' => $request->city_id, 'region_id' => $request->district_id,'identity_number'=>$request->number,'type'=>1]);
                        UserModel::where(['id' => $request->uid])
                            ->update(['is_auth' => 2]);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollback();//事务回滚
                    }
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
     * 地区信息表
     */
    public function region(){
        if(Redis::exists('basic_region')){
            $this->setData(json_decode(Redis::get('basic_region'),true));
        }else {
            $list = RegionModel::where(['pid' => 1])
                ->with(['city' => function ($query) {
                    $query->with(['district'=>function($query){
                        $query->select(['id','pid','name']);
                    }])->select(['name','id','pid']);
                }])
                ->get(['name','id','pid']);
            Redis::set('basic_region',json_encode($list,JSON_UNESCAPED_UNICODE));
            $this->setData($list);
        }
        return $this->result();
    }

    /**
     * 认证成功资料
     */
    public  function authDocument(Request $request){
        $is_auth = UserAuthModel::where(['user_id'=>$request->uid])
            ->with('province','city','district')
            ->first(['front_identity','back_identity','true_name','address','is_pass','province_id','city_id','region_id']);
        if(count($is_auth))
        {
            $address = '';
            if($is_auth->province) $address =$is_auth->province['name'];
            if($is_auth->city) $address .= ','. $is_auth->city['name'];
            if($is_auth->district) $address .= ','. $is_auth->district['name'];
            $is_auth->address = trim($address,',');
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
            $loaner = LoanerModel::find($request->to_uid);
            $exist = ReportModel::where(['to_uid'=>$loaner->user_id,'from_uid'=>$request->uid,'type'=>1,'loan_id'=>$request->loan_id])
                ->first();
            if(!count($exist)) {
                DB::beginTransaction();
                try {
                    $res = ReportModel::create(['create_time' => time(), 'to_uid' => $loaner->user_id, 'from_uid' => $request->uid, 'report_reason' => $request->comment, 'type' => 1, 'loan_id' => $request->loan_id]);
                    $from = UserModel::find($request->uid, ['username']);
                    $to = UserModel::find($loaner->user_id, ['username']);
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
    //TODO::重新认证

    /**
     * @param $deviceid
     * @return mixed
     * 我的问题
     */
    public function myQuestion(Request $request){
        $list = QuestionModel::where(['user_id'=>$request->uid,'is_pass'=>2,'status'=>1])
            ->select(['title','create_time','id'])
            ->paginate($this->pageSize);
        if(count($list))
        {
           $list = $list->toArray();
            foreach($list['data'] as &$item)
            {
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
     * @param $deviceid
     * 我的答案
     * //todo::分组问题
     */
    public function  myAnswer(Request $request){
        $uid = $this->checkUid($request->header('deviceid'));
        $list = AnswerModel::where(['user_id'=>$uid])
            ->with(['question'=>function($query){
                $query->select(['id','title']);
            }])
            ->select(['id','question_id','content','create_time'])
            ->paginate($this->pageSize);
        if(count($list)){
            $list = $list->toArray();
            foreach($list['data'] as &$item)
            {
                $item['question'] = $item['question']['title'];
            }
            $this->setData($list);
        }else{
            $this->setstatusCode(5000);
            $this->setMessage('暂无数据');
        }
        return $this->result();
    }

    /**
     * @param $deviceid
     * 删除问题
     */
    public function deleteQuestion(Request $request){
        $uid = $this->checkUid($request->header('deviceid'));
        $validator = Validator::make($request->all(), [
            'id' => 'required',//问题id
            'type' => 'required'//问题：1|答案：2
        ]);
        if(!$validator->fails()) {
            if($request->type == 1) {
                $exist = QuestionModel::where(['id' => $request->id, 'user_id' => $uid])
                    ->first();
                if (count($exist)) {
                    $res = QuestionModel::where(['id' => $request->id])
                        ->update(['status' => 2]);
                    if (!$res) {
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                } else {
                    $this->setstatusCode(5000);
                    $this->setMessage('暂无数据');
                }
            }else{
                $exist = AnswerModel::where(['user_id'=>$uid,'id'=>$request->id])
                    ->first();
                if (count($exist))
                {
                    $res = AnswerModel::where(['id' => $request->id])
                        ->update(['is_display' => 2]);
                    if (!$res) {
                        $this->setstatusCode(500);
                        $this->setMessage('服务器错误');
                    }
                } else {
                    $this->setstatusCode(5000);
                    $this->setMessage('暂无数据');
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
     * @param Request $request
     * @return mixed
     * 头像修改
     */
    public function avatar(Request $request)
    {
        $uid = $this->checkUid($request->header('deviceid'));
        $validator = Validator::make($request->all(), [
            'url' => 'required',//头像地址
        ]);
        if(!$validator->fails()) {
            $res = UserModel::where(['id'=>$uid])
                ->update(['header_img'=>$request->url]);
            if(!$res)
            {
                $this->setstatusCode(500);
                $this->setMessage('系统错误');
            }
        }else{
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * 信息提醒
     */
    public function notice(Request $request,$type)
    {
        $uid = $request->uid;
        $list = NoticeModel::where(['to_uid'=>$uid,'type'=>$type])
            ->orderBy('id','desc')
            ->paginate($this->pageSize,['id','from_uid','to_uid','title','content','type','create_time','is_success']);
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
     * 消息提醒数目
     */
    public function noticeNumber(Request $request){
        $uid = $request->uid;
        $count = NoticeModel::where(['to_uid'=>$uid,'status'=>1])
            ->count();
        if($count)
        {
            $this->setData(['no_read'=>$count]);
        }
        else{
            $this->setData(['no_read'=>0]);
        }
        return $this->result();
    }
    /**
     * 已读状态变更
     */
    public function setRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',//信贷经理1，文章2
//            'id' => 'required',//ids:
        ]);
        if (!$validator->fails()) {
            $ids = explode(',', $request->id);
            if (count($ids))
            {
                if(in_array($request->type,[1,2]))
                {
                    NoticeModel::where(['to_uid' => $request->uid, 'type' => $request->type])
                        ->update(['status' => 2, 'update_time' => time()]);
                }
            }
        }
        else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }
     /**
     * 城市字母排序
     */
    public function basicCity()
    {
        if(Redis::exists('basic_region_order')){
            $this->setData(json_decode(Redis::get('basic_region_order'),true));
        }else {
            $all = RegionModel::where(['type'=>2])->get(['id','name','first']);
            $arr = [];
            $all = $all->toArray();
            foreach($all as $item)
            {
                $arr[$item['first']][] = $item;
            }
            Redis::set('basic_region_order',json_encode($arr,JSON_UNESCAPED_UNICODE));
            $this->setData($arr);
        }
        return $this->result();
    }
}
