<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleModel extends Model
{
    const PAGESIZE = 10;
    protected $table = 'article';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','title','content','author','views','cate_id','is_display','recommend','create_time','update_time','picture','source','user_id','introduce','link'
    ];

    public function category(){
        return $this->hasOne(ArticleCategoryModel::class,'id','cate_id');
    }

    public function user(){
        return $this->belongsTo(UserModel::class,'user_id','id');
    }
}
