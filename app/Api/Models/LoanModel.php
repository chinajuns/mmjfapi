<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class LoanModel extends Model
{
    protected $table = 'loan';
    const PAGESIZE = 10;
    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','loan_account','user_id','is_comment','product_id','receive_loaner_id','status','create_time','update_time','discard_time','loan_number','apply_number','receive_time','loan_day','loan_time','loaner_id','score','process','is_given','is_check','check_result','type','region_id','apply_information','name','age','time_limit','loan_type','mobile','is_vip','job_information','is_marry','province_id','city_id','assets_information','junk_id'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 进度审核
     */
    public function examine(){
        return $this->hasMany(LoanExamineModel::class,'loan_id','id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 信贷经理
     */
    public function loaner(){
        return $this->belongsTo(LoanerModel::class,'loaner_id','id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     * 评分
     */
    public function score(){
        return $this->hasOne(LoanEvaluateModel::class,'loan_id','id');
    }

    /**
     * 点评
     */
    public function evaluate()
    {
        return $this->hasOne(LoanEvaluateModel::class,'loan_id','id');
    }

    public function user(){
        return $this->belongsTo(UserModel::class,'user_id','id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     * 贷款产品
     */
    public function type(){
        return $this->hasOne(LoanerAttrModel::class,'id','loan_type');
    }

    public function process(){
        return $this->hasOne(LoanerAttrModel::class,'id','process');
    }

    public function timeLimit(){
        return $this->hasOne(LoanerAttrModel::class,'id','time_limit');
    }

    public function region(){
        return $this->hasOne(RegionModel::class,'id','city_id');
    }
}
