<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class AnswerModel extends Model
{
    protected $table = 'answer';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','content','user_id','is_display','create_time','update_time','question_id'
    ];

    public function question()
    {
        return $this->belongsTo(QuestionModel::class,'question_id','id');
    }
}
