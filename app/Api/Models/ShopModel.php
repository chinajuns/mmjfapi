<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ShopModel extends Model
{
    protected $table = 'shop';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','user_id','loaner_id','auth_id','pageviews','mechanism_name','work_time','profile','proxy_product_num','add_product_num','card_share_num','shop_share_num','shop_share_url','create_time','update_time','likes','status','introduce','service_object','special','loaner_name','spread','check_result','wechat','qrcode'
    ];

    public function product(){
        return $this->hasMany(ShopAgentModel::class,'shop_id','id');
    }

    public function loaner()
    {
        return $this->hasOne(LoanerModel::class,'id','loaner_id');
    }
}
