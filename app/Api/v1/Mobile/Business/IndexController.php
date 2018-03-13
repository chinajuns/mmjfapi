<?php

namespace App\Api\v1\Mobile\Business;

use App\Api\Models\JunkModel;
use App\Api\Models\LoanerModel;
use App\Api\Models\RegionModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;

class IndexController extends BaseController
{
    //首页
    public function index(Request $request){
        if(!$request->city_name){
            $this->setstatusCode(4002)->setMessage('城市不能为空');
            return $this->result();
        }
        if(!$request->ad_position_id){
            $this->setstatusCode(4002)->setMessage('banner图位置不能为空');
            return $this->result();
        }
        $loaner = LoanerModel::where(['user_id'=>$request->uid])
            ->first(['is_auth']);
        $city = RegionModel::where(['name'=>str_replace('市','',$request->city_name),'type'=>2])
            ->first(['id']);
        if($city) {
            $total_order = JunkModel::where([['city_id','=',$city->id],[ 'status' ,'=', 1],['is_check','=',2],['expire_time','>',time()]])
                ->count();
            $banner = ['http://img.zcool.cn/community/010a2c587c33daa801219c77ea3f4d.jpg', 'http://img.zcool.cn/community/0146c2586478f4a8012060c8925881.jpg'];
            $data = ['total_order' => $total_order, 'banner' => $banner,'is_auth'=>$loaner?$loaner->is_auth:1];
            $this->setData($data);
        }
        else
        {
            $this->setMessage('暂无该城市');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }
}
