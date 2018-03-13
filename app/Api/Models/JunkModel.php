<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class JunkModel extends Model
{
    protected $table = 'junk_loan';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','loan_id','loaner_id','loaner_name','address','loan_type','customer_info','create_time','update_time','status','pid','first_time','source_table','source_id','is_check','apply_information','apply_number','time_limit','loan_type','region_id','age','name','name','price','mobile','description','is_vip','expire_time','province_id','city_id','job_information'];

    public function source(){
        return $this->hasOne(LoanModel::class,'id','source_id');
    }

    public function loanType(){
        return $this->hasOne(LoanerAttrModel::class,'id','loan_type');
    }

    public function limit(){
        return $this->hasOne(LoanerAttrModel::class,'id','time_limit');
    }
    public function region(){
        return $this->hasOne(RegionModel::class,'id','city_id');
    }
}
