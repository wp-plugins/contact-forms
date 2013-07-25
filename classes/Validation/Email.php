<?php
class AccuaForm_Validation_Email extends Validation_Email {
	public function __construct($message = "") {
	  if(empty($message)) {
	    $this->message = __("Error: '%element%' must contain an email address.", 'accua-form-api');
	  } else {
	    $this->message = $message;
	  }
	}
}
