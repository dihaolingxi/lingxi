<?php

/*

 * @file

 * Created on 2012-11-16

 *

 * $Id$

 */

class GlobalsController extends CSnsController

{
 	
 	public $model;
 	public function init()
	{	
		include "config.php";		
 		$this->loadModel($this->model, 'GlobalsModel');
 	}
	
    public function actionParameter()
    {

        $array=array();
        $query = "select title,notreg_read_nums,bag_moh_nums from company  limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($uinfo)){
            $array['status']=500;
            $array['msg']="查询失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }else{
            $array['status']=200;
            $array['msg']="查询成功";
            $array['data']=$uinfo;
            echo CSnsCommon::jsonencode($array);
            exit;
        }   
    }
    public function actionGreatcoe()
    {
        extract($_REQUEST);
        $opts=array(
        "http"=>array(
                "method"=>"GET",
                "timeout"=>60
                ),
        );
        if(empty($width)){
            $width=430;
        }
        $context = stream_context_create($opts);
        //$url请求的地址，例如：
        $wtoken =file_get_contents("https://".$_SERVER['HTTP_HOST']."/?r=app/globals/tokens", false, $context);
        $urls="https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=".$wtoken;
        $datas['scene']=$scene;
        $datas['page']=$page;
        $datas['width']=$width;
        $datas['auto_color'] = false;//是否自定义颜色
                
        $data['line_color'] = array(
                    "r"=>"221",
                    "g"=>"0",
                    "b"=>"0",
                );//自定义的颜色值
        $datas['is_hyaline']=false;
        $datas=json_encode($datas);
        //echo $urls;
        $rand = time().CSnsCommon::randomInt(8);
        $code=CSnsCommon::https_post($urls,$datas);
       // print_r($code);
        $codes=json_decode($code,true);
        //print_r($codes);
        if(!empty($codes['errcode'])){
            $array['status']=500;
            $array['msg']="生成失败";
            $array['data']=$codes;
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $files=file_put_contents($_SERVER['DOCUMENT_ROOT']."/files/code/".$rand.".jpg", $code);
        if($files==false){
            $array['status']=500;
            $array['msg']="生成失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }else{
            $array['status']=200;
            $array['msg']="生成成功";
            $array['data']=array("codeurl"=>"https://".$_SERVER['HTTP_HOST']."/files/code/".$rand.".jpg");
            echo CSnsCommon::jsonencode($array);
            exit;
        }
    }
    
    public function actionTokens()
    {
        $query = "select id,access_token,dateline from company  limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(!empty($uinfo['access_token'])){
            if(time()>=$uinfo['dateline']){
                $wtoken=CSnsCommon::wtoken(NETWORK_APPID,NETWORK_APPSECRET);  
             
                $this->storage->execute("update company set access_token='".$wtoken."',dateline=".strtotime("+1 hours")." where id=".$uinfo['id']);
            }else{
                $wtoken=$uinfo['access_token'];
            }
        }else{
            $wtoken=CSnsCommon::wtoken(NETWORK_APPID,NETWORK_APPSECRET);  
            $this->storage->execute("update company set access_token='".$wtoken."',dateline=".strtotime("+1 hours")." where id=".$uinfo['id']);
        }
        echo $wtoken;
    }
    public function actionPayments()
    {
        extract($_REQUEST);
        if(empty($openid) || empty($stoken)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id,cityid from user_info where network_openid='".$openid."' and token='".$stoken."' limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($uinfo)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id,bag_moh_nums,dateline from company  limit 1";
        $cinfo = $this->storage->execute($query,null,null,array('single'=>true));
        $data['orderno']=date("Ymdhis").str_pad($tokens[0]['id'],8,"0",STR_PAD_LEFT);
        require_once  $_SERVER['DOCUMENT_ROOT']."/libs/weixin/Pay.php";
        $PayClass = new PayClass();
        $res = $PayClass->pay($openid,$data['orderno'],$cinfo['bag_moh_nums']*100);
        CSnsCommon::logError(json_encode($res));
        if($res['return_code']=='SUCCESS'){
            $params = array($uinfo['id'], $data['orderno'],$cinfo['bag_moh_nums'],$res['prepay_id'],time());
            $bid = $this->storage->execute(
                    "insert into user_money " .
                    "   set uid=?,orderno=?,pay_money=?,transaction_id=?,dateline=?", 
                    $params, null, array('insert_id'=>1)
                );
            if($bid>0){
                    $payparams["appId"] = $res['appid'];   
                    $payparams["timeStamp"]=time();       
                    $payparams["nonceStr"] = $res['nonce_str'];   
                    $payparams["package"]= "prepay_id=".$res['prepay_id'];
                    $payparams["signType"]= "MD5";
                    ksort($payparams);
                    //print_r($payparams);
                    foreach($payparams as $k=>$l)
                    {
                        $str.=$k.'='.$l.'&';
                    }
                   //生成签名
                    $str .= 'key=jintianTIANQIbucuojiushihenleng1';
                    //echo $str;
                    $sign = strtoupper(md5($str));
                    //md5加密 转换成大写
                    $array['data']=array('prepay_id'=>$res['prepay_id'],'prices'=>$cinfo['bag_moh_nums'],'timeStamp'=>$payparams["timeStamp"],'nonceStr'=>$res['nonce_str'],'paySign'=>$sign);
                    $array['status']=200;
                    $array['msg']="预支付成功";
                    echo CSnsCommon::jsonencode($array);
                    exit;
                   
            }else{
                $array['status']=500;
                $array['msg']="预支付失败";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
        }else{
            $array['status']=500;
                $array['msg']=$res['return_msg'];
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
        }
    }
    
    public function actionPaymentstrue()
    {
        extract($_REQUEST);
        if(empty($openid) || empty($stoken)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id,cityid from user_info where network_openid='".$openid."' and token='".$stoken."' limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($uinfo)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        if(empty($transaction_id)){
            $array['status']=500;
            $array['msg']="请提交支付订单号";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }   
        if(empty($cash_fee)){
            $array['status']=500;
            $array['msg']="请提交支付金额";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }   
        $query = "select id from user_money where uid=".$uinfo['id']." and transaction_id='".$transaction_id."' limit 1";
        $cinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($cinfo)){
            $array['status']=500;
            $array['msg']="订单不存在";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }else{
            $this->storage->execute("update user_money set ispay=1,paytime=".time().",cash_fee=".($cash_fee/100)." where  id=".$cinfo['id']);
            $this->storage->execute("update user_info set flat_rate=1,flat_rate_time=".strtotime("+1 month")." where  id=".$uinfo['id']);
            $array['status']=200;
            $array['msg']="支付成功";
            $array['data']=array("flat_rate_time"=>strtotime("+1 month"));
            echo CSnsCommon::jsonencode($array);
            exit;
        }
    }

    public function actionShares()
    {
        extract($_REQUEST);
        if(empty($openid) || empty($stoken)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id,cityid from user_info where network_openid='".$openid."' and token='".$stoken."' limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($uinfo)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $read_nums=(int)$read_nums;
        $array=array();
        $query = "select * from company  limit 1";
        $cinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if($types==0 || $types==1){
            $query = "select id from user_info where id=".$tuid."  limit 1";
            $fuinfo = $this->storage->execute($query,null,null,array('single'=>true));
            if(empty($fuinfo)){
                $array['status']=500;
                $array['msg']="会员不存在";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }else{
                if($types==0){
                    $read_nums=$cinfo['request_user_nums'];
                }
                if($types==1){
                    $read_nums=$cinfo['request_user_great_nums'];
                }
                
                
                $this->storage->execute("update user_info set read_nums=read_nums+".$read_nums." where id=".$fuinfo['id']);
                $array['status']=200;
                $array['msg']="提交成功";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }   
        }
        if($types==2){
            if(empty($uinfo['cityid'])){
                $array['status']=500;
                $array['msg']="未设置城市";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
            $query = "select id from user_info where id=".$tuid."  limit 1";
            $fuinfo = $this->storage->execute($query,null,null,array('single'=>true));
            if(empty($fuinfo)){
                $array['status']=500;
                $array['msg']="分享会员不存在";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
            $query = "select id,cityid from word_book where id=".$bid." and uid=".$uinfo['id']." limit 1";
            $binfo = $this->storage->execute($query,null,null,array('single'=>true));   
            if(!empty($binfo)){
                $array['status']=500;
                    $array['msg']="禁止审核自创字典";
                    $array['data']="";
                    echo CSnsCommon::jsonencode($array);
                    exit;
            }

            
            $query = "select id from word_book_check where id=".$bid." limit 1";
            $cinfos = $this->storage->execute($query,null,null,array('single'=>true));
            if(!empty($cinfos)){
                $array['status']=200;
                    $array['msg']="接受成功";
                    $array['data']="";
                    echo CSnsCommon::jsonencode($array);
                    exit;
            }
            $query = "select id,onshop,cityid from word_book where id=".$bid." and isdel=0 limit 1";
            $binfos = $this->storage->execute($query,null,null,array('single'=>true));   
            if ($uinfo['cityid']!=$binfos['cityid']) {
                $array['status']=500;
                    $array['msg']="城市不符，不能参与审核";
                    $array['data']="";
                    echo CSnsCommon::jsonencode($array);
                    exit;
            }

            if(empty($binfos)){
                $array['status']=500;
                $array['msg']="字典不存在";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
            if($binfos['onshop']>0){
                $array['status']=500;
                $array['msg']="字典已审核完成";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }

            $params = array($uinfo['id'], $bid, time());
            $cid = $this->storage->execute(
                    "insert into word_book_check " .
                    "   set uid=?,bid=?,dateline=?,types=1", 
                    $params, null, array('insert_id'=>1)
                );
            if($cid>0){
                $read_nums=$cinfo['request_user_check_nums'];
                $this->storage->execute("update user_info set read_nums=read_nums+".$read_nums." where id=".$fuinfo['id']);
                $array['status']=200;
                $array['msg']="接受成功";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }else{
                $array['status']=500;
                $array['msg']="接受失败";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
            
        }
        if($types==3){
            $read_nums=$cinfo['great_book_nums'];
            if(empty($bid)){
                $array['status']=500;
                $array['msg']="字典参数错误";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
            $query = "select id,nid from word_book where id=".$bid."  limit 1";
            $binfo = $this->storage->execute($query,null,null,array('single'=>true));
            if(empty($binfo)){
                $array['status']=500;
                $array['msg']="字典不存在";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
            $query = "select id,bid from user_read_book where bid=".$bid." and uid=".$uinfo['id']." limit 1";
            $rinfo = $this->storage->execute($query,null,null,array('single'=>true));   
            if(empty($rinfo)){
                $params = array($uinfo['id'], $bid,$read_nums,time());
                $inid = $this->storage->execute(
                        "insert into user_read_book " .
                        "   set uid=?,bid=?,read_nums=?,dateline=?", 
                        $params, null, array('insert_id'=>1)
                    );
                if($inid>0){
                    $array['status']=200;
                    $array['msg']="提交成功";
                    $array['data']="";
                    echo CSnsCommon::jsonencode($array);
                    exit;
                }else{
                    $array['status']=500;
                    $array['msg']="提交失败";
                    $array['data']="";
                    echo CSnsCommon::jsonencode($array);
                    exit;
                }
            }else{
                $this->storage->execute("update user_read_book set read_nums=read_nums+".$read_nums." where id=".$rinfo['id']);
                $array['status']=200;
                $array['msg']="提交成功";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
        }
        if($types==4){
            
                $read_nums=$cinfo['check_book_nums'];
                $this->storage->execute("update user_info set read_nums=read_nums+".$read_nums." where id=".$uinfo['id']);
                $array['status']=200;
                $array['msg']="提交成功";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
           
            
        }
       
    }
    public function actionReadsize()
    {
        extract($_REQUEST);
        if(!empty($openid) || !empty($stoken)){
            $query = "select id,read_nums,flat_rate,flat_rate_time,close_time,states from user_info where network_openid='".$openid."' and token='".$stoken."' limit 1";
            $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
            if(empty($uinfo)){
                $array['status']=501;
                $array['msg']="登录失败";
                $array['data']="";
                echo CSnsCommon::jsonencode($array);
                exit;
            }
            $query = "select a.id,a.read_nums,b.title from user_read_book a inner join word_book_lists b on a.bid=b.id where a.uid=".$uinfo['id']."";
            $readlists = $this->storage->execute($query);
            $array['data']['read_nums']=$uinfo['read_nums'];
            $array['data']['title_read_nums']=$readlists;
            $array['data']['flat_rate']=$uinfo['flat_rate'];
            $array['data']['flat_rate_time']=$uinfo['flat_rate_time'];
            $array['data']['states']=$uinfo['states'];
            $array['data']['close_time']=$uinfo['close_time'];
        }
        $query = "select title,notreg_read_nums,bag_moh_nums from company  limit 1";
        $ginfo = $this->storage->execute($query,null,null,array('single'=>true));
        $array['data']['notreg_read_nums']=$ginfo['notreg_read_nums'];
        $array['data']['bag_moh_nums']=$ginfo['bag_moh_nums'];
        $array['status']=200;
            $array['msg']="查询成功";
            echo CSnsCommon::jsonencode($array);
            exit;
    }
    public function actionPays()
    {
        header("Content-type: text/html; charset=utf-8");
        CSnsCommon::logError(json_encode($_REQUEST));
        echo "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>";
        return "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>";

    }
    public function actionBanders()
    {

        $array=array();
        $query = "select title,cover_img,weburl from bander where cat_id=12 and isdel=0 and state=0 order by weight desc,id desc";
        $lists = $this->storage->execute($query);
        foreach($lists as $k=>$l){
                $lists[$k]['cover_img']='https://'.$_SERVER['HTTP_HOST']._FILE_URL.$l['cover_img'];
            }
        if(empty($lists)){
            $array['status']=500;
            $array['msg']="查询失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }else{
            $array['status']=200;
            $array['msg']="查询成功";
            $array['data']=$lists;
            echo CSnsCommon::jsonencode($array);
            exit;
        }   
    }

}
