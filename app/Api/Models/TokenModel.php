<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class TokenModel extends Model
{
    protected $table = 'token';

    const EXPIRE_TIME = 72000;//过期时间

    public $timestamps = false;

    protected $fillable = [
        'platform','sys_version','app_version','deviceid','express_time','uid'
    ];
}
