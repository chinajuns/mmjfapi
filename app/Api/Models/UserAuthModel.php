<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\Presets\React;

class UserAuthModel extends Model
{
    protected $table = 'user_auth';
    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','user_id','true_name','identity_number','front_identity','back_identity','is_check','is_pass','create_time','update_time','check_time','reply_content','admin_id','admin_name','work_card','card','contract_page','logo_personal','address','service_city','mechanism','mechanism_type','province_id','city_id','region_id','department','photo','type'
    ];

    public function province()
    {
        return $this->hasOne(RegionModel::class,'id','province_id');
    }

    public function city()
    {
        return $this->hasOne(RegionModel::class,'id','city_id');
    }

    public function district()
    {
        return $this->hasOne(RegionModel::class,'id','region_id');
    }

    public function user()
    {
        return $this->hasOne(UserModel::class,'id','user_id');
    }

}
