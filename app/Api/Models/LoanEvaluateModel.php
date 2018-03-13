<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class LoanEvaluateModel extends Model
{
    protected $table = 'loan_evaluate';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','loan_id','user_id','status','describe','score_str','score_avg','focus','create_time'
    ];

    public function user()
    {
        return $this->hasOne(UserModel::class,'id','user_id');
    }
}
