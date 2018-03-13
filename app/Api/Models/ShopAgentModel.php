<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAgentModel extends Model
{
    protected $table = 'shop_agent';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','loaner_id','sys_pro_id','third_pro_id','pro_cate_id','status','is_normal','create_time','update_time','shop_id','apply_peoples'
    ];

    public function product(){
        return  $this->hasOne(ProductModel::class,'id','sys_pro_id');
    }

    public function productOther(){
        return $this->hasOne(ProductOtherModel::class,'id','third_pro_id');
    }

    public function loaner(){
        return $this->hasOne(LoanerModel::class,'id','loaner_id');
    }
}

