<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionModel extends Model
{
    protected $table = 'question';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','cate_id','title','apply_content','user_id','author','status','is_pass','create_time','update_time','mobile_'
    ];
}
