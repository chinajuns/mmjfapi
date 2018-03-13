<?php

namespace App\Api\v1\Mobile\Client;

use App\Api\Models\FeedbackModel;
use App\Api\Models\IntegralListModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanExamineModel;
use App\Api\Models\LoanModel;
use App\Api\Models\UserAuthModel;
use App\Api\Models\UserFavoriteModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class MemberController extends BaseController
{
    public function point(Request $request){
        $info = UserModel::find($request->uid,['integral']);
        $dayIn = IntegralListModel::where([['create_time','>',strtotime(date('ymd',time()))],['number','>',0]])
                ->sum('number');
        $monthIn = IntegralListModel::where([['user_id','=',$request->uid],['create_time','>',strtotime(date('Y-m-01',time()))],['number','>',0]])
            ->sum('number');
        $list = IntegralListModel::where(['user_id'=>$request->uid])
            ->with(['type'=>function($query){
                $query->select(['id','integral_log']);
            }])
            ->orderBy('id','desc')
            ->limit($this->pageSize)
            ->get(['id','number','integral_id','create_time']);
        if(count($list))
        {
            foreach ($list as &$item)
            {
                $item->type_name = $item->type['integral_log'];
                unset($item->type);
            }
        }
        $this->setData(['total'=>$info->integral,'day'=>$dayIn,'month'=>$monthIn,'list'=>$list?$list:null]);
        return $this->result();
    }

    /**
     * @param Request $request
     * @return mixed
     * 积分流水
     */
    public function pointList(Request $request)
    {
        $list = IntegralListModel::where(['user_id'=>$request->uid])
            ->with(['type'=>function($query){
                $query->select(['id','integral_log']);
            }])
            ->orderBy('id','desc')
            ->paginate($this->pageSize,['id','number','integral_id','create_time']);
        if(count($list))
        {
            $list = $list->toArray();
            foreach ($list['data'] as &$item)
            {
                $item['type_name'] = $item['type']['integral_log'];
                unset($item->type);
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
                        ->update(['true_name' => $request->name, 'front_identity' => $request->front_cert, 'back_identity' => $request->back_cert, 'user_id' => $request->uid, 'update_time' => time(), 'is_pass' => 2, 'province_id' => $request->province_id, 'city_id' => $request->city_id, 'region_id' => $request->district_id]);
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
     * 认证成功资料
     */
    public  function authDocument(Request $request){
        $is_auth = UserAuthModel::where(['user_id'=>$request->uid])
            ->with('province','city','district')
            ->first(['front_identity','back_identity','true_name','address','is_pass','province_id','city_id','region_id','identity_number']);
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
            $this->setData(['is_pass'=>1]);
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
            if($request->type == 1) {
                $list = UserFavoriteModel::where(['user_id' => $uid, 'type' => 1])
                    ->with(['loaner' => function ($query) {
                        $query->select(['max_loan', 'id', 'loanername as name', 'tag', 'header_img','loan_day','score','is_auth']);
                    }])
                    ->select(['id','object_id'])
                    ->paginate($this->pageSize);
                if (count($list)) {
                    $list = $list->toArray();
                    foreach ($list['data'] as &$item) {
                        if($item['loaner'])
                        {
                            $item['favorite_id'] = $item['id'];
                            $item['is_focus'] = '0';
                            $item['is_hot'] = '0';
                            $item = array_merge($item, $item['loaner']);
                            unset($item['loaner']);
                            unset($item['id']);
                            $item['id'] = $item['favorite_id'];
                            unset($item['loaner']);
                            unset($item['favorite_id']);
                        }
                    }
                    $this->setData($list);
                }else{
                    $this->setstatusCode(5000);
                    $this->setMessage('暂无数据');
                }
            }
            else
            {//文章收藏管理
                $list = UserFavoriteModel::where(['user_id' => $uid, 'type' => 2])
                    ->with(['article'=>function($query){
                        $query->select(['id','title','views','create_time','picture','introduce']);
                    }])
                    ->select(['id','object_id'])
                    ->paginate($this->pageSize);
                if(count($list))
                {
                    $list = $list -> toArray();
                    foreach($list['data'] as &$item)
                    {
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
     * @param Request $request
     * @param $deviceid
     * @return mixed
     */
    public function setFavorite(Request $request){
        $uid = $request->uid;
        $validator = Validator::make($request->all(), [
            'id' => 'required',//
            'type' => 'required',//信贷经理1，文章2
            'action'=>'required'//add||cancel
        ]);
        if(!$validator->fails()) {
            if($request->id) {
                if ($request->action == 'add') {
                    $exist = UserFavoriteModel::where(['type' => $request->type, 'object_id' => $request->id, 'user_id' => $uid])->first();
                    if (!count($exist)) {
                        $res = UserFavoriteModel::create(['user_id' => $uid, 'type' => $request->type, 'object_id' => $request->id, 'create_time' => time()]);
                        if (!$res) {
                            $this->setstatusCode(500);
                            $this->setMessage('服务器错误');
                        } else {
                            $this->setMessage('收藏成功');
                        }
                    } else {
                        $this->setstatusCode(4010);
                        $this->setMessage('重复操作');
                    }
                } else {
                    $exist = UserFavoriteModel::where(['type' => $request->type, 'object_id' => $request->id, 'user_id' => $uid])->first();
                    if (count($exist)) {
                        $res = UserFavoriteModel::where(['object_id' => $request->id])->delete();
                        if (!$res) {
                            $this->setstatusCode(500);
                            $this->setMessage('服务器错误');
                        } else {
                            $this->setMessage('取消成功');
                        }
                    } else {
                        $this->setstatusCode(5000);
                        $this->setMessage('暂无数据');
                    }
                }
            }
            else{
                $this->setstatusCode(4002);
                $this->setMessage('收藏id不能为0');
            }
        }
        else {
            $this->setstatusCode(4002);
            $this->setMessage('参数错误');
        }
        return $this->result();
    }

    /**
     * 贷款订单
     */
    public function history(Request $request){
        $uid = $request->uid;
        if($request->type == 1) {//办理中
            $list = LoanModel::with(['loaner' => function ($query)
            {
                $query->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'create_time', 'header_img', 'max_loan', 'all_number', 'tag', 'is_auth','attr_ids']);
            }])
                ->where([['user_id','=', $uid], ['status','=', 1],['c_comment','=',1],['process','<',37]])
                ->paginate($this->pageSize, ['loan_account', 'id', 'user_id', 'status', 'process', 'loaner_id', 'c_comment as is_comment']);
        }elseif($request->type == 2)//待评价
        {
            $list = LoanModel::with(['loaner' => function ($query) {
                $query->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'create_time', 'header_img', 'max_loan', 'all_number', 'tag', 'is_auth','attr_ids']);
            }])
                ->where([['user_id','=', $uid], ['status','=', 1],['c_comment','=',1],['process','>=',37]])
                ->paginate($this->pageSize, ['loan_account', 'id', 'user_id', 'status', 'process', 'loaner_id', 'c_comment as is_comment']);
        }elseif($request->type == 3){//贷款记录
            $list = LoanModel::with(['loaner' => function ($query) {
                $query->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'create_time', 'header_img', 'max_loan', 'all_number', 'tag', 'is_auth','attr_ids']);
            }])
                ->where([['user_id','=', $uid], ['status','=', 1],['process','>=',37]])
                ->paginate($this->pageSize, ['loan_account', 'id', 'user_id', 'status', 'process', 'loaner_id', 'c_comment as is_comment']);

        } else {//全部
            $list = LoanModel::with(['loaner' => function ($query) {
                $query->select(['id', 'loanername as name', 'loanername_mobile as mobile', 'create_time', 'header_img', 'max_loan', 'all_number', 'tag', 'is_auth','attr_ids']);
            }])
                ->where(['user_id' => $uid, 'status' => 1])
                ->paginate($this->pageSize, ['loan_account', 'id', 'user_id', 'status', 'process', 'loaner_id', 'c_comment as is_comment']);
        }
        if (count($list)) {
            $list = $list->toArray();
            foreach ($list['data'] as &$item) {
                //信贷经理信息
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
                /*贷款进度*/
                $item['all_process'] = LoanerAttrModel::where([['pid', '=', 10], ['id', '<>', 38]])
                    ->get(['id', 'attr_value']);
//                    dd($process);
                $processing = LoanExamineModel::where(['loan_id' => $item['id']])
                    ->with(['process' => function ($query) {
                        $query->select(['id', 'attr_value', 'pid']);
                    }, 'loaner' => function ($query) {
                        $query->select(['id', 'loanername as name', 'loanername_mobile as mobile']);
                    }])
                    ->get(['loan_id', 'id', 'process', 'describe', 'create_time', 'loaner_id']);
                if ($processing) {
                    $processing = $processing->toArray();
                    foreach ($processing as &$val) {
                        $val['value'] = $val['process']['attr_value'];
                        $val['id'] = $val['process']['id'];
                        $val['loanername'] = $val['loaner']['name'];
                        $val['mobile'] = $val['loaner']['mobile'];
                        unset($val['process']);
                        unset($val['loaner']);
                        unset($val['loan_id']);
                    }
                    $item['processing'] = $processing;
                } else {
                    $item['processing'] = null;
                }
            }
            $this->setData($list);
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }
}
