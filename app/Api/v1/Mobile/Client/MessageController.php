<?php

namespace App\Api\v1\Mobile\Client;

use App\Api\Models\NoticeModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class MessageController extends BaseController
{
    /**
     * @param Request $request
     * @param $type
     * @return mixed
     * 消息提醒
     */
    public function message(Request $request,$type)
    {
        $list = NoticeModel::where([['to_uid','=',$request->uid],['type','=',$type],['status','>',0]])
            ->paginate($this->pageSize,['from_uid','to_uid','title','content','type','create_time','is_success','status']);
        if(count($list))
        {
            $this->setData($list);
        }else{
            $this->setMessage('暂无数据');
            $this->setstatusCode(5000);
        }
        return $this->result();
    }

    /**
     * 已读状态变更
     */
    public function setRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',//信贷经理1，文章2
//            'id' => 'required',//ids:
        ]);
        if (!$validator->fails()) {
            $ids = explode(',', $request->id);
            if (count($ids))
             {
                 if(in_array($request->type,[1,2]))
                 {
                    NoticeModel::where(['to_uid' => $request->uid, 'type' => $request->type])
                             ->update(['status' => 2, 'update_time' => time()]);
                 }
            }
        }
        else{
            $this->setMessage('参数错误');
            $this->setstatusCode(4002);
        }
        return $this->result();
    }
}
