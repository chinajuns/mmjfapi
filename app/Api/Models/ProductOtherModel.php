<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOtherModel extends Model
{
    protected $table = 'product_others';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','title','service_city','rate','time_limit','sort','property','repayment','loan_day','calculate','advance','age','income','work_year','social_security','credit','credit_overdue','need_options','need_trade','need_identity','channel','url','account','content','recommend','is_hot','attr_id','is_display','cate_id','create_time','update_time','option_values','service_object','service_years','service_city','service_options'
    ];
}
