<?php
class AccuaForm_Element_Captcha extends Element {
	protected $privateKey = "6LcazwoAAAAAAD-auqUl-4txAK3Ky5jc5N3OXN0_";
	protected $publicKey = "6LcazwoAAAAAADamFkwqj5KN1Gla7l4fpMMbdZfi";

	public function __construct($label = "", array $properties = null) {
		parent::__construct($label, "recaptcha_response_field", $properties);
		$this->setValidation(new Validation_Captcha($this->privateKey, __("The reCATPCHA response provided was incorrect.  Please re-try.", 'accua-form-api')));
	}	

	public function render() {
	  require_once(dirname(__FILE__) . "/../../PFBC/Resources/recaptchalib.php");
		echo recaptcha_get_html($this->publicKey);
	}
}
