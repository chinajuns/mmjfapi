<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class VerifyListModel extends Model
{
    const NUMBER = 20;
    const EXPIRE_TIME = 600;
    protected $table = 'verify_list';
    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'mobile','code','deviceid','create_time'
    ];
}
