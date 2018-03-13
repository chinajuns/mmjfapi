<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class LoanExamineModel extends Model
{
    protected $table = 'loan_examine';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','loan_id','loaner_id','process','create_time','status','describe'
    ];

    public function process(){
        return $this->belongsTo(LoanerAttrModel::class,'process','id');
    }

    public function loaner(){
        return $this->belongsTo(LoanerModel::class,'loaner_id','id');
    }


}
