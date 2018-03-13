<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
class UserModel extends Authenticatable
{
    protected $table = 'user';
    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','username','password','mobile','sex','header_img','authplatform','open_id','union_id','is_disable','is_auth','integral','create_time','update_time','sign_from','last_login_time','last_login_ip','is_loan','apply_loan_num','is_stop','type','token','group_id','last_deviceid'
    ];

    public function auth(){
        return $this->hasOne(UserAuthModel::class,'user_id','id');
    }

    public function shop()
    {
        return $this->hasOne(ShopModel::class,'user_id','id');
    }

    public function loaner(){
        return $this->hasOne(LoanerModel::class,'user_id','id');
    }
}
