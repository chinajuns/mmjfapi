<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ReportModel extends Model
{
    protected $table = 'report';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','from_uid','to_uid','from_name','to_name','report_reason','report_type','create_time','type','loan_id'
    ];
}
