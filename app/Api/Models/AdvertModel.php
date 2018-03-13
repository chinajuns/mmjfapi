<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertModel extends Model
{
    protected $table = 'advert';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','title','content','image','is_display','advert_config','advert_port','link','type'
    ];

}
