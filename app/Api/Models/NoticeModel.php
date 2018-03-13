<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeModel extends Model
{
    protected $table = 'notice';

    public $timestamps = false;

    protected $fillable = [
        'id','from_uid','to_uid','title','content','status','create_time','update_time','type','is_success'
    ];
}
