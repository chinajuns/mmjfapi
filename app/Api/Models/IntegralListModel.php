<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class IntegralListModel extends Model
{
    protected $table = 'integral_list';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id','integral_id','number','total','create_time','update_time','description','desc','status'
    ];

    public function type(){
        return $this->hasOne(IntegralModel::class,'id','integral_id');
    }
}
