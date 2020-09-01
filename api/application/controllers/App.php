<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class App extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */

    public function __construct(){
        parent::__construct();
        
        header('Content-Type: application/json');
    }

	public function index()
	{
		$this->load->view('welcome_message');
    }

    // demo
    public function demo(){
        
        $test = $this->input->post('test');

        if($test)
        $this->_success('DEMO');

    }

    // 회원정보
    public function userinfo(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $this->db->select('mb_no, mb_id, mb_name, mb_email, mb_phone, added_id, mb_status, mb_fcm');
        $this->db->where('mb_token', $mb_token);
        $request = $this->db->get('um_member')->row();

        $this->_success($request);

    }

    // 회원가입
    public function register(){
        
        $mb_id = $this->input->post('mb_id');
        $mb_password = $this->input->post('mb_password');
        $mb_name = $this->input->post('mb_name');
        $mb_email = $this->input->post('mb_email');
        $mb_phone = $this->input->post('mb_phone');
        
        if(!$mb_id) $this->_error(0,'No ID has been entered.');
        if(!$mb_password) $this->_error(0,'No Password has been entered.');
        if(!$mb_name) $this->_error(0,'No Username has been entered.');
        if(!$mb_email) $this->_error(0,'No Email has been entered.');
        if(!$mb_phone) $this->_error(0,'No PhoneNumber has been entered.');

        $mb_password = $this->_encrypt($mb_password);

        $data = array(
            'mb_id' => $mb_id,
            'mb_password' => $mb_password,
            'mb_name' => $mb_name,
            'mb_email' => $mb_email,
            'mb_phone' => $mb_phone,
            'mb_token' => $this->_encrypt(microtime()),
            'mb_regdate' => date('Y-m-d H:i:s')
        );

        $result = $this->db->insert('um_member', $data);

        if($result) $this->_success($data);
        else $this->_error(0,'It was not handled as a temporary error.');
    }

    
    // 로그인
    public function login(){
		$mb_id		= $this->input->post('mb_id');
		//$mb_token	= $this->input->post('mb_token');
        //$fb_token	= $this->input->post('fb_token');
        $mb_password = $this->input->post('mb_password');
        $password = $this->_encrypt($mb_password);

		/*if($mb_token != "") {
			$this->db->where('mb_token', $mb_token);
			$member = $this->db->get('um_member')->row();
			if(!$member) $this->_error(0,"다른곳에서 로그인하여 로그인이 해제되었습니다. 다시 로그인 후 이용해주세요.");
			else $this->_success($member);
		}*/

		if($mb_id == "")	$this->_error(-2,"Please enter your ID.");
		if($mb_password == "")	$this->_error(-2,"Please enter a password.");
        //if($fb_token == "")	$this->_error("Firebase Token값이 전달되지 않았습니다.");
        
        $this->db->where('mb_id', $mb_id);
        $chk_pass = $this->db->get('um_member')->row();
        if($chk_pass) {
            if($chk_pass->mb_password != $password) $this->_error(-2,'The password you entered is incorrect');
        }

		$this->db->where('mb_id', $mb_id);
        $this->db->where('mb_password',	$password);
        $member = $this->db->get('um_member')->row();

        if($member->mb_status == 'D') $this->_error(-2,"I can't log in as a canceled member.");

        if(!$member) $this->_error(-2,"The password you entered is incorrect.");

		$member->mb_token = $this->_genToken();
        //$member->fb_token = $fb_token;
        $member->last_login_dt = date('Y-m-d H:i:s');


        //$update = array('mb_token' => $member->mb_token, 'fb_token' => $member->fb_token);
        $update = array('mb_token' => $member->mb_token);
		$this->db->where('mb_id', $mb_id);
		$this->db->update('um_member', $update);

		$this->_success($member);		
    }

    // 마이회원추가
    public function addMember(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $mb_id = $member->mb_id;
        $user_id = $this->input->post('user_id');

        if($user_id){

            // user_id 가 이미 등록한 회원이 있는지 확인
            $this->db->where('mb_id', $member->mb_id);
            $this->db->where('user_id', $user_id);
            $mymember = $this->db->get('um_myMember')->row();
            if($mymember) $this->_error(0,'I am already a registered member.');

            $this->db->where('mb_id', $user_id);
            $result_m = $this->db->get('um_member')->row();
            if(!$result_m) $this->_error(0,'This member does not exist.');
        }
        
        $data = array(
            'mb_id' => $mb_id,
            'user_id' => $user_id,
            'user_name' => $result_m->mb_name,
            'um_regdate' => date('Y-m-d')
        );

        $result = $this->db->insert('um_myMember', $data);

        if($result) $this->_success($data);
        else $this->_error(0,'It was not handled as a temporary error.');

    }

    // 마이회원 이름수정
    public function updateMyMember(){

        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);
        
        $um_no = $this->input->post('um_no');
        $user_name = $this->input->post('user_name');
        //$um_no = $this->input->post('um_no');

        $data = array('user_name' => $user_name);
        
        $this->db->where('mb_id', $member->mb_id); 
        $this->db->where('um_no', $um_no);
        //$this->db->where('um_no', $um_no);
        $result = $this->db->update('um_myMember', $data);

        if($result) $this->_success($data);
        else $this->_error(0,'It was not handled as a temporary error.');
    }

    // 마이회원 리스트
    public function myMembers(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $this->db->where('mb_id', $member->mb_id);
        $myMember = $this->db->get('um_myMember');

        $data = array();
        foreach($myMember->result() as $row){
            $data[] = $row;
        }

        $this->_success($data);

    }

    // 마이회원 상대가 나를 등록했을때 리스트
    public function userAddedMe(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $this->db->where('user_id', $member->mb_id);
        $addedMe = $this->db->get('um_myMember');

        $data = array();

        foreach($addedMe->result() as $row){
            $data[] = $row;
        }

        $this->_success($data);

    }

    // 마이회원삭제
    public function deleteMyMember(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $um_no = $this->input->post('um_no');

        $this->db->where('um_no', $um_no);
        $result = $this->db->delete('um_myMember');

        if($result) $this->_success('delete Success!');
        else $this->_error(0,'It was not handled as a temporary error.');

    }
    

    // 회원탈퇴
    public function deleteMember(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);
        
    }


    // 디바이스 추가.
    public function addDevice(){
        
        $mb_token = $this->input->post('mb_token');
        if($mb_token) $member = $this->_getMember($mb_token);
        
        //if (!$member) $this->_error(-1);

        if($mb_token) $mb_id = $member->mb_id;
        else $mb_id = $this->input->post('mb_id');

        $dv_no = $this->input->post('dv_no');
        $dv_name = $this->input->post('dv_name');
        $dv_sirial = $this->input->post('dv_sirial');

        
        if(!$mb_token){
            $device = $this->db->get('um_device');
            foreach($device->result() as $row){
                if(strpos($dv_no, "|") !== false){
                    $dvno_arr = explode('|', $dv_sirial);
                    foreach($dvno_arr as $dRow){
                        if($row->dv_sirial == $dRow){
                        $this->_error(0,'This is the serial number already registered.');
                        }
                    }
                }else{
                    if($row->dv_sirial == $dv_sirial){
                        $this->_error(0,'This is the serial number already registered.');
                    }
                }
            }
        }

        if(strpos($dv_no, "|") !== false){
            $dv_no_arr = explode('|', $dv_no);
            $dv_name_arr = explode('|', $dv_name);
            $dv_sirial_arr = explode('|', $dv_sirial);

            foreach($dv_no_arr as $key => $dvRow){
                if($dvRow){
                    $dv_nameV = $dv_name_arr[$key];
                    $dv_sirialV = $dv_sirial_arr[$key];
                    $updateData = array( 'dv_name' => $dv_nameV, 'dv_sirial' => $dv_sirialV );
                    $this->db->where('dv_no', $dvRow);
                    $result = $this->db->update('um_device',$updateData);
                }else{

                    if($mb_token){
                        $device = $this->db->get('um_device');
                        foreach($device->result() as $row){
                            if(strpos($dv_no, "|") !== false){
                                $dvno_arr = explode('|', $dv_sirial);
                                foreach($dvno_arr as $dRow){
                                    if($row->dv_sirial == $dRow){
                                    //$this->_error(0,'이미 등록되어 있는 시리얼 번호 입니다.');
                                    }
                                }
                            }else{
                                if($row->dv_sirial == $dv_sirial){
                                    $this->_error(0,'This is the serial number already registered.');
                                }
                            }
                        }
                    }

                    $dv_nameV = $dv_name_arr[$key];
                    $dv_sirialV = $dv_sirial_arr[$key];
                    $updateData = array( 'dv_name' => $dv_nameV, 'dv_sirial' => $dv_sirialV, 'mb_id' => $mb_id, 'dv_regdate' => date('Y-m-d H:i:s') );
                    $result = $this->db->insert('um_device',$updateData);
                }
            }

        }else{

            $device = $this->db->get('um_device');
            foreach($device->result() as $row){
                if($row->dv_sirial == $dv_sirial){
                    $this->_error(0,'This is the serial number already registered.');
                }
            }

            $updateData = array( 'dv_name' => $dv_name, 'dv_sirial' => $dv_sirial, 'mb_id' => $mb_id, 'dv_regdate' => date('Y-m-d H:i:s') );
            $result = $this->db->insert('um_device',$updateData);
        }
        
        if($result) $this->_success('The update was handled as normal.');
        else $this->_error(0,'It was not handled as a temporary error.');

    }

    // 디바이스 삭제
    public function delDevice(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $dv_no = $this->input->post('dv_no');
        
        $this->db->where('mb_id', $member->mb_id);
        $this->db->where('dv_no', $dv_no);
        $result = $this->db->delete('um_device');

        if($result) $this->_success('Deletion is complete.');
        else $this->_error(0,'It was not handled as a temporary error.');

    }

    // 디바이스 리스트
    public function listDevice(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $this->db->where('mb_id', $member->mb_id);
        $device = $this->db->get('um_device');

        $data = array();
        foreach($device->result() as $row){
            $data[] = $row;
        }

        $this->_success($data);
    }

    // 디바이스 삭제
    public function deleteDevice(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $dv_no = $this->input->post('dv_no');
        
        $this->db->where('mb_id', $member->mb_id);
        $this->db->delete('um_member');

        $this->_success('success!');

    }

    private function _random_char($length){
        
        $str = 'abcdefghijklmnopqrstuvwxyz0123456789!@#ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($str) - 1;
        $chr = '';
        $len = abs($length);
        for($i=0; $i<$len; $i++) {
            $chr .= $str[random_int(0, $max)];
        }
        return $chr;
    }

    // findPassword
    public function findPassword(){
        
        $this->load->library('email');
        
        $email = $this->input->post('email');

        $this->db->where('mb_email', $email);
        $user = $this->db->get('um_member')->row();

        if($user){
            // 임시비밀번호 생성
            $temp_passwd = $this->_random_char(8);
            $emailUpdate = array();
            $emailUpdate['mb_password'] = $this->_encrypt($temp_passwd);
            $this->db->where('mb_email', $email);
            $this->db->update('um_member', $emailUpdate);

            $this->email->from('james@umain.co.kr','Umain');
            $this->email->to($email);
            $this->email->subject('This is your main password information.');
            $this->email->message('your password : '.$temp_passwd);

            if(!$this->email->send()){
                $this->_error(0,'There was an error sending the mail.');
            }else{
                $this->_success('Temporary password information has been sent to the email you entered.');
            }
        }else{
            $this->_error(0,'There is no matching information.');
        }

    }

    // change password
    public function changePw(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $password = $this->input->post('password');
        $changepw = $this->_encrypt($password);
        
        $data = array('mb_password' => $changepw);

        $this->db->where('mb_no', $member->mb_no);
        $result = $this->db->update('um_member', $data);

        if($result) $this->_success('Password has been modified.');
        else $this->_error(0,'It was not handled as a temporary error.');

    }

    // change email
    public function changeEmail(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $changeEmail = $this->input->post('email');
        
        $data = array('mb_email' => $changeEmail);

        $this->db->where('mb_no', $member->mb_no);
        $result = $this->db->update('um_member', $data);

        if($result) $this->_success('Your email has been modified.');
        else $this->_error(0,'It was not handled as a temporary error.');

    }

    // my Acount 
    public function updateAccount(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $username = $this->input->post('username');
        $userid = $this->input->post('userid');
        $phone = $this->input->post('phone');

        /*
        $members = $this->db->get('um_member');
        foreach($members->result() as $mRow){
            if($mRow->mb_id == $userid) {
                $this->_error(0,'이미 사용하고 있는 아이디 입니다. 다른 아이디로 입력 해주세요.');
                return;
            }
        }
        */

        $data = array(
            'mb_name' => $username,
            'mb_id' => $userid,
            'mb_phone' => $phone
        );

        $this->db->where('mb_no', $member->mb_no);
        $result = $this->db->update('um_member', $data);

        if($result){
            //$myData = array();
            
            $this->db->where('mb_id', $member->mb_id);
            $mb_id_ck = $this->db->get('um_myMember')->row();
            if($mb_id_ck){
                $mb_id_ck_data = array('mb_id'=>$userid);
                $this->db->where('mb_id', $member->mb_id);
                $this->db->update('um_myMember', $mb_id_ck_data);
            }

            $this->db->where('user_id', $member->mb_id);
            $us_id_ck = $this->db->get('um_myMember')->row();
            if($us_id_ck){
                
                $us_id_ck_data = array('user_id'=>$userid);

                $this->db->where('user_id', $member->mb_id);
                $this->db->update('um_myMember', $us_id_ck_data);
            }
        }

        if($result) $this->_success($data);
        else $this->_error(0,'It was not handled as a temporary error. ');

    }


    // fcm update
    public function fcmSetting(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $fcmSet = $this->input->post('fcmSet');
        
        $data = array('mb_fcm' => $fcmSet);

        $this->db->where('mb_no', $member->mb_no);
        $result = $this->db->update('um_member', $data);

        if($result) $this->_success($data);
        else $this->_error(0,'It was not handled as a temporary error.');

    }

    // 회원 중복 체크
    public function checkMember(){
        
        $userid = $this->input->post('userid');
        $email = $this->input->post('email');

        if($userid){
            $this->db->where('mb_id', $userid);
            $member = $this->db->get('um_member')->row();
            
            if($member){ $this->_error(0,'This ID is already in use.'); }
            else { $this->_success('ok'); }
        }

        if($email){
            $this->db->where('mb_email', $email);
            $member = $this->db->get('um_member')->row();
            
            if($member){ $this->_error(0,'This Email is already in use.'); }
            else { $this->_success('ok'); }
        }
        
    }

    // 회원탈퇴
    public function deleteAccount(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $data = array('mb_status' => 'D');

        $this->db->where('mb_no', $member->mb_no);
        $result = $this->db->update('um_member', $data);

        $this->db->where('mb_id', $member->mb_id);
        $this->db->or_where('user_id', $member->mb_id);
        $this->db->delete('um_myMember');

        if($result) $this->_success($data);
        else $this->_error(0,'It was not handled as a temporary error.');

    }


    // 데이터 리스트
    public function listDate(){

        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);
        
        $mb_id = $member->mb_id;
        $device = $this->input->post('device');
        $date = $this->input->post('date');

        if(!$device){
            $this->db->where('mb_id', $mb_id);
            $this->db->order_by('dv_no','asc');
            $deviceA = $this->db->get('um_device')->row();
            $deviceName = $deviceA->dv_name;
        }else{
            $deviceName = $device;
        }

        if(!$date){
            $todayD = date('Y-m-d');
            $this->db->where('mb_id', $mb_id);
            $this->db->where(" data_regdate LIKE '%{$todayD}%' ", NULL, FALSE);
            $dateD = $this->db->get('um_data')->row();
            if(!$dateD){
                $this->db->where('mb_id', $mb_id);
                $this->db->where('dv_name', $deviceName);
                $this->db->order_by('data_regdate', 'desc');
                $dataS = $this->db->get('um_data')->row();
                $lastDate = $dataS->data_regdate;
                $lastDateA = explode(' ', $lastDate);
                $today = $lastDateA[0];
            }
        }else{
            $today = $date;
        }

        $this->db->where('mb_id', $mb_id);
        $this->db->where('dv_name', $deviceName);
        if($date) $this->db->where(" data_regdate LIKE '%{$date}%' ", NULL, FALSE);
        else $this->db->where(" data_regdate LIKE '%{$today}%' ", NULL, FALSE);
        $this->db->order_by('data_regdate', 'asc');
        $dataL = $this->db->get('um_data');
        
        $data = array();
        foreach($dataL->result() as $row){
            $data[] = $row;
        }

        $this->_success($data);

    }

    // my device list
    public function myDeviceList(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $this->db->where('mb_id', $member->mb_id);
        $list = $this->db->get('um_device');

        $data = array();
        foreach($list->result() as $row){
            $data[] = $row;
        }

        $this->_success($data);

    }
    
    
    // 데이터 전송API
    public function sendData(){

        $this->output->set_content_type('text/json');

        $json = file_get_contents("php://input");
        $json = stripslashes($json);
        $json = json_decode($json);

        // A2|2020-05-13|10:37:09|715|0

        $device = $json->device;
        $date = $json->date;
        $time = $json->time;
        $mode = $json->move;
        $breath = $json->breath;

        $dataA = array(
            'dv_sirial' => $device,
            'dt_date' => $date,
            'dt_time' => $time,
            'dt_move' => $mode,
            'dt_breath' => $breath,
            'dt_regdate' => $date.' '.$time
        );

        $result = $this->db->insert('um_rawdata', $dataA);
        
        if($result) $this->_success('정상적으로 처리되었습니다.');
        else $this->_error(0,'일시적인 오류로 처리되지 않았습니다.');

    }

    // 내 디바이스 정보 디폴트
    public function getDeviceDefault(){
        
        $mb_token = $this->input->post('mb_token');
        $member = $this->_getMember($mb_token);
        if (!$member) $this->_error(-1);

        $mb_id = $member->mb_id;
        
        $this->db->where('mb_id', $mb_id);
        $this->db->order_by('dv_regdate','desc');
        $device = $this->db->get('um_device');

        $data = array();
        foreach($device->result() as $row){
            $data= $row;

            $this->db->where('dv_sirial', $row->dv_sirial);
            $this->db->order_by('dt_regdate', 'desc');
            $last = $this->db->get('um_rawdata')->row();
            if($last) $lastDay = explode(' ', $last->dt_regdate);

            if($last) $row->lastDay = $lastDay[0];

        }

        $this->_success($data);

    }


    // 그래프 데이터 확인
    public function getData2(){
        
        $mb_id = $this->input->post('mb_id');
        $dv_name = $this->input->post('device');
        $date = $this->input->post('date');
        if($date){
            $dateA = explode(' ',$date);
            $dateD = $dateA[0];
        }else{
            //$dateD = date('Y-m-d');
            $dateD = '2020-05-16';
        }

        $dateED = strtotime($dateD."+1 days");
        $dateE = date('Y-m-d', $dateED);

        
        $this->db->where('mb_id', $mb_id);
        if($dv_name) $this->db->where('dv_name', $dv_name);
        $this->db->order_by('dv_regdate','asc');
        $device = $this->db->get('um_device')->row();

        if($device) $sirial = $device->dv_sirial;

        $this->db->select(' 
        FROM_UNIXTIME(FLOOR((UNIX_TIMESTAMP(dt_regdate))/1800)*1800) as time, 
        count(*) as ctn, 
        count(if((dt_move > 0)&&(dt_breath = 0)&&(dt_regdate <= "'.$dateD.' 20:00:00"), dt_move, null)) as highActivity,
        count(if((dt_move > 0)&&(dt_breath > 0)&&(dt_regdate <= "'.$dateD.' 20:00:00"), dt_move, null)) as LowActivity,
        count(if((dt_move = 0)&&(dt_breath > 0)&&(dt_regdate >= "'.$dateD.' 20:00:00"), dt_move, null)) as DeepSleep,
        count(if((dt_move > 0)&&(dt_breath > 0)&&(dt_regdate >= "'.$dateD.' 20:00:00"), dt_move, null)) as LowSleep
        ');
        $this->db->where('dv_sirial', $sirial);
        $this->db->where(" (dt_regdate >= '{$dateD} 08:00:00') AND (dt_regdate <= '{$dateE} 08:00:00') ");
        $this->db->group_by('time'); 
        $this->db->order_by('dt_regdate', 'asc');
        $getData = $this->db->get('um_rawdata');

        $dataD = array(
            'highActivity' => '',
            'LowActivity' => '',
            'DeepSleep' => '',
            'LowSleep' => '',
            'hAC' => '',
            'lAC' => '',
            'dSC' => '',
            'lSC' => '',
            'graph2' => '',
            'graph3' => ''
        );

        $hACSum = '';
        $lACSum = '';
        $dSCSum = '';
        $lSCSum = '';

        foreach($getData->result() as $row1){
            $dataD['list'][] = $row1;
            $timeS = $row1->time;
            $timsA = explode(' ', $timeS);
            $timeA = explode(':', $timsA[1]);
            $time = $timeA[0].':'.$timeA[1];

            $dataD['time'][]['label'] = $time;

            $dataD['highActivity'][]['value'] = $row1->highActivity;
            $dataD['LowActivity'][]['value'] = $row1->LowActivity;
            $dataD['DeepSleep'][]['value'] = '-'.$row1->DeepSleep;
            $dataD['LowSleep'][]['value'] = '-'.$row1->LowSleep;

            $hACSum += $row1->highActivity;
            $lACSum += $row1->LowActivity;
            $dSCSum += $row1->DeepSleep;
            $lSCSum += $row1->LowSleep;

        }

        $dataD['hAC'] = $hACSum;
        $dataD['lAC'] = $lACSum;
        $dataD['dSC'] = $dSCSum;
        $dataD['lSC'] = $lSCSum;

        $HighActivity = array('label'=>'High Activity', 'color'=>'#4aa1f0', 'value' => $hACSum);
        $LowActivity = array('label'=>'Low Activity', 'color'=>'#447ab6', 'value' => $lACSum);
        $dataD['graph2'][0] = $HighActivity;
        $dataD['graph2'][1] = $LowActivity;

        //$HighActivity = array('label'=>'Sleep', 'color'=>'#81bdf1', 'value' => $hACSum);
        //$LowActivity = array('label'=>'Low Activity', 'color'=>'#447ab6', 'value' => $lACSum);
        //$dataD['graph3'][0] = $HighActivity;
        //$dataD['graph3'][1] = $LowActivity;

        $this->_success($dataD);

    }



    public function getData(){
        
        $mb_id = $this->input->post('mb_id');
        $dv_name = $this->input->post('device');
        $date = $this->input->post('date');

        // 디바이스 찾기        
        $this->db->where('mb_id', $mb_id);
        if($dv_name) $this->db->where('dv_name', $dv_name);
        $this->db->order_by('dv_regdate','asc');
        $device = $this->db->get('um_device')->row();

        if($device) $sirial = $device->dv_sirial;


        // 날짜 찾기
        if($date){
            $dateA = explode(' ',$date);
            $dateD = $dateA[0];
        }else{
            $dateD = date('Y-m-d');
            
            $this->db->where('dv_sirial', $sirial);
            $this->db->where(" dt_regdate LIKE '%{$dateD}%' ", NULL, FALSE);
            $sameD = $this->db->get('um_rawdata')->row();
            if($sameD){
                //$dateDA = $dateD;
            }else{
                $this->db->where('dv_sirial', $sirial);
                $this->db->order_by('dt_regdate', 'DESC');
                $Dates = $this->db->get('um_rawdata')->row();
                $dateDA = $Dates->dt_regdate;
                $dateDAC = explode(' ', $dateDA);
                $dateD = $dateDAC[0];
            }

        }

        $dateED = strtotime($dateD."+1 days");
        $dateE = date('Y-m-d', $dateED);


        $this->db->select(' 
        FROM_UNIXTIME(FLOOR((UNIX_TIMESTAMP(dt_regdate))/1800)*1800) as time, 
        count(*) as ctn, 
        count(if((dt_move > 0)&&(dt_breath = 0)&&(dt_regdate <= "'.$dateD.' 20:00:00"), dt_move, null)) as highActivity,
        count(if((dt_move > 0)&&(dt_breath > 0)&&(dt_regdate <= "'.$dateD.' 20:00:00"), dt_move, null)) as LowActivity,
        count(if((!dt_move)&&(!dt_breath)&&(dt_regdate <= "'.$dateD.' 20:00:00"), dt_move, null)) as Absence,
        count(if((dt_move = 0)&&(dt_breath > 0)&&(dt_regdate >= "'.$dateD.' 20:00:00"), dt_move, null)) as DeepSleep,
        count(if((dt_move > 0)&&(dt_breath > 0)&&(dt_regdate >= "'.$dateD.' 20:00:00"), dt_move, null)) as LowSleep,
        dt_regdate
        ');
        $this->db->where('dv_sirial', $sirial);
        $this->db->where(" (dt_regdate >= '{$dateD} 08:00:00') AND (dt_regdate <= '{$dateE} 08:00:00') ");
        $this->db->group_by('time'); 
        $this->db->order_by('dt_regdate', 'asc');
        $getData = $this->db->get('um_rawdata');


        $this->db->select(' 
        FROM_UNIXTIME(FLOOR((UNIX_TIMESTAMP(dt_regdate))/300)*300) as time, 
        count(*) as ctn, 
        count(if((dt_move > 0)&&(dt_breath > 0)&&((dt_breath < dt_move))&&(dt_regdate >= "'.$dateD.' 20:00:00"), dt_move, null)) as Wakefulness,
        count(if((dt_move > 0)&&(dt_breath > 0)&&((dt_breath > dt_move))&&(dt_regdate >= "'.$dateD.' 20:00:00"), dt_move, null)) as LightSleep,
        count(if((dt_move > 0)&&(dt_breath > 0)&&(dt_regdate >= "'.$dateD.' 20:00:00"), dt_move, null)) as Activity,
        dt_regdate
        ');
        $this->db->where('dv_sirial', $sirial);
        $this->db->where(" (dt_regdate >= '{$dateD} 08:00:00') AND (dt_regdate <= '{$dateE} 08:00:00') ");
        $this->db->group_by('time'); 
        $this->db->order_by('dt_regdate', 'asc');
        $getData2 = $this->db->get('um_rawdata');

        $dataD = array(
            'highActivity' => '',
            'LowActivity' => '',
            'DeepSleep' => '',
            'hAC' => '',
            'lAC' => '',
            'AbsC' => '',
            'dSC' => '',
            'lSC' => '',
            'wakeC' => '',
            'lightC' => '',
            'activity' => '',
            'graph2' => '',
            'graph3' => '',
            
            'hAT' => '',
            'lAT' => '',
            'AbsT' => '',
            'dST' => '',
            'lST' => '',
            'wakeT' => '',
            'activityT' => ''
        );

        $hACSum = '';
        $lACSum = '';
        $AbsC = '';
        $dSCSum = '';
        $lSCSum = '';
        $wakeC = '';
        $lightC = '';
        $activity = '';

        $wakeT = 0;
        $lST = 0;
        $actT = 0;
        
        foreach($getData2->result() as $row2){

            $wakeC += $row2->Wakefulness;
            $lightC += $row2->LightSleep;
            $activity += $row2->Activity;

            if($row2->Wakefulness){ $wakeT += 1; }
            if($row2->LightSleep){ $lST += 1; }
            if($row2->Activity){ $actT += 1; }
        }

        $hAT = 0;
        $lAT = 0;
        $dST = 0;
        $AbsT = 0;
        
        foreach($getData->result() as $row1){
            $dataD['list'][] = $row1;
            $timeS = $row1->time;
            $timsA = explode(' ', $timeS);
            $timeA = explode(':', $timsA[1]);
            $time = $timeA[0].':'.$timeA[1];

            $dataD['time'][]['label'] = $time;

            $dataD['highActivity'][]['value'] = $row1->highActivity;            
            $dataD['LowActivity'][]['value'] = $row1->LowActivity;
            $dataD['DeepSleep'][]['value'] = '-'.$row1->DeepSleep;
            $dataD['LightSleep'][]['value'] = '-'.$row1->LowSleep;

            if($row1->highActivity){ $hAT += 1; }
            if($row1->LowActivity){ $lAT += 1; }
            if($row1->DeepSleep){ $dST += 1; }
            if($row1->Absence){ $AbsT += 1; }
            //if($row1->LowSleep){ $lST += 1; }

            $hACSum += $row1->highActivity;
            $lACSum += $row1->LowActivity;
            $AbsC += $row1->Absence;
            $dSCSum += $row1->DeepSleep;
            $lSCSum += $row1->LowSleep;
        }

        $hATD = round(($hAT * 30)/60, 1);
        $lATD = round(($lAT * 30)/60, 1);
        $dSTD = round(($dST * 30)/60, 1);
        $wakeTD = round(($wakeT * 5)/60, 1);
        $lSTD = round(($lST * 5)/60, 1);
        $AbsD = round(($AbsT * 30)/60, 1);
        $activityD = round(($activity * 5)/60, 1);

        // highActivity 시간
        if(strpos($hATD, ".") !== false){
            $hATDArr = explode('.', $hATD);
            $hATD_1 = $hATDArr[0];
            $hATD_2 = $hATDArr[1];
            if($hATD_2 > 5){
                $hATD_1 = $hATD_1 + 1;
                $hATD_2 = $hATD_2 - 6;
            }
            $hATDS = $hATD_1.' hours '.$hATD_2.'0 minutes';
        }else{
            if($hATD) $hATDS = $hATD.' hours';
            else $hATDS = '';
        }
        // LowActivity 시간
        if(strpos($lATD, ".") !== false){
            $lATDArr = explode('.', $lATD);
            $lATD_1 = $lATDArr[0];
            $lATD_2 = $lATDArr[1];
            if($lATD_2 > 5) {
                $lATD_1 = $lATD_1 + 1;
                $lATD_2 = $lATD_2 - 6;
            }
            $lATDS = $lATD_1.' hours '.$lATD_2.'0 minutes';
        }else{
            if($lATD) $lATDS = $lATD.' hours';
            else $lATDS = '';
        }
        // Absence 시간
        if(strpos($AbsD, ".") !== false){
            $AbsDArr = explode('.', $AbsD);
            $AbsD_1 = $AbsDArr[0];
            $AbsD_2 = $AbsDArr[1];
            if($AbsD_2 > 5) {
                $AbsD_1 = $AbsD_1 + 1;
                $AbsD_2 = $AbsD_2 - 6;
            }
            $AbsDS = $AbsD_1.' hours '.$AbsD_2.'0 minutes';
        }else{
            if($AbsD) $AbsDS = $AbsD.' hours';
            else $AbsDS = '';
        }
        // DeepSleep 시간
        if(strpos($dSTD, ".") !== false){
            $dSTDArr = explode('.', $dSTD);
            $dSTD_1 = $dSTDArr[0];
            $dSTD_2 = $dSTDArr[1];
            if($dSTD_2 > 5) {
                $dSTD_1 = $dSTD_1 + 1;
                $dSTD_2 = $dSTD_2 - 6;
            }
            $dSTDS = $dSTD_1.' hours '.$dSTD_2.'0 minutes';
        }else{
            if($dSTD) $dSTDS = $dSTD.' hours';
            else $dSTDS = '';
        }
        // Wakefulness 시간
        if(strpos($wakeTD, ".") !== false){
            $wakeTDArr = explode('.', $wakeTD);
            $wakeTD_1 = $wakeTDArr[0];
            $wakeTD_2 = $wakeTDArr[1];
            if($wakeTD_2 > 5) {
                $wakeTD_1 = $wakeTD_1 + 1;
                $wakeTD_2 = $wakeTD_2 - 6;
            }
            $wakeTDS = $wakeTD_1.' hours '.$wakeTD_2.'0 minutes';
        }else{
            if($wakeTD) $wakeTDS = $wakeTD.' hours';
            else $wakeTDS = '';
        }
        // activity 시간
        if(strpos($activityD, ".") !== false){
            $activityDArr = explode('.', $activityD);
            $activityD_1 = $activityDArr[0];
            $activityD_2 = $activityDArr[1];
            if($activityD_2 > 5) {
                $activityD_1 = $activityD_1 + 1;
                $activityD_2 = $activityD_2 - 6;
            }
            $activityDS = $activityD_1.' hours '.$activityD_2.'0 minutes';
        }else{
            if($activityD) $activityDS = $activityD.' hours';
            else $activityDS = '';
        }
        // 
        if(strpos($lSTD, ".") !== false){
            $lSTDArr = explode('.', $lSTD);
            $lSTD_1 = $lSTDArr[0];
            $lSTD_2 = $lSTDArr[1];
            if($lSTD_2 > 5) {
                $lSTD_1 = $lSTD_1 + 1;
                $lSTD_2 = $lSTD_2 - 6;
            }
            $lSTDS = $lSTD_1.' hours '.$lSTD_2.'0 minutes';
        }else{
            if($lSTD) $lSTDS = $lSTD.' hours';
            else $lSTDS = '';
        }
        
        $dataD['hAT'] = $hATDS;
        $dataD['lAT'] = $lATDS;
        $dataD['AbsT'] = $AbsDS;
        $dataD['dST'] = $dSTDS;
        $dataD['wakeT'] = $wakeTDS;
        $dataD['lST'] = $lSTDS;
        $dataD['activityT'] = $activityDS;

        $dataD['hAC'] = $hACSum.' ( High Activity )';
        $dataD['lAC'] = $lACSum.' ( Low Activity )';
        $dataD['AbsC'] = $AbsC.' ( Absence )';
        $dataD['dSC'] = $dSCSum.' ( DeepSleep )';
        $dataD['lSC'] = $lSCSum.' ( LowSleep )';

        $dataD['wakeC'] = $wakeC.' ( Wakefulness )';
        $dataD['lightC'] = $lightC.' ( LightSleep )';
        $dataD['activity'] = $activity.' ( Activity )';

        $HighActivity = array('label'=>'High Activity', 'color'=>'#4aa1f0', 'value' => $hACSum);
        $LowActivity = array('label'=>'Low Activity', 'color'=>'#447ab6', 'value' => $lACSum);
        $Absence = array('label'=>'Absence', 'color'=>'#81bdf1', 'value' => $AbsC);
        
        $dataD['graph2'][0] = $HighActivity;
        $dataD['graph2'][1] = $LowActivity;
        $dataD['graph2'][2] = $Absence;

        $DeepSleep = array('label'=>'DeepSleep', 'color'=>'#81bdf1', 'value' => $dSCSum);
        $LowSleep = array('label'=>'LowSleep', 'color'=>'#447ab6', 'value' => $lightC);
        $wakeCS = array('label'=>'Wakefulness', 'color'=>'#c8daff', 'value' => $wakeC);
        $Activity = array('label'=>'Activity', 'color'=>'#4aa1f0', 'value' => $activity);
        
        $dataD['graph3'][0] = $DeepSleep;
        $dataD['graph3'][1] = $LowSleep;
        $dataD['graph3'][2] = $wakeCS;
        $dataD['graph3'][3] = $Activity;
        

        $this->_success($dataD);

    }


    // 그래프 데이터 생성
    public function insertGraphData(){

        $startDate = date('2020-05-15 08:00:00');

        $data = array();
        $data['dv_sirial'] = 'A2';
        $data['dt_move'] = '300';
        $data['dt_breath'] = '0';

        $k = 3;
        for($i = 1; $i < 62800; $i++){

            $k += 3;

            $addT = strtotime($startDate."+{$k} seconds");
            $addTD = date('Y-m-d H:i:s', $addT);
            $TDA = explode(' ', $addTD);
            $dt_date = $TDA[0];
            $dt_time = $TDA[1];
            
            $data['dt_date'] = $dt_date;
            $data['dt_time'] = $dt_time;

            $data['dt_regdate'] = $dt_date.' '.$dt_time;

            $this->db->insert('um_rawdata', $data);
        }

        $this->_success('그래프 데이터가 생성되었습니다.');

    }


    // 소켓통신 
    public function umainServer(){
        
        $address = "192.168.3.1";
        $port = 22; // 접속할 PORT //
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); 
        if ($socket === false) {
        echo "socket_create() 실패! 이유: " . socket_strerror(socket_last_error()) . "\n";
        echo "<br>";
        } else {
        echo "socket 성공적으로 생성.\n";
        echo "<br>";
        //socket_write
        //socket_write($socket, 'test');
        //socket_close($socket);
        }

    }


    // json 결과
    public function _success($data = array()) {
        die(json_encode(array('status' => 1, 'msg' => "", 'data' => $data)));
    }

    public function _error($status = 0, $msg = '') {
        if($status == -1 && !$msg) $msg = "Login is required!";
        die(json_encode(array('status' => $status, 'msg' => $msg, 'data' => array())));
    }	

    private function _encrypt($str) {
        return hash('sha256', $str);
    }

	private function _genToken() {
		return $this->_encrypt(microtime());
	}

    public function _getMemberById($mb_id){
        $this->db->where('mb_id', $mb_id);
        $member = $this->db->get('um_member')->row();

        return $member;
    }

    public function _getMember($mb_token) {
        $this->db->where('mb_token', $mb_token);
        $member = $this->db->get('um_member')->row();

        return $member;
    }
    
}
