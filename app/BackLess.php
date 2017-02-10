<?php
	/**
	* 	BackLess Project
	* 	Author: Diogo Cezar Teixeira Batista
	*	Year: 2017
	*/
	require_once "./vendor/autoload.php";
	class BackLess{
		/**
		* Attribute to store configs json location
		*/
		private static $configs_url = "./configs/configs.json";
		/**
		* Attribute to store an instance of PageBuilder
		*/
		private static $instance = null;
		/**
		* Attribute to store configurations
		*/
		private static $configs = null;
		/**
		* Attribute to store http origin
		*/
		private static $origin = null;
		/**
		* Private constructor to prevent direct criation
		*/
		private function __construct(){}
		/**
		* Method that returns an instance
		*/
		public static function __get_instance(){
			if (!isset(BackLess::$instance) && is_null(BackLess::$instance)) {
				$c = __CLASS__;
				BackLess::$instance = new $c;
			}
			BackLess::$configs = (array) json_decode(file_get_contents(BackLess::$configs_url), true);
			BackLess::$origin  = BackLess::__get_http_origin();
			return BackLess::$instance;
		}
		/**
		* Check all requisites to active api
		*/
		private function __allowed(){
			$result = array();
			if(!$this->__check_origin()){
				$result[] = BackLess::$configs['messages']['origin-no-allowed'];
			}
			if(!$this->__check_token()){
				$result[] = BackLess::$configs['messages']['token-no-match-or-no-exists'];
			}
			if(!$this->__check_data()){
				$result[] = BackLess::$configs['messages']['data-is-empty'];
			}
			return $result;
		}
		/**
		* Method get HTTP Origin
		*/
		public static function __get_http_origin(){
			if(!empty($_SERVER['HTTP_ORIGIN'])){
				$origin = $_SERVER['HTTP_ORIGIN'];
				if(eregi('http://', $origin))
					$origin = str_replace('http://', '', $origin);
				if(eregi('https://', $origin))
					$origin = str_replace('https://', '', $origin);
				return $origin;
			}
			else
				return false;
		}
		/**
		* Generate token by origin and key
		*/
		private function __encode_key($host = ""){
			if(!empty($host))
				$origin = $host;
			else
				$origin = BackLess::$origin;
			$reverse_origin = strrev($origin);
			$str            = $origin . BackLess::$configs['configs']['key'];
			return md5($str);
		}
		/**
		* Check if post have data
		*/
		private function __check_data(){ return (!empty($_POST['data'])) ? true : false; }
		/**
		* Check if origin is valid
		*/
		private function __check_origin(){
			if(!empty(BackLess::$origin))
				return array_key_exists(BackLess::$origin, BackLess::$configs['allowed']);
			else
				return false;
		}
		/**
		* Check if token is not empty and valid
		*/
		private function __check_token(){
			if(empty($_POST['token']))
				return false;
			$token = $this->__encode_key();
			return ($this->__encode_key() == $_POST['token']);
		}
		/**
		* Json Response
		*/
		private function __response($json){ echo json_encode($json); }
		/**
		* Method to send email
		*/
		public function systemSendMail($emails, $email, $content, $subject, $from, $log){
			$sendgrid = new SendGrid(BackLess::$configs['configs']['mail']['mail_sendgrid_key']);
			$mail     = new SendGrid\Email();
			foreach ($emails as $name => $email) {
				$mail->addTo($email);
			}
			$mail->setFrom($from)
				 ->setSubject($subject)
				 ->setHtml($content);
			try {
				$sendgrid->send($mail);
				$return['msg']     = BackLess::$configs['messages']['mail-success'];
	    		$return['success'] = "true";
	    		$return['log']     = (string) $log;
		    	$this->__response($return);
			}
			catch(\SendGrid\Exception $e) {
				$code = $e->getCode();
				foreach($e->getErrors() as $er) {
				    $errors[] = $er;
				}
				$return['msg']     = BackLess::$configs['messages']['mail-error'];
				$return['erros']   = $errors;
				$return['success'] = "false";
				$return['log']     = (string) $log;
		    	$this->__response($return);
			}
		}
		/**
		* Add Dinamic Lines to Template
		*/
		private function addLinesTemplate($content, $fields){
			$i   = 0;
			$str = "";
			foreach ($fields as $value) {
				$str .= "<tr><td style=\"padding: 10px; border-right:1px solid #e6e9ea; border-bottom:1px solid #e6e9ea; text-align: center; font-size:12px;font-family: Verdana, Geneva, sans-serif;color:#244e53;font-weight:normal; text-transform: uppercase;\">".$value."</td><td style=\"padding: 10px; border-bottom:1px solid #e6e9ea; text-align: center; font-size:12px;font-family: Verdana, Geneva, sans-serif;color:#244e53;font-weight:normal;\">{".$value."}</td></tr>";
				$i++;
			}
			return str_replace("{fields}", $str, $content);
		}
		/**
		* Return Email Template
		*/
		private function getTemplate($replaces, $fields){
			$file = BackLess::$configs['allowed'][BackLess::$origin]['mail']['template'];
			if(is_file($file)){
				$content = file_get_contents($file);
				$content = $this->addLinesTemplate($content, $fields);
				for($i=0;$i<count($replaces);$i++)
					$content = str_replace("{".$replaces[$i]['key']."}", $replaces[$i]['value'], $content);
				return $content;
			}
		}
		/**
		* Send Mail
		*/
		public function sendMail(){
			$allowed = $this->__allowed();
			if(!empty($allowed)){
				$return = array('success' => 'false', 'message' => $allowed);
				$this->__response($return);
				return;
			}
			$fields = $_POST['data']['fields'];
			$values = $_POST['data']['values'];
			if(count($fields) != count($values)){
				$return = array('success' => 'false', 'message' => BackLess::$configs['messages']['sizes-not-match']);
				$this->__response($return);
				return;
			}
			if(!in_array('email', $fields)){
				$return = array('success' => 'false', 'message' => BackLess::$configs['messages']['field-email-required']);
				$this->__response($return);
				return;
			}
			$i = 0;
			foreach ($fields as $value) {
				$replaces[] = array('key' => $value, 'value' => $values[$i]);
				$i++;
			}
			$host_configs = BackLess::$configs['allowed'][BackLess::$origin];
			$replaces[] = array('key' => 'logo',  'value'  => $host_configs['mail']['logo']);
			$replaces[] = array('key' => 'color', 'value'  => $host_configs['mail']['color']);
			$replaces[] = array('key' => 'line_1', 'value' => $host_configs['mail']['line_1']);
			$replaces[] = array('key' => 'line_2', 'value' => $host_configs['mail']['line_2']);
			$content = $this->getTemplate($replaces, $fields);
			$emails  = $host_configs['mail']['to'];
			$subject = $host_configs['mail']['subject'];
			$from    = $host_configs['mail']['from'];
			$email   = $values[array_search('email', $fields)];
			$log     = $this->saveLog('sendEmail', $_POST['token'], BackLess::$origin);
			$this->systemSendMail($emails, $email, $content, $subject, $from, $log);
		}
		/**
		* Generate Token Tool
		*/
		public function generateToken(){
			if(!empty($_GET['host']))
				$return = array('success' => 'true', 'token' => $this->__encode_key($_GET['host']));
			else
				$return = array('success' => 'false', 'message' => BackLess::$configs['messages']['invalid-host']);
			$this->__response($return);
		}

		/**
		* Save Api Log
		*/
		public function saveLog($type = "", $token = "", $origin = ""){
			try{
				if(BackLess::$configs['configs']['logs'] == true){
					$log = array('type' => $type, 'token' => $token, 'origin' => $origin, 'date' => date('d-m-Y H:i:s'));
					file_put_contents(BackLess::$configs['configs']['dir']['access_log'], "\n".json_encode($log), FILE_APPEND);
				}
				return true;
			}
			catch(Exception $e){
				return false;
			}
		}
	}//BackLess
	if(isset($_GET['method'])){
		$method = $_GET['method'];
		if(!empty($method)){
			$instance = BackLess::__get_instance();
			$instance->{$method}();
		}
	}
?>