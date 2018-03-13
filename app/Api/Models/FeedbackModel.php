<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackModel extends Model
{
    protected $table = 'feedback';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id','content','sys_version','current_version','mobile_brand','last_login_ip','reply_content','is_accept','create_time','update_time','integral_score','status'
    ];
}
