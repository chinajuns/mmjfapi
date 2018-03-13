<?php

namespace App\Api\v1\Mobile\Client;

use App\Api\Models\AdvertModel;
use App\Api\Models\ArticleCategoryModel;
use App\Api\Models\ArticleModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ArticleController extends BaseController
{
    /**
     * @return mixed
     * 咨询首页
     */
    public function index()
    {
        $adver = $this->advert();
        foreach($adver['config'] as &$item)
        {
            $list = ArticleModel::where(['is_display' => 1,'cate_id'=>$item['id']])
                ->select('title', 'picture', 'id', 'create_time', 'views', 'introduce')
                ->orderBy('recommend', 'desc')
                ->limit(3)
                ->get();
            $item['list'] = $list;
        }
        if(!count($list))
        {
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }else{
            $config = $this->advert()['config'];
            $this->setData(['config'=>$config,'adver'=>$adver['adver'],'list'=>$adver['config']]);
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
            $where = ['is_display'=>1,'cate_id'=>$cate];
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
            ->paginate($this->pageSize);
        if(!count($list)){
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }else{
            $this->setData($list);
        }
        return $this->result();
    }

    /**
     * 咨询顶部广告
     */
    public function advert(){
        $top =  AdvertModel::where(['is_display'=>1,'advert_config'=>14])
            ->get(['image','link']);
        $middle = AdvertModel::where(['is_display'=>1,'advert_config'=>16])
            ->get(['image','link']);
        $config = ArticleCategoryModel::where(['pid'=>0])
            ->get(['id','cate_name']);
        return ['adver'=>['top'=>$top,'middle'=>$middle],'config'=>$config];
    }

    /**
     * @param $id
     * 分类列表
     */
    public function articleList($id)
    {
        $list = ArticleModel::where(['is_display' => 1,'cate_id'=>$id])
            ->select('title', 'picture', 'id', 'create_time', 'views', 'introduce')
            ->orderBy('recommend', 'desc')
            ->paginate($this->pageSize);
        if(count($list))
        {
            $this->setData($list);
        }else{
            $this->setMessage('暂无数据');
            $this->setData(5000);
        }
        return $this->result();
    }

}
