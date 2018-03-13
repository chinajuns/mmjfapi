<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    protected $table = 'product';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','title','description','rate','time_limit','sort','account','content','recommend','is_hot','attr_id','is_display','cate_id','create_time','update_time','option_values','service_object','service_years','service_city','service_options','loan_number','loan_day','apply_peoples','proxy_peoples','rate_start','rate_end','loan_number_start','loan_number_end'
    ];

    public function loaner(){
        return $this->belongsTo(LoanerModel::class,'account','id');
    }




}
