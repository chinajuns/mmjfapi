<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class LoanerModel extends Model
{
    protected $table = 'loaner';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','loanername','loanername_mobile','loaner_lat','loaner_lng','attr_ids','is_display','create_time','update_time','header_img','region_id','balance','max_loan','user_id','tag','loan_number','score','loan_day','all_number','is_auth','attr_ids','province_id','city_id','proxy_number'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     * 关联用户信息表
     */
    public function user(){
        return $this->hasOne(UserModel::class,'id','user_id');
    }


    public function order()
    {
        return $this->hasMany(LoanModel::class, 'loaner_id', 'id');

    }

    public function auth(){
        return $this->hasOne(UserAuthModel::class,'user_id','user_id');

    }

    public function shop(){
        return $this->hasOne(ShopModel::class,'loaner_id','id');
    }



}
