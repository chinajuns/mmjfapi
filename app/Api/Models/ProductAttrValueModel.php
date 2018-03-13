<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttrValueModel extends Model
{
    protected $table = 'product_attr_value';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','attr_value','attr_id','desc','create_time','status'
    ];
}
