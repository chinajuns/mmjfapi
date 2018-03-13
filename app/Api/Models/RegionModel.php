<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class RegionModel extends Model
{
    protected $table = 'region';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'id','pid','name','pinyin','type','enable','status','first','lng','lat'
    ];

    public function city(){
        return $this->hasMany(RegionModel::class,'pid','id');
    }

    public function district(){
        return $this->hasMany(RegionModel::class,'pid','id');
    }

    public function beLongToCity(){
        return $this->belongsTo(RegionModel::class,'pid','id');
    }

    public function beLongToProvince(){
        return $this->belongsTo(RegionModel::class,'pid','id');
    }
}
