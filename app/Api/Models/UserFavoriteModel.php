<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class UserFavoriteModel extends Model
{
    protected $table = 'user_favorite';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','user_id','type','status','create_time','describe','object_id'
    ];

    public function loaner()
    {
        return $this->hasOne(LoanerModel::class,'id','object_id');
    }

    public function article()
    {
        return $this->hasOne(ArticleModel::class,'id','object_id');
    }
}
