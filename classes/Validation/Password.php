<?php
class AccuaForm_Validation_Password extends Validation {
	public function __construct($message = "") {
		if(empty($message)) {
			$this->message = __("Attention: Your passwords do not match.", 'accua-form-api');
		} else {
			$this->message = $message;
		}
	}

	public function isValid($value) {
		$valid = false;
		
		
		foreach ($_POST as $key => $value) 
			if(preg_match("/___([a-zA-Z0-9-_]+)___confirmpass/i",$key,$matches))
			{
				$slug_password=$matches[1];
				$value_confirm_pass=$value;
				break;
			}
		  
		 foreach ($_POST as $key => $value) 
			if($key==$slug_password)
				$value_pass=$value;
		
		if($value_pass == $value_confirm_pass){ 
			$valid = true; }
		return $valid;	
	}
}
