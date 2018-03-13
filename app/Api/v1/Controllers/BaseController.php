<?php

namespace App\Api\v1\Controllers;

use App\Api\Models\LoanerAttrModel;
use App\Api\Models\ProductAttrModel;
use App\Api\Models\ProductAttrValueModel;
use App\Api\Models\TokenModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Support\Facades\Redis;
include (app_path().'/Libraries/SMS/SmsSend.php');
include (app_path().'/Libraries/Qrcode/phpqrcode.php');
class BaseController extends Controller
{
    use Helpers;
    protected $statusCode = 200;//ok
    protected $message = 'success';//ok
    const EXPIRE_TIME = 600;
    const JUNK_EXPIRE_TIME = 36; //单位:小时
    protected $data = [];
    private $smsKey = '1001@501275730003';
    private $secret = '764274B702DAC1898AA5BB6640BAA810';
//    public $smsKey = '1001@501275730002';
//    public $secret = '948BB9F65083D560FA3563711B606B1F';
    public $pageSize = 10;
    // public $imgUrl = 'http://image.kuanjiedai.com/';
    public $imgUrl = 'http://image.com/';
    public $expire_time = 36 * 3600;
    /**
     * @param $str
     * @return mixed|string
     * 转义emoji表情
     */
    function userTextEncode($str){
        if(!is_string($str))return $str;
        if(!$str || $str=='undefined')return '';

        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i",function($str){
            return addslashes($str[0]);
        },$text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
        return json_decode($text);
    }

    /**
     *解码emoji表情转义
     */
    function userTextDecode($str){
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback('/\\\\\\\\/i',function($str){
            return '\\';
        },$text); //将两条斜杠变成一条，其他不动
        return json_decode($text);
    }

    /*
  *状态码获取
  */

    public function getstatusCode()
    {
        return $this -> statusCode;
    }

    /*
    *状态码设置
    */

    public function setstatusCode($statusCode)
    {
        $this -> statusCode =  $statusCode;
        return $this;
    }

    /*
*状态码获取
*/

    public function getData()
    {
        return $this -> data;
    }

    /*
    *状态码设置
    */

    public function setData($data)
    {
        $this -> data =  $data;
        return $this;
    }

    /*
*状态码获取
*/

    public function getMessage()
    {
        return $this -> message;
    }

    /*
    *状态码设置
    */

    public function setMessage($message)
    {
        $this -> message =  $message;
        return $this;
    }

    /**token**/
    /*
      *生成token
      */
    public function verifyToken()
    {
        return sha1(md5(uniqid(md5(microtime()))));
    }

    /*
     *token参数存入数据库
     */
    public function setTokens($data)
    {
        $id =  TokenModel::insertGetId($data);
        if($id){
            $token = $this -> verifyToken();
            $this -> setRedis($data['uuid'],$token);
            $this -> setstatusCode(200);
            return $this -> getRedis($data['uuid']);
        }else{
            $this->setstatusCode(4001);
        }
    }

    /**
     * @param $token
     * @param $deviceid
     * @return bool
     * token验证
     */
    public function checkToken($token,$deviceid)
    {
        $array_token = $this->getRedis($deviceid);
        if(json_decode($array_token['value'],true)['token'] == $token)
        {
            return true;
        }
        else
        {
            $result['status'] = 4001;
            $result['data'] = null;
            $result['msg'] = 'fail';
            return $result;
        }
    }

    /*
     *用户注册登录后修改redis对应token:uid
     *
     */

    public function updateToken($deviceid,$uid)
    {
        if(Redis::exists($deviceid)){
            //根据键名获取键值
            $array_token = Redis::get($deviceid);
            $token = json_decode($array_token,true)['token'];
            Redis::setex($deviceid,TokenModel::EXPIRE_TIME,json_encode(array('uid'=>$uid,'token'=>$token)));
            return true;
        }else{
            return false;
        }
    }

    /*
    * 图片上传
    */
    public function uploadImg(Request $request)
    {
        if ($request->file('image')->isValid()) {
            $file = $request->file('image');
            $allowed_extensions = ["png", "jpg", "gif","jpeg"];
            if ($file->getClientOriginalExtension() && !in_array($file->getClientOriginalExtension(), $allowed_extensions)) {
                return ['error' => 'You may only upload png, jpg or gif.'];
            }
           $destinationPath = 'e:/upload/images/'.date('Y',time()).'/'.date('m',time()).'/'.date('d',time()).'/';
            // $destinationPath = '/home/upload/images/'.date('Y',time()).'/'.date('m',time()).'/'.date('d',time()).'/';

            $extension = $file->getClientOriginalExtension();
            $this->mkDirs($destinationPath);
            //name:/2017/201701/20170101/000001.png
            $fileName = time() . rand(1000, 9999) . '.' . $extension;
            $file->move($destinationPath, $fileName);
            $url = substr($destinationPath . $fileName,strpos($destinationPath . $fileName,'upload')+6);
            return array('status' => 'success','src' => $url);
        }
    }
    /*
     * 图片文件夹创建
     */
    public function mkDirs($dir){
        if(!is_dir($dir)){
            if(!$this->mkDirs(dirname($dir))){
                return false;
            }
            if(!mkdir($dir,0777)){
                return false;
            }
        }
        return true;
    }

    /**
     * @param $url
     * @param null $data
     * @return mixed
     * curl 请求
     */

    protected function https_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
    /*
   *redis 存入指定时间的值
   * @param array
   */
    public function setRedis($data)
    {
        $key = $data['key'];
        $value = json_encode($data['val']);
        Redis::setex($key,TokenModel::EXPIRE_TIME,$value);
    }

    /*
    *redis 取值
    */

    public function getRedis($key)
    {
        if(Redis::exists($key))
        {//根据键名获取键值
            $data[$key] = $key;
            $data['value'] = Redis::get($key);
            return $data;
        }else{
            return false;
        }
    }


    /**
     * @param $mobile
     * @param $content
     * @return string
     * @throws \Exception
     * 短信验证码发送
     */
    public function sendSmsCode($mobile,$content){
        $SendSMS = new \SmsSend();
        $res =$SendSMS->sendBZ(
            $this->smsKey,
            $this->secret,
            $mobile,
            $content
        );
        if($res == true)
        {
           return '1';
        }
        else
        {
           return '0';
        }
    }

    /**
     * @param $message
     * @param null $data
     * 结果返回
     */
    public function result(){
        $result['msg'] = $this->getMessage();
        $result['data'] = $this->getData() ? $this->getData() : null;
        $result['status'] = $this->getstatusCode();
        return $result;
    }

    /**
     * @param $deviceid
     * @return int
     * 获取用户登录id
     */
    public function checkUid($deviceid){
        if(Redis::exists($deviceid))
        {
            //根据键名获取键值
            $array_token = Redis::get($deviceid);
            $token = json_decode($array_token, true);
            return $token['uid'];
        }else{
            return 0;
        }
    }


    //获取属性表键值对应
    public function getAttrKeyAndValue($ids){
        $attrs=LoanerAttrModel::from('loaner_attr as la')->select('laf.attr_value as attr_key','la.attr_value')->leftJoin('loaner_attr as laf','laf.id','=','la.pid')
            ->whereIn('la.id',explode(',',$ids))->get();
        return $attrs;
    }

    //代理产品详情获取申请条件和所需材料
    public function getProductApplyConditionAndMaterial($option_values,$need_data){
        //申请条件
        $data['apply_condition']=[];
        if($option_values){
            $attrs=json_decode($option_values,true);
            if($attrs['attr']!=null && is_array($attrs['attr'])){
                foreach ($attrs['attr'] as $key=>$attr){
                    $attrModel=ProductAttrModel::from('product_attr as pa')->select('pa.attr_key','pc.describe')
                        ->leftJoin('product_config as pc','pc.id','=','pa.config_id')->where(['pa.id'=>array_keys($attr)[0]])->first();
                    if($attrModel!=null){
                        $item['attr_key']=$attrModel->attr_key;
                        if($attrModel->describe=='radio'){
                            $attrValueModel=ProductAttrValueModel::select('attr_value')->where(['id'=>array_values($attr)[0]])->first();
                            $item['attr_value']=$attrValueModel?[$attrValueModel->attr_value]:[];
                        }elseif ($attrModel->describe=='checkbox'){
//                                    return array_values($attr)[0];
                            $attrValueModel=ProductAttrValueModel::select('attr_value')->whereIn('id',array_values($attr)[0])->get();
                            $item['attr_value']=$attrValueModel?array_column($attrValueModel->toArray(),'attr_value'):[];
                        }else{
                            $item['attr_value']=array_values($attr)[0];
                        }
                    }
                    $data['apply_condition'][]=$item;
                }
            }
        }
        //所需材料
        $data['need_data']=[];
        if($need_data){
            $needModel=ProductAttrValueModel::select('attr_value')->whereIn('id',explode(',',$need_data))->get();
            if($needModel!=null){
                $data['need_data']=array_column($needModel->toArray(),'attr_value');
            }
        }
        return $data;
    }

    //代理产品详情获取申请条件和所需材料
    public function getProductOptions($option_values,$need_data){
        //申请条件
        $data['apply_condition']=[];
        if($option_values){
            $attrs=json_decode($option_values,true);
            if($attrs['attr']!=null && is_array($attrs['attr'])){
                foreach ($attrs['attr'] as $key=>$attr){
                    $attrModel=ProductAttrModel::from('product_attr as pa')->select('pa.attr_key','pc.describe')
                        ->leftJoin('product_config as pc','pc.id','=','pa.config_id')->where(['pa.id'=>array_keys($attr)[0]])->first();
                    if($attrModel!=null){
                        $item['option_name']=$attrModel->attr_key;
                        if($attrModel->describe=='radio'){
                            $attrValueModel=ProductAttrValueModel::select('attr_value')->where(['id'=>array_values($attr)[0]])->first();
                            $item['option_values']=$attrValueModel?$attrValueModel->attr_value:'无';
                        }elseif($attrModel->describe=='checkbox'){
//                                    return array_values($attr)[0];
                            $attrValueModel=ProductAttrValueModel::select('attr_value')->whereIn('id',array_values($attr)[0])->get()->pluck('attr_value');
                            $item['option_values']=$attrValueModel?implode(',',$attrValueModel->toArray()):'无';
                        }else{
                            $item['option_values']=array_values($attr)[0]? (($attrModel->attr_key == '工资范围')?str_replace(',','-',array_values($attr)[0]).'元':''):'无';
                        }
                    }
                    $data['apply_condition'][]=$item;
                }
            }
        }
        //所需材料
        $data['need_data']=[];
        if($need_data){
            $needModel=ProductAttrValueModel::select('attr_value')->whereIn('id',explode(',',$need_data))->get()->pluck('attr_value');
            if($needModel!=null){
                $need = $needModel->toArray();
                $data['need_data']= implode(',',$need);
            }
        }
        return $data;
    }
    // 1. 生成原始的二维码(生成图片文件)
    function scerweima($url=''){

        $value = $url;                  //二维码内容

        $errorCorrectionLevel = 'H';    //容错级别
        $matrixPointSize = 10;           //生成图片大小

        //生成二维码图片
        $destinationPath = 'e:/upload/images/'.date('Y',time()).'/'.date('m',time()).'/'.date('d',time()).'/';
        // $destinationPath = '/home/upload/images/'.date('Y',time()).'/'.date('m',time()).'/'.date('d',time()).'/';
        $this->mkDirs($destinationPath);
        $filename = $destinationPath.time().'.png';
        \QRcode::png($value,$filename , $errorCorrectionLevel, $matrixPointSize, 2);

        $QR = $filename;                //已经生成的原始二维码图片文件

        $QR = imagecreatefromstring(file_get_contents($QR));

        //输出图片
//        imagepng($QR, 'qrcode.png');
        imagepng($QR, $filename);
        imagedestroy($QR);
        // return str_replace('/home/upload','',$filename);
        return str_replace('e:/upload','',$filename);
    }
    /*
     * 根据地址获取经纬度
     */
    function get_lng_lat($address){
        $url = "http://api.map.baidu.com/geocoder?address=$address&output=json&key=5QfstUVoGunXTkerDm5Z4wOY";
        $rows = file_get_contents($url);
        //将json数据转成数组
        $rows = json_decode($rows,TRUE);
//        dump($rows);
        if($rows){
            return $rows['result'];
        }
        else{
            return null;
        }
    }
}
