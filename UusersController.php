<?php

/*

 * @file

 * Created on 2012-11-16

 *

 * $Id$

 */

class UusersController extends CSnsController

{
 	
 	public $model;
 	public function init()
	{	
		include "config.php";		
 		$this->loadModel($this->model, 'UcategoryModel');
 	}
	
    public function actionLogin()
    {   
        extract($_REQUEST);
        include_once  $_SERVER['DOCUMENT_ROOT'].'/libs/wxBizDataCrypt.php';   
        if(isset($code)){
            $TOKEN_URL="https://api.weixin.qq.com/sns/jscode2session?appid=".NETWORK_APPID."&secret=".NETWORK_APPSECRET."&js_code=".$code."&grant_type=authorization_code";  
                $json=file_get_contents($TOKEN_URL);
                CSnsCommon::logError($TOKEN_URL.$json);
                $result=json_decode($json);
                if(!empty($result->openid)){
                    $userInfo='';
                    $pc = new WXBizDataCrypt(NETWORK_APPID, $result->session_key);
                    $errCode = $pc->decryptData($encryptedData, $iv, $datas );
                    CSnsCommon::logError($errCode);
                    CSnsCommon::logError($datas);
                    if ($errCode == 0) {
                       $userInfo=json_decode($datas);
                    } else {        
                        $array['status']=500;
                        $array['msg']="获取用户信息失败！";
                        $array['data']="";
                        echo CSnsCommon::jsonencode($array);
                        exit;
                    }  
                    $stoken=CSnsCommon::_3rd_session(128);
                    $data = array(
                        $stoken, $userInfo->nickName, $userInfo->avatarUrl,$userInfo->gender,$userInfo->city, $userInfo->gender,$userInfo->province,$userInfo->country,$result->session_key,time()
                        );       
                    $query = "select title,reg_read_nums,bag_moh_nums from company  limit 1";
                    $company = $this->storage->execute($query,null,null,array('single'=>true));
                    $query = "select a.id,a.nickname,a.realname,b.name as cityname,a.mobile,a.states,a.states,a.close_time,a.cityid,a.read_nums,a.flat_rate,a.flat_rate_time from user_info a left join all_citys b on a.cityid=b.aid where a.network_openid='".$result->openid."' limit 1";
                    $lists = $this->storage->execute($query,null,null,array('single'=>true));
                    
                    if(empty($lists)){      
                        $tuid=(int)$tuid;
                        array_push($data,$result->openid,time(),0,$tuid);//添加元素
                        CSnsCommon::logError(json_encode($data));
                        $query = "insert into user_info " .
                            "   set token=?,nickname=?, headimg=?, gender=?, city=?, sex=?, province=?,country=?,session_key=?,lasttime=?,network_openid=?,dateline=?,states=?,tuid=?,read_nums=".$company['reg_read_nums'];
                        $insert_id = $this->storage->execute($query, $data, null, array('insert_id' => true) );     
                        if ($insert_id>0 and $tuid>0) {
                            $query = "select id from user_info where id=".$tuid." limit 1";
                            $tinfo = $this->storage->execute($query,null,null,array('single'=>true));
                            if(!empty($tinfo)){
                                $query = "select request_user_reg_nums from company  limit 1";
                                $cinfo = $this->storage->execute($query,null,null,array('single'=>true));
                                $this->storage->execute("update user_info set read_nums=read_nums+".$cinfo['request_user_reg_nums']." where id=".$tinfo['id']);
                            }
                        }                 
                        //$dbnum=$db->insert('user_info',$data);
                        $nickname=$userInfo->nickName;
                        $cityname="";
                        $mobile="";
                        $cityid="";
                        $uid=$insert_id;
                        $read_nums=$company['reg_read_nums'];
                        $flat_rate=0;
                        $flat_rate_time=0;
                    }else{                                              
                        $query = "update user_info " .
                                "   set token=?,nickname=?, headimg=?, gender=?, city=?, sex=?, province=?,country=?,session_key=?,lasttime=? where network_openid='".$result->openid."'";                        
                        $this->storage->execute($query, $data );
                        if($lists['states']==1){
                            if($lists['close_time']==0){
                                $array['status']=502;
                                $array['msg']="您的账户已被禁用";
                                $array['data']=array('close_time'=>$lists['close_time']);
                                echo CSnsCommon::jsonencode($array);
                                exit;
                            }
                            if($lists['close_time']>time()){
                                $array['status']=502;
                                $array['msg']="您的账户已被禁用";
                                $array['data']=array('close_time'=>$lists['close_time']);
                                echo CSnsCommon::jsonencode($array);
                                exit;
                            }else{
                                $query = "update user_info set states=0,close_time=0 where network_openid='".$result->openid."'";    
                                $this->storage->execute($query); 
                            }
                        }
                        $nickname=empty($lists['realname'])?$lists['nickname']:$lists['realname'];
                        if(!empty($lists['cityname'])){
                            $cityname=$lists['cityname'];
                        }else{
                            $cityname="";
                        }
                        if(!empty($lists['mobile'])){
                            $mobile=$lists['mobile'];
                        }else{
                            $mobile="";
                        }
                        $uid=$lists['id'];
                        $cityid=$lists['cityid'];
                        $read_nums=$lists['read_nums'];
                        $flat_rate=$lists['flat_rate'];
                        $flat_rate_time=$lists['flat_rate_time'];
                        
                    }
                    $array['status']=200;
                    $array['msg']="登录成功！";
                    $array['data']=array('stoken'=>$stoken,'openid'=>$result->openid,'nickname'=>$nickname,'cityname'=>$cityname,'mobile'=>$mobile,'cityid'=>$cityid,'read_nums'=>$read_nums,'flat_rate'=>$flat_rate,'flat_rate_time'=>$flat_rate_time,'uid'=>$uid);
                    echo CSnsCommon::jsonencode($array);
                    
                    //$db->close();
                }else{
                    $array['status']=500;
                    $array['msg']="登录失败！";
                    $array['data']="";
                    echo CSnsCommon::jsonencode($array);
                                 
                   // $db->close();
                }
        }else{
            $array['status']=500;
            $array['msg']="Code不存在！";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
           
        }

    }
    public function actionUpdate()
    {   
        extract($_REQUEST);
        if(empty($openid) || empty($stoken)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        if(empty($nickname)){
            $array['status']=500;
            $array['msg']="请提交昵称";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id,nickname,realname from user_info where network_openid='".$openid."' and token='".$stoken."' limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($uinfo)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $params = array(        
            $nickname,$uinfo['id']
        );        
        $query = "update user_info " .
                "   set realname=? where id=?";
        $this->storage->execute($query, $params );
            $array['status']=200;
            $array['msg']="修改成功";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);

    }

    public function actionMobile()
    {   
        extract($_REQUEST);
        if(empty($openid) || empty($stoken)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        if(empty($mobile)){
            $array['status']=500;
            $array['msg']="请提交手机号";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        if(CSnsCommon::chickphone($mobile)=="-1"){
            $array['status']=500;
            $array['msg']="手机号格式！请重试";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id,nickname,realname from user_info where network_openid='".$openid."' and token='".$stoken."' limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($uinfo)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id from user_info where mobile='".$mobile."' and id<>".$uinfo['id']." limit 1";
        $minfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(!empty($minfo)){
            $array['status']=500;
            $array['msg']="手机号已被占用！请重试";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $params = array(        
            $mobile,$uinfo['id']
        );        
        $query = "update user_info " .
                "   set mobile=? where id=?";
        $this->storage->execute($query, $params );
            $array['status']=200;
            $array['msg']="修改成功";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);

    }

    public function actionCitys()
    {   
        extract($_REQUEST);
        if(empty($openid) || empty($stoken)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        if(empty($cityid)){
            $array['status']=500;
            $array['msg']="请提交城市ID";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $query = "select id from user_info where network_openid='".$openid."' and token='".$stoken."' limit 1";
        $uinfo = $this->storage->execute($query,null,null,array('single'=>true));
        if(empty($uinfo)){
            $array['status']=501;
            $array['msg']="登录失败";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
            exit;
        }
        $params = array(        
            $cityid,$uinfo['id']
        );        
        $query = "update user_info " .
                "   set cityid=? where id=?";
        $this->storage->execute($query, $params );
            $array['status']=200;
            $array['msg']="设置成功";
            $array['data']="";
            echo CSnsCommon::jsonencode($array);
    }
	
}