<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttrModel extends Model
{
    protected $table = 'product_attr';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','attr_key','attr_value','status','create_time','update_time','cate_id','config_id','condition','sort','sort_app','condition_app','attr_name'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 属性值
     */
    public function values(){

        return $this->hasMany(ProductAttrValueModel::class,'attr_id','id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     * 配置
     */
    public function config(){
        return $this->hasOne(ProductConfigModel::class,'id','config_id');
    }


}
