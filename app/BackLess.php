<?php

	/**
	* 	BackLess Project
	* 	Author: Diogo Cezar Teixeira Batista
	*	Year: 2017
	*/

	require_once "./vendor/autoload.php";

	class BackLess{

		/**
		* Attribute to store database location
		*/
		private static $database = "./data/database.json";

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
		* Check all requisites to active api
		*/
		private function __allowed(){
			$result = array();
			if(!$this->__check_origin()){
				$result[] = "Origin of call is not allowed.";
			}
			if(!$this->__check_token()){
				$result[] = "Token no match or not exists.";
			}
			if(!$this->__check_data()){
				$result[] = "Data is empty.";
			}
			return $result;
		}

		/**
		* Check if post have data
		*/
		private function __check_data(){ return (!empty($_POST['data'])) ? true : false; }

		/**
		* Check if origin is valid
		*/
		private function __check_origin(){
			return array_key_exists(BackLess::$origin, BackLess::$configs['allowed']);
		}

		/**
		* Check if token is not empty and valid
		*/
		private function __check_token(){
			if(empty($_POST['token']))
				return false;
			$host  = strrev(BackLess::$origin);
			$check = md5($host . "<your-key>");
			return ($check == $_POST['token']);
		}

		/**
		* Method that returns an instance
		*/
		public static function getInstance(){
			if (!isset(BackLess::$instance) && is_null(BackLess::$instance)) {
				$c = __CLASS__;
				BackLess::$instance = new $c;
			}
			BackLess::$configs = (array) json_decode(file_get_contents(BackLess::$database), true);
			BackLess::$origin  = BackLess::getHttpOrigin();
			return BackLess::$instance;
		}

		/**
		* Method to send email
		*/
		public function systemSendMail($emails, $email, $content, $subject, $from){
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
				$return['msg']     = BackLess::$configs['configs']['mail']['mail_success'];
	    		$return['success'] = "true";
		    	$this->response($return);
			}
			catch(\SendGrid\Exception $e) {
				$code = $e->getCode();
				foreach($e->getErrors() as $er) {
				    $errors[] = $er;
				}
				$return['msg']     = BackLess::$configs['configs']['mail']['mail_error'];
				$return['erros']   = $errors;
				$return['success'] = "false";
		    	$this->response($return);
			}
		}

		public static function getHttpOrigin(){
			$origin = $_SERVER['HTTP_ORIGIN'];
			if(eregi('http://', $origin))
				$origin = str_replace('http://', '', $origin);
			if(eregi('https://', $origin))
				$origin = str_replace('https://', '', $origin);
			return $origin;
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
		* Json Response
		*/
		private function response($json){ echo json_encode($json);	}

		/**
		* Generate Token
		*/
		public function generateToken(){
			if(!empty($_GET['host'])){
				$host  = strrev($_GET['host']);
				$check = md5($host . "<your-key>");
				$return = array('success' => 'true', 'token' => $check);
			}
			else{
				$return = array('success' => 'false', 'message' => 'Invalid Host.');
			}
			$this->response($return);
		}

		/**
		* Send Mail
		*/
		public function sendMail(){

			$allowed = $this->__allowed();
			if(!empty($allowed)){
				$return = array('success' => 'false', 'message' => $allowed);
				$this->response($return);
				return;
			}

			$fields = $_POST['data']['fields'];
			$values = $_POST['data']['values'];

			if(count($fields) != count($values)){
				$return = array('success' => 'false', 'message' => 'Size of values not match with fields.');
				$this->response($return);
				return;
			}

			if(!in_array('email', $fields)){
				$return = array('success' => 'false', 'message' => 'Field email is required.');
				$this->response($return);
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

			$this->systemSendMail($emails, $email, $content, $subject, $from);
		}
	}//BackLess

	if(isset($_GET['method'])){
		$method = $_GET['method'];
		if(!empty($method)){
			$instance = BackLess::getInstance();
			$instance->{$method}();
		}
	}
?>