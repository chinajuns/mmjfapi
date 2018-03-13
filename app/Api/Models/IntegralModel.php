<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class IntegralModel extends Model
{
    protected $table = 'user_integral';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','integral_log','integral_description','integral_number','status','create_time','update_time'
    ];
}
