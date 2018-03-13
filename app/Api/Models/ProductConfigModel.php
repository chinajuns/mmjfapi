<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ProductConfigModel extends Model
{
    protected $table = 'product_config';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'config_key','config_value','pid','id','status','create_time','interval','describe'
    ];
}
