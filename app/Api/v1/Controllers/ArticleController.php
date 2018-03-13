<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\ArticleCategoryModel;
use App\Api\Models\ArticleModel;
use App\Api\Models\UserFavoriteModel;
use Illuminate\Http\Request;

class ArticleController extends BaseController
{
    /**
     * @return mixed
     * 咨询首页
     */
    public function index(){
        $list = ArticleModel::where(['is_display'=>1])
            ->select('title','picture','id','create_time','views','introduce')
            ->orderBy('recommend','desc')
            ->orderBy('id','desc')
            ->paginate(ArticleModel::PAGESIZE);
        if(!count($list)){
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }else{
            $this->setData($list);
        }
        return $this->result($list);
    }

    public function category(){
        $category = ArticleCategoryModel::where(['pid'=>0,'is_display'=>1])
            ->with(['child'=>function($query)
            {
                $query->where(['is_display'=>1])->select(['cate_name','id','pid']);
            }])
            ->get();
        if(!count($category)){
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }else{
            $this->setData($category);
        }
        return $this->result();
    }
    /**
     * @param Request $request
     * 筛选
     */
    public function search(Request $request){
        $sort = $request->sort;//排序
        $cate = $request->cate_id;//分类id
        $by = $request->by ? $request->by : 'desc';//asc || desc
        if($cate){
            $is_child = ArticleCategoryModel::find($cate,['type']);
            if($is_child->type == 1) {
                $where = ['is_display' => 1, 'cate_id' => $cate];
            }else{
                $where = ['is_display' => 1, 'child_id' => $cate];
            }
        }else{
            $where = ['is_display'=>1];
        }
        if($sort == 'time'){
            $order = 'create_time';
        }elseif($sort == 'views')
        {
            $order = 'views';
        }else{
            $order = 'id';
        }
        $list = ArticleModel::where($where)
            ->select('title','picture','id','create_time','views','introduce')
            ->orderBy($order,$by)
            ->paginate(ArticleModel::PAGESIZE);
        if(!count($list)){
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }else{
            $this->setData($list);
        }
        return $this->result();
    }

    /**
     * @param $id
     * 咨询详情
     */
    public function show(Request $request ,$id){
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
           $uid = $this->checkUid($request->header('deviceid'));
           if($uid)
           {
               $show->is_favorite = UserFavoriteModel::where(['type'=>2,'user_id'=>$uid,'object_id'=>$id])
                   ->count() ? '1':'0';
           } else {
               $show->is_favorite = '0';
           }
           $this->setData($show);
       }else{
           $this->setMessage('暂无数据');
           $this->setstatusCode(5000);
       }
        return $this->result();
    }
}
