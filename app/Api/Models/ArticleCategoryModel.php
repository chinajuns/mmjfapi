<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleCategoryModel extends Model
{
    protected $table = 'article_category';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','cate_name','is_display','create_time','pid'
    ];

    public function child(){
        return $this->hasMany(ArticleCategoryModel::class,'pid','id');
    }

    public function parent(){
        return $this->belongsTo(ArticleCategoryModel::class,'pid','id');
    }
}
