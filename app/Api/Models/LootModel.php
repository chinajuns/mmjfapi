<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class LootModel extends Model
{
    protected $table = 'loot_customers';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','user_id','loaner_id','junk_id','status','create_time','update_time','pid'
    ];
}
