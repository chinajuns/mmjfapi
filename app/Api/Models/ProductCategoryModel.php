<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategoryModel extends Model
{
    protected $table = 'product_category';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','cate_name','sort','is_display','create_time','update_time','pid'
    ];
}
