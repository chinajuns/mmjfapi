<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class LoanerAttrModel extends Model
{
    protected $table = 'loaner_attr';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','attr_key','attr_value','create_time','pid','type','function_name','sort','type'
    ];

    public function loaner(){
        return $this->belongsTo(LoanerModel::class,'loaner_id','id');
    }

    public function values(){
        return $this->hasMany(LoanerAttrModel::class,'pid','id');
    }

    public function parent(){
        return $this->belongsTo(LoanerAttrModel::class,'pid','id');
    }
}
