<?php

namespace App\Api\v1\Mobile\Client;

use App\Api\Models\ArticleCategoryModel;
use App\Api\Models\ArticleModel;
use App\Api\Models\LoanerAttrModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\LoanEvaluateModel;
use App\Api\Models\LoanModel;
use App\Api\Models\ReportModel;
use App\Api\Models\ShopAgentModel;
use App\Api\Models\ShopModel;
use App\Api\Models\UserFavoriteModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use App\Api\v1\Controllers\LoanerController;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WebController extends BaseController
{
    /**
     * @param Request $request
     * 店铺详情
     */
    public function showHeader($deviceid,$token, $id){
        //验证token
        if ($this->checkToken($token, $deviceid) !== true)
        {
            return $this->checkToken($token, $deviceid);
        }
        $uid = $this->checkUid($deviceid);
//        $info = UserModel::where(['is_auth'=>2,'is_disable'=>1])//正式
        //检查店铺
        $hasShop = ShopModel::where(['loaner_id'=>$id,'check_result'=>2,'status'=>1])
            ->first();
//        dd($hasShop);
        if($hasShop) {
            $info = ShopModel::with(['loaner'=>function($query){
                $query->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag','loan_number','score','loan_day','user_id','all_number','is_auth','attr_ids']);
            }])
                ->find($hasShop->id,['id','loaner_id','mechanism_name','introduce','special','service_object']);
            if (count($info))
            {
                //代理产品数量
//            $info->agent = ShopAgentModel::where(['id'=>$info->shop_id,'status'=>1])->count();
                //申请人数
//            $info->apply_number = LoanModel::where(['loaner_id'=>$info->loaner_id])->count();
                //是否收藏
                if($uid) {
                    $info->is_favorite = UserFavoriteModel::where(['user_id' => $this->checkUid($uid), 'type' => 1, 'object_id' => $info->shop['loaner_id']])->count() ? '1' : '0';
                } else {
                    $info->is_favorite = '0';
                }
                if($info->loaner['attr_ids'])
                {
                    $tags = LoanerAttrModel::whereIn('id',explode(',',$info->loaner['attr_ids']))
                        ->get()
                        ->pluck('attr_value','id');
                    $tags = implode(',',$tags->toArray());
                }else{
                    $tags = null;
                }
                $info->loaner['tags'] = $tags;
                $this->setData($info);
            } else {
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
     * @param $uid
     * 代理产品列表
     */
    public function productList($deviceid,$token,$id)
    {
        //验证token
        if ($this->checkToken($token, $deviceid) !== true)
        {
            return $this->checkToken($token, $deviceid);
        }
        $loaner = LoanerModel::find($id);
        if(count($loaner))
        {
            $list = ShopAgentModel::where(['loaner_id'=>$loaner->id])
                ->with(['product'=>function($query){
                    $query->select(['id','time_limit','rate','title','loan_number','cate_id']);
                },'productOther'=>function($query){
                    $query->select(['title','rate','loan_number','time_limit','id','cate_id']);
                }])
                ->get(['create_time','loaner_id','sys_pro_id','third_pro_id','id','apply_peoples']);
            $average = $this->average($loaner->id);
            $evaluate = $this->evaluate($loaner->id);
            if(count($list))
            {
                $list = $list->toArray();
                foreach($list as &$item)
                {
                    if($item['product'])
                    {
                        $item['time_limit'] = str_replace(',','-',$item['product']['time_limit']).'个月';
                        $item['rate'] = str_replace(',','-',$item['product']['rate']).'%';
                        $item['loan_number'] = str_replace(',','-',$item['product']['loan_number']).'万';
                        $item['title'] = str_replace(',','-',$item['product']['title']);
                        $item['pro_id'] = str_replace(',','-',$item['product']['id']);
                        $type = LoanerAttrModel::find($item['product']['cate_id'],['attr_value']);
                        $item['type'] = $type ? $type->attr_value :'';
                    }
                    else
                    {
                        $item['time_limit'] = str_replace(',','-',$item['product_other']['time_limit']);
                        $item['rate'] = str_replace(',','-',$item['product_other']['rate']).'%';
                        $item['loan_number'] = str_replace(',','-',$item['product_other']['loan_number']);
                        $item['title'] = str_replace(',','-',$item['product_other']['title']);
                        $item['pro_id'] = str_replace(',','-',$item['product_other']['id']);
                        $type = LoanerAttrModel::find($item['product_other']['cate_id'],['attr_value']);
                        $item['type'] = $type ? $type->attr_value :'';
                    }
                    $item['platform'] = $item['sys_pro_id'] ? 'system':'third';
                    unset($item['product_other']);
                    unset($item['product']);
                    unset($item['sys_pro_id']);
                    unset($item['third_pro_id']);
                }
                $this->setData(['product'=>$list,'evaluate'=>['header'=>$average,'list'=>$evaluate]]);
            }else{
                $this->setData(['product'=>[],'evaluate'=>['header'=>$average,'list'=>$evaluate]]);
            }
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
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
                } else {
                    $arr['tag'] = null;
                }
            }
            else{
                $arr['tag'] = null;
            }
        }else{
           $arr = [];
        }
        return $arr;
    }

    /**
     * @param $id
     * 评价列表
     */
    public function evaluate($id){
            $ids = LoanModel::where(['is_comment'=>2,'loaner_id'=>$id])
                ->limit(10)
                ->get(['id'])
                ->pluck('id');
            $arr = [];
            if(count($ids))
            {
                $list = LoanEvaluateModel::whereIn('loan_id', $ids->toArray())
                    ->with(['user'=>function ($query) {
                        $query->select(['id', 'username']);
                    }])
                    ->orderBy('id', 'desc')
                    ->select(['loan_id','user_id','describe','create_time','score_avg','focus'])
                    ->get();
                if (count($list)) {
                    $list = $list->toArray();
                    foreach ($list as &$item)
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
                        $item['score_avg'] = (double)$score;
                        unset($item['user']);
                    }
                    $arr = $list;
                }
            }
        return $arr;
    }
    /**
     * @param $id
     * 咨询详情
     */
    public function show($deviceid,$token,$id){
        //验证token
        if ($this->checkToken($token, $deviceid) !== true)
        {
            return $this->checkToken($token, $deviceid);
        }
        $show = ArticleModel::select('id','title','content','author','views','create_time','picture','source','cate_id','user_id','introduce','link')
            ->find($id);
        if(count($show))
        {
            ArticleModel::where(['id'=>$id])
                ->increment('views');
            $category = ArticleCategoryModel::with(['parent'=>function($query){
                $query->select(['cate_name','id','pid']);
            }])->find($show->cate_id);
            $cate['cate_name'] = $category->cate_name;
            if($category->parent){
                $cate['parent_cate_name'] = $category->parent->cate_name;
            }
            else
            {
                $cate['parent_cate_name'] = '';
            }
            $show->content = str_replace('_IMG_',$this->imgUrl,$show->content);
            $show->picture = $this->imgUrl.$show->picture;
            $show->category = $cate ? $cate : null;;
            //$show->username = $show->user->username;
            $uid = $this->checkUid($deviceid);
            if($uid)
            {
                $show->is_favorite = UserFavoriteModel::where(['type'=>2,'user_id'=>$uid,'object_id'=>$id])
                    ->count() ? '1':'0';
            } else {
                $show->is_favorite = '0';
            }
            //
            $list = ArticleModel::where(['is_display'=>1])
                ->select('title','picture','id','create_time','views','introduce')
                ->orderBy('recommend','desc')
                ->limit(2)
                ->get();
            $this->setData(['detail'=>$show,'list'=>$list]);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * @param Request $request
     * @param $deviceid
     * @param $token
     * @return bool
     * 计算结果以后推荐信贷经理
     */
    public function recommend(Request $request,$deviceid,$token)
    {
        //验证token
        if ($this->checkToken($token, $deviceid) !== true)
        {
            return $this->checkToken($token, $deviceid);
        }
        if($request->number) {
            $list = LoanerModel::where(['is_display' => 1])
                ->select(['id', 'loanername as name', 'header_img', 'max_loan', 'tag', 'loan_day', 'loan_number', 'score', 'all_number', 'is_auth', 'attr_ids'])
                ->orderBy('id', 'desc')->limit(2)->get();
            if (count($list)) {
                foreach ($list as &$item)
                {
                    if ($item->attr_ids)
                    {
                        $tags = LoanerAttrModel::whereIn('id', explode(',', $item->attr_ids))
                            ->get()
                            ->pluck('attr_value');
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
        }
        else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }

    /**
     * @param $deviceid
     * @param $token
     * @param $id
     * @return bool
     * 分享：经理人信息
     */
    public function info($deviceid,$token,$id){
        //验证token
        if ($this->checkToken($token, $deviceid) !== true)
        {
            return $this->checkToken($token, $deviceid);
        }
        $exist = LoanerModel::find($id,['loanername','header_img']);
        if($exist)
        {
            $this->setData($exist);
        }
        else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }
}