<?php
//error_reporting(0); // 关闭错误提示
date_default_timezone_set('Asia/Shanghai');
header('Content-Type: text/html; charset=UTF-8');
require_once './vendor/autoload.php';
define('ROOT_DIR',__DIR__);
$act = $_GET['act'];
$C = new Config('Config');
$Amoli = new Amoli();
$oss = $C->get('oss');
$cos = $C->get('cos');

$type = $C->get('type', 'local');
$indexpass = $C->get('indexpass');
$Cookie = $_COOKIE['Amoli_index'];
// 判断是否登录
if ($indexpass) {
    if ($act == 'getList') {
        if (!isset($Cookie) || $Cookie != md5($indexpass)) {
            $login = false;
            echo json_encode(['code' => 2, 'msg' => '你未登录，请先登录！']);
            exit();
        }
    }
}
$pan123Config=$type=='pan123'?$C->get('pan123'):[];
switch ($act) {
    case 'getConfig': // 获取配置
        // 判断是否登录
        ($Cookie == md5($indexpass) || !$indexpass) ? $log = true : $log = false;
        // 判断是否安装
        file_exists('data/install.lock') ? $install = true : $install = false;
        $result = ['code' => 1, 'msg' => '获取成功！', 'data' => ['name' => $C->get('name'),  'record' => $C->get('record'), 'install' => $install, 'log' => $log, 'verify' => $C->get('verify', false)]];
        break;
    case 'getList': // 加载目录
        $dir = $_POST['dir'];
        switch ($type) {
            case 'local': //本地存储
                $dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $Amoli->getEncoding($C->get('localhost') . $dir, true);
                $list = $Amoli->getLocalList($dir);
                break;
            case 'oss': //OSS存储
                $dir = $oss['osshost'] . $dir;
                $list = $Amoli->getOssList($oss['bucket'], $oss['endpoint'], $oss['accessKeyId'], $oss['accessKeySecret'], $dir);
                break;
            case 'cos': //COS存储
                $dir = $cos['coshost'] . $dir;
                $list = $Amoli->getCosList($cos['bucket'], $cos['region'], $cos['secretId'], $cos['secretKey'], $dir);
                break;
	        case 'pan123'://123云盘
				$list= $Amoli->getPan123List($pan123Config,$_POST['id']);
		        break;
        }
        $result = ['code' => 1, 'msg' => '获取成功', 'data' => $list];
        break;
    case 'verify': // 生成验证
        require_once __DIR__ . '/app/class/Geetestlib.class.php';
        $GtSdk = new GeetestLib('Amoli', '1552294270');
        $data = [
            'user_id' => $C->get('name'),
            'client_type' => 'web',
            'ip_address' => $_SERVER["REMOTE_ADDR"]
        ];
        $status = $GtSdk->pre_process($data, 1);
        echo $GtSdk->get_response_str();
        return;
    case 'getUrl': // 获取文件下载Url
        if ($C->get('verify', false)) {
            require_once __DIR__ . '/app/class/Geetestlib.class.php';
            $GtSdk = new GeetestLib('Amoli', '1552294270');
            $data = [
                'user_id' => $C->get('name'),
                'client_type' => 'web',
                'ip_address' => $_SERVER["REMOTE_ADDR"]
            ];
            if (!$GtSdk->success_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode'], $data)) {
                $result = ['code' => 2, 'msg' => '非法访问！'];
                break;
            }
        }
        $dir = $_POST['dir'];
        if (!$dir) {
            $result = ['code' => 2, 'msg' => '非法访问！'];
            break;
        }
        switch ($type) {
            case 'local':
                $url = '/' . $C->get('localhost') . $dir;
                $result = ['code' => 1, 'msg' => '获取成功！', 'data' => ['url' => $url]];
                break;
            case 'oss':
                $object = $oss['osshost'] . $dir;
                $result = $Amoli->getOssUrl($oss['bucket'], $oss['endpoint'], $oss['accessKeyId'], $oss['accessKeySecret'], $oss['ossdomain'], $object);
                break;
            case 'cos':
                $object = $cos['coshost'] . $dir;
                $result = $Amoli->getCosUrl($cos['bucket'], $cos['region'], $cos['secretId'], $cos['secretKey'], $object);
                break;
	        case 'pan123':
				$fileId=$_POST['id']??'';
				if(empty($fileId)){
					$result = ['code' => 2, 'msg' => '文件ID不能为空！'];
				}
		        $result= $Amoli->getPan123Url($pan123Config,$fileId);
		        break;
        }
        break;
    case 'login': // 前台登录
        $POST_pass = md5($_POST['indexpass']);
        if ($POST_pass == md5($indexpass)) {
            setcookie('Amoli_index', $POST_pass, time() + 3600 * 24); // 写入Cookies
            $result = ['code' => 1, 'msg' => '登录成功！'];
        } else {
            $result = ['code' => 2, 'msg' => '密码错误！'];
        }
        break;

    case 'logout': // 退出登录
        setcookie('Amoli_index', '', time() - 1552294270);
        exit('<script language="javascript">alert("您已成功注销本次登陆！");window.location.href="./";</script>');
        break;
    default:
        $result = ['code' => 2, 'msg' => 'No Act!'];
}
echo json_encode($result);
