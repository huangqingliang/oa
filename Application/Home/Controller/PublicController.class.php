<?php

namespace Home\Controller;
use Think\Controller;

class PublicController extends Controller {
	protected $config = array('app_type' => 'public');

	public function login() {
		$this -> assign("is_verify_code", get_system_config("login_verify_code"));
		$auth_id = session(C('USER_AUTH_KEY'));
		if (!isset($auth_id)) {
			$this -> display();
		} else {
			header('Location: ' . __APP__);
		}
	}
        /*考勤后台管理系统*/
        function kaoqin(){
            $emp_no = session('emp_no');
            header("Location:/position/index.php/index/login/login/emp_no/".$emp_no);
            exit;
        }
        /*KPI系统*/
        function kpi(){
            $emp_no = session('emp_no');
            header("Location:/kpi/index.php/login/login/login/emp_no/".$emp_no);
            exit;
        }
        // 检测输入的验证码是否正确，$code为用户输入的验证码字符串
	function check_verify($code, $id = '') {
		$verify = new \Think\Verify();
		return $verify -> check($code, $id);
	}

	// 登录检测
	public function check_login() {
		$is_verify_code = get_system_config("login_verify_code");
		if (!empty($is_verify_code)) {
			$check = $this -> check_verify($_POST['verify'], 1);
			if (!$check) {
				$this -> error('验证码错误！');
			}
		}
		if (empty($_POST['emp_no'])) {
			$this -> error('帐号信息必须填写！');
		} elseif (empty($_POST['password'])) {
			$this -> error('密码信息必须填写！');
		}
		if ($_POST['emp_no'] == 'admin'){
			$is_admin=true;
			session(C('ADMIN_AUTH_KEY'), true);
		}
		
		// if(C("LDAP_LOGIN")&&!$is_admin){
                if(false){
			$where['emp_no']=array('eq',$_POST['emp_no']);
			$dept_name=D('UserView')->where($where)->getField('dept_name');
			
			if(empty($dept_name)){
				$this->error('帐号或密码错误！');
			}
			
			$ldap_host = C("LDAP_SERVER");//LDAP 服务器地址
			$ldap_port = C("LDAP_PORT");//LDAP 服务器端口号
			$ldap_user = "CN=".$_POST['emp_no'].",OU={$dept_name},".C('LDAP_BASE_DN');			
			$ldap_pwd = $_POST['password']; //设定服务器密码
			$ldap_conn = ldap_connect($ldap_host, $ldap_port) or die("Can't connect to LDAP server");
								 
			ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION,3);					
			$r=ldap_bind($ldap_conn, $ldap_user, $ldap_pwd);//与服务器绑定			
			if($r){
				$map['emp_no'] = $_POST['emp_no'];
				$map["is_del"] = array('eq', 0);
				$model = M("User");
				$auth_info = $model -> where($map) -> find();
			}else{
				$this->error(ldap_error($ldap_conn));
			}
		}else{
			$map = array();
			// 支持使用绑定帐号登录
			$map['emp_no'] = $_POST['emp_no'];
			$map["is_del"] = array('eq', 0);
			$map['password']=array('eq',md5($_POST['password']));
			$model = M("User");
			$auth_info = $model -> where($map) -> find();
		}

		//使用用户名、密码和状态的方式进行认证
		if (false == $auth_info) {
			$this -> error('帐号或密码错误！');
		} else {
			session(C('USER_AUTH_KEY'), $auth_info['id']);
			session('emp_no', $auth_info['emp_no']);
			session('user_name', $auth_info['name']);
			session('user_pic', $auth_info['pic']);
			session('dept_id', $auth_info['dept_id']);
                        session('com_id',$auth_info['com_id']);  
			// if (empty($auth_info['init_pwd'])) {
				// $this -> redirect('init_pwd');
			// }
			//保存登录信息
			$User = M('User');
			$ip = get_client_ip();
			$time = time();
			$data = array();
			$data['id'] = $auth_info['id'];
			$data['last_login_time'] = $time;
			$data['login_count'] = array('exp', 'login_count+1');
			$data['last_login_ip'] = $ip;
			$User -> save($data);
			// $this -> assign('jumpUrl', U("index/index"));
			// header('Location: ' . U("index/index"));
		    $this->success("登录成功！");
//-----------------------------------------------------------
                }
				
			}
	public function init_pwd() {
		$auth_id = session(C('USER_AUTH_KEY'));
		if (!isset($auth_id)) {
			//跳转到认证网关
			redirect(U(C('USER_AUTH_GATEWAY')));
		}
		$this -> display();
	}

//	public function find_pwd() {
//		if (IS_POST) {
//			$verify_no = I('verify_no');
//			if ($verify_no !== session('verify_no')) {
//				$this -> error('验证码错误');
//			}
//			$emp_no = I('emp_no');
//			$password = I('password');
//
//			$where['emp_no'] = array('eq', $emp_no);
//			$data['password'] = md5($password);
//			$result = M("User") -> where($where) -> save($data);
//			if ($result !== false) {
//				$this -> success('修改密码成功');
//				die ;
//			} else {
//				$this -> success('修改密码失败');
//			}
//		}
//		$this -> display();
//	}

	public function send_sms_verify($emp_no) {
		$verify_no = rand_string(6, 1);
		session('verify_no', $verify_no);
		if (empty($emp_no)) {
			$return['info'] = '请输入员工编号';
			$return['status'] = 0;
			$this -> ajaxReturn($return);
		}

		$where_user['emp_no'] = array('eq', $emp_no);
		$user = M("User") -> where($where_user) -> find();

		if ($user == false) {
			$return['info'] = '员工编号不存在';
			$return['status'] = 0;
			$this -> ajaxReturn($return);
		}
		if ($user !== false) {
			if (empty($user['mobile_tel'])) {
				$return['info'] = '该用户手机号不存在，请联系管理员';
				$return['status'] = 0;
				$this -> ajaxReturn($return);
			}
		}

		$account = 'jkwl110';

		//用户密码 $password
		$password = 'jkwl11033';

		//发送到的目标手机号码 $mobile
		$mobile = $user['mobile_tel'];

		//短信内容 $content
		$content = "【SIAS】您的验证码：{$verify_no}";

		//发送短信（其他方法相同）
		$gateway = "https://sh2.ipyy.com/sms.aspx?action=send&userid=&account={$account}&password={$password}&mobile={$mobile}&content={$content}&sendTime=";
		$result = file_get_contents($gateway);
		//dump($result);
		$xml = simplexml_load_string($result);
		if ($xml -> returnstatus == 'Success') {
			$return['status'] = 1;
			$return['info'] = '短信已发送';
			$this -> ajaxReturn($return);
		}
	}

	/* 退出登录 */
	public function logout() {
		$auth_id = session(C('USER_AUTH_KEY'));

		if (isset($auth_id)) {
			$_SESSION=array();
    		setcookie('PHPSESSID','',time()-3600,'/');
    		session_destroy();
			// session(null);
			$this -> assign("jumpUrl", __APP__);
			$this -> success('退出成功！');
		} else {
			$_SESSION=array();
    		setcookie('PHPSESSID','',time()-3600,'/');
    		session_destroy();
			$this -> assign("jumpUrl", __APP__);
			$this -> error('退出成功！');
		}
	}
//	public function reset_pwd() {
//		$password = I('password');
//		if ('' == trim($password)) {
//			$this -> error('密码不能为空！');
//		}
//
//		$data['password'] = md5($password);
//		$data['id'] = get_user_id();
//		$data['init_pwd'] = 1;
//
//		$result = M('User') -> save($data);
//
//		if (false !== $result) {
//			$this -> assign('jumpUrl', get_return_url());
//			$this -> success("密码修改成功");
//		} else {
//			$this -> error('重置密码失败！');
//		}
//	}

	public function register() {
		$this -> display();
	}

	// 登录检测
	public function check_register() {
		$is_verify_code = get_system_config("login_verify_code");
		if (!empty($is_verify_code)) {
			if (session('verify') != md5($_POST['verify'])) {
				$this -> error('验证码错误！');
			}
		}

		if (empty($_POST['emp_no'])) {
			$this -> error('帐号必须！');
		} elseif (empty($_POST['password'])) {
			$this -> error('密码必须！');
		} elseif ($_POST['password'] !== $_POST['check_password']) {
			$this -> error('密码不一致');
		}

		$map = array();
		// 支持使用绑定帐号登录
		$map['emp_no'] = $_POST['emp_no'];
		$count = M("User") -> where($map) -> count();

		if ($count) {
			$this -> error('该账户已注册');
		} else {
			$model = D("User");
			if (false === $model -> create()) {
				$this -> error($model -> getError());
			}
			$list = $model -> add();
			if ($list !== false) {//保存成功
				$this -> assign('jumpUrl', get_return_url());
				$this -> success('注册成功!');
			} else {
				$this -> error('注册失败!');
				//失败提示
			}
		}
	}

	public function verify() {
		$config = array('fontSize' => 15, // 验证码字体大小
		'length' => 4, // 验证码位数
		'useNoise' => false, // 关闭验证码杂点
		);
		$verify = new \Think\Verify($config);
		$verify -> entry(1);
	}

	public function recevie_mail() {
		$client_ip = $_SERVER["REMOTE_ADDR"];
		$server_ip = gethostbyname(null);
		if ($client_ip != $server_ip) {
			set_time_limit(0);
			$where['is_del'] = array('eq', 0);
			$mail_account_list = D("MailAccountView") -> where($where) -> select();
			foreach ($mail_account_list as $account) {
				$this -> receve($account['id']);
				sleep(1);
			}
			sleep(1);
		}
		die ;
	}

	//--------------------------------------------------------------------
	//   接收邮件
	//--------------------------------------------------------------------
	private function receve($user_id) {
		$mail_account = $this -> _get_mail_account($user_id);

		$new = 0;
		import("@.ORG.Util.receve");
		$mail_list = array();
		$mail = new \receiveMail();
		$connect = $mail -> connect($mail_account['pop3svr'], '110', $mail_account['mail_id'], $mail_account['mail_pwd'], 'INBOX', 'pop3/novalidate-cert');
		if (!$connect) {
			$connect = $mail -> connect($mail_account['pop3svr'], '995', $mail_account['mail_id'], $mail_account['mail_pwd'], 'INBOX', 'pop3/ssl/novalidate-cert');
		}
		$mail_count = $mail -> mail_total_count();
		if ($connect) {
			for ($i = 1; $i <= $mail_count; $i++) {
				$mail_id = $mail_count - $i + 1;
				$mail_header = $mail -> mail_header($mail_id);
				$where['mid'] = $mail_header['mid'];
				$where['user_id']=array('eq',$user_id);
				$count = M('Mail') -> where($where) -> count();
				if ($count == 0) {
					$model = M("Mail");
					$model -> create($mail_header);
					if ($model -> create_time < strtotime(date('y-m-d H:i:s')) - 86400 * 30) {
						$mail -> close_mail();
						if ($new > 0) {
							$push_data['type'] = '邮件';
							$push_data['action'] = '';
							$push_data['title'] = '收到' . $new . '封邮件';
							$push_data['content'] = '';
							$push_data['url'] = U('Mail/folder','fid=inbox&return_url=Mail/index');

							send_push($push_data, $user_id);
						}
						return;
					}

					$new++;
					$model -> user_id = $user_id;
					$model -> read = 0;
					$model -> folder = 1;
					$model -> is_del = 0;
					$str = $mail -> get_attach($mail_id);

					$model -> add_file = $this -> _receive_file($str, $model);
					$this -> _organize($model, $user_id);
					$model -> add();

				} else {
					$mail -> close_mail();
				}
			}
			if ($new > 0) {
				$push_data['type'] = '邮件';
				$push_data['action'] = '';
				$push_data['title'] = '收到' . $new . '封邮件';
				$push_data['content'] = '';
				$push_data['url'] = U("Mail/folder?fid=inbox&return_url=Mail/index");

				send_push($push_data, $user_id);
			}
		}
		$mail -> close_mail();
	}

	//--------------------------------------------------------------------
	//   接收邮件附件
	//--------------------------------------------------------------------
	private function _receive_file($str, &$model) {

		$ar = array_filter(explode("?", $str));
		$files = array();
		if (!empty($ar)) {
			foreach ($ar as $key => $value) {
				$ar2 = explode("|", $value);
				$cid = $ar2[0];
				$inline = $ar2[1];
				$file_name = $ar2[2];
				$tmp_name = $ar2[3];

				$files[0]['name'] = $file_name;
				$files[0]['tmp_name'] = $tmp_name;
				$files[0]['size'] = filesize($tmp_name);
				$files[0]['is_move'] = true;
				if (!empty($files)) {
					$File = D('File');
					$file_driver = C('DOWNLOAD_UPLOAD_DRIVER');
					$info = $File -> upload($files, C('DOWNLOAD_UPLOAD'), C('DOWNLOAD_UPLOAD_DRIVER'), C("UPLOAD_{$file_driver}_CONFIG"));
					if ($inline == "INLINE") {
						$model -> content = str_replace("cid:" . $cid, $info[0]['path'], $model -> content);
					} else {
						$add_file = $add_file . think_encrypt($info[0]['id']) . ';';
					}
				}
			}
		}
		return $add_file;
	}

	private function _organize(&$model, $user_id) {
		$where['user_id'] = array('eq', $user_id);
		$where['is_del'] = array('eq', 0);
		$list = M("MailOrganize") -> where($where) -> order('sort') -> select();

		foreach ($list as $val) {
			//包含

			if (($val['sender_check'] == 1) && ($val['sender_option'] == 1) && (strpos($model -> from, $val['sender_key']) !== false)) {
				$model -> folder = $val['to'];
				return;
			}
			//不包含
			if (($val['sender_check'] == 1) && ($val['sender_option'] == 0) && (strpos($model -> from, $val['sender_key']) == false)) {
				$model -> folder = $val['to'];
				return;
			}

			//包含
			if (($val['domain_check'] == 1) && ($val['domain_option'] == 1) && (strpos($model -> from, $val['domain_key']) !== false)) {
				$model -> folder = $val['to'];
				return;
			}

			//不包含
			if (($val['domain_check'] == 1) && ($val['domain_option'] == 0) && (strpos($model -> from, $val['domain_key']) == false)) {
				$model -> folder = $val['to'];
				return;
			}

			//包含
			if (($val['recever_check'] == 1) && ($val['recever_option'] == 1) && (strpos($model -> to, $val['recever_key']) !== false)) {
				$model -> folder = $val['to'];
				return;
			}
			//不包含
			if (($val['recever_check'] == 1) && ($val['recever_option'] == 0) && (strpos($model -> to, $val['recever_key']) == false)) {
				$model -> folder = $val['to'];
				return;
			}

			//包含
			if (($val['title_check'] == 1) && ($val['title_option'] == 1) && (strpos($model -> name, $val['title_key']) !== false)) {
				$model -> folder = $val['to'];
				return;
			}
			//不包含
			if (($val['title_check'] == 1) && ($val['title_option'] == 0) && (strpos($model -> name, $val['title_key']) == false)) {
				$model -> folder = $val['to'];
				return;
			}
		}
	}

	//--------------------------------------------------------------------
	//  读取邮箱用户数据
	//--------------------------------------------------------------------
	private function _get_mail_account($user_id = null) {
		if (empty($user_id)) {
			$user_id = get_user_id();
		}
		$model = M('MailAccount');
		$list = $model -> field('mail_name,email,pop3svr,smtpsvr,mail_id,mail_pwd') -> find($user_id);
		if (empty($list['mail_name']) || empty($list['email']) || empty($list['pop3svr']) || empty($list['smtpsvr']) || empty($list['mail_id']) || empty($list['mail_pwd'])) {
			$this -> assign('jumpUrl', U('MailAccount/index'));
			cookie('current_node', null);
			$this -> error("请设置邮箱帐号");
			die ;
		} else {
			return $list;
		}
	}
	
	
        /*
    * 微信回调
    */
    public function wechat() {
        // $sVerifyMsgSig = HttpUtils.ParseUrl("msg_signature");      
        $sVerifyMsgSig = I('get.msg_signature');
        $sVerifyTimeStamp = I('get.timestamp');
        $sVerifyNonce = I('get.nonce');
        $sVerifyEchoStr = I('get.echostr');               
        // 需要返回的明文，官方给出的，实际没用的参数，最终输出是$sEchoStr
        include_once './Application/Common/Common/wx/WXBizMsgCrypt.php';
        $EchoStr = "";
        $wxcpt = new \WXBizMsgCrypt(C("token"), C("encodingAesKey"), C("corpid"));
        $errCode = $wxcpt->VerifyURL($sVerifyMsgSig, $sVerifyTimeStamp, $sVerifyNonce, $sVerifyEchoStr, $sEchoStr);
        if ($errCode == 0) {
            // 验证URL成功，将sEchoStr返回
            // HttpUtils.SetResponce($sEchoStr);
            writerLogs($sEchoStr,'log','/sEchoStr/success');
            echo $sEchoStr;
        } else {
            writerLogs($errCode, 'log','/sEchoStr/error'); 
            print("ERR: " . $errCode . "\n\n");
        }
    }
    /*检查是否近期登录*/
    public function wx_index(){
        $biao = I('get.biao');
        if(empty($_SESSION['user_name'])){
            header("Location:https://".$_SERVER['SERVER_NAME']."index.php?m=&c=public&a=wxlogin&biao=".$biao);
        }else{
            redirect_func($biao);
        }       
    }
    /*
     * 微信回调登录
     */
    public function wxlogin() {
        $appid = C('corpid');
        $biao = I('get.biao');
        $danju_id = I('get.danju_id');

        $url_str="https://".$_SERVER['SERVER_NAME']."/index.php?m=home&c=public&a=wxcallback&biao=".$biao."&danju_id=".$danju_id;
        $redirect_url = urlencode($url_str);
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_url.'&response_type=code&scope=snsapi_base&state=fc#wechat_redirect';
	header("Location:".$url);
    }
    public function wxcallback() {
        $biao = I('get.biao');
        $danju_id = I('get.danju_id');
         
        $user = tp_wxcallback();
        $loginname = $user["UserId"];
        $model = M('user');
        $where['emp_no']= $loginname;
        $rs = $model->field('id,emp_no,name,nickname,weixin,pic,dept_id,com_id')->where($where)->find();            
        if($rs){
            session(C('USER_AUTH_KEY'), $rs['id']);
            session('emp_no', $rs['emp_no']);
            session('user_name',$rs['name']);
            session('user_pic', $rs['pic']);
            session('dept_id', $rs['dept_id']);
            session('com_id',$rs['com_id']);
            //保存登录信息
            $ip = get_client_ip();
            $time = time();
            $data = array();
            $data['id'] = $rs['id'];
            $data['last_login_time'] = $time;
            $data['login_count'] = array('exp', 'login_count+1');
            $data['last_login_ip'] = $ip;
            $model -> save($data);
            if($biao=='20'){
            	$model_flow=M('flow_log');
            	$M_flowRES=$model_flow->where(array('emp_no'=>$rs['emp_no'],'flow_id'=>$danju_id))->find();
            	if($M_flowRES['result']=='1' || $M_flowRES['result']=='0'){
            		$biao='22';
            	}
            }

            redirect_func($biao,$danju_id);
        }  else {
            $this->error("登录失败！");
        }
    }
    /*待办单据发送提醒*/
    function sendconfirm(){
        $hourse= date('H',time());
        if($hourse>8 && $hourse<22){    //推送提醒时间
            $model = M('flow_log');
            $map['is_del'] = 0;
            $map['_string'] = "fl.result IS NULL";
            $user_list = $model->alias('fl')->field('emp_no')->where($map)->group('emp_no')->select();
            foreach ($user_list as $v) {
                $user_arr[] = $v['emp_no'];
            }
            $data['title'] ="审批提醒";
            $data['action'] ="您有单据需要审批，请及时处理！";
            $url = "https://".$_SERVER['SERVER_NAME']."/index.php?m=&c=Public&a=wxlogin&biao=1";
            Pushmessage($data['title'],$data['action'],$url,$user_arr);
            echo 'success!';
        }  else {
            echo 'error!';
        }
    }
    
    
    public function delkaoqin(){
        $sql = "SELECT
	*
FROM
	(
		SELECT
			max(id) as id,count(*) AS counts ,
			emp_no ,
month,
			day
		FROM
			think_pos_form
		GROUP BY
			month,
			day,
			emp_no
		ORDER BY id desc
	) AS a
WHERE
	counts > 1";
        $Model = new \Think\Model();
        $data_arr = $Model->query($sql);
        foreach ($data_arr as $v) {
            M('pos_form')->delete($v['id']);
        }
        echo 'ok';
    }
}
    

