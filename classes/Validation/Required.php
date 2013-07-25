<?php
class AccuaForm_Validation_Required extends Validation_Required {
  public function __construct($message = "") {
    if(empty($message)) {
      $this->message = __("Error: '%element%' is a required field.", 'accua-form-api');
    } else {
      $this->message = $message;
    }
  }
}
