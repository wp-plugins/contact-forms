<?php
class AccuaForm_Element_Captcha extends Element {
	protected $privateKey = "6LcazwoAAAAAAD-auqUl-4txAK3Ky5jc5N3OXN0_";
	protected $publicKey = "6LcazwoAAAAAADamFkwqj5KN1Gla7l4fpMMbdZfi";

	public function __construct($label = "", array $properties = null) {
		parent::__construct($label, "recaptcha_response_field", $properties);
		$this->setValidation(new Validation_Captcha($this->privateKey, __("The reCATPCHA response provided was incorrect.  Please re-try.", 'accua-form-api')));
	}	

	public function render() {
	  $lang = $this->form->getLanguage();
	  $field_id = $this->attributes["id"];
	  $jspath = ACCUA_FORMS_DIR_URL . 'accua-recaptcha.js';
	  $button_text = htmlspecialchars(__('Show Captcha', 'accua-form-api'), ENT_QUOTES);
	  echo <<<EOT
<script type="text/javascript">
<!--
if (typeof(accuaform_recaptcha_ajax_loaded) == "undefined" || !accuaform_recaptcha_ajax_loaded) {
  var accuaform_recaptcha_ajax_loaded = true;
  document.write('<sc'+'ript type="text/javascript" src="//www.google.com/recaptcha/api/js/recaptcha_ajax.js"></sc'+'ript>');
  document.write('<sc'+'ript type="text/javascript" src="$jspath"></sc'+'ript>');
  document.write('<sc'+'ript type="text/javascript">jQuery(function(){accua_forms_show_recaptcha("{$this->publicKey}", "{$field_id}", {lang: "{$lang}"});});</sc'+'ript>');
}
// -->
</script>
<input type='button' value='$button_text' class='accua_forms_show_recaptcha_button' onclick='accua_forms_show_recaptcha("{$this->publicKey}", "{$field_id}", {lang: "{$lang}"})' />
<div id="{$field_id}"></div>
<noscript>
  		<iframe src="https://www.google.com/recaptcha/api/noscript?k={$this->publicKey}" height="300" width="500" frameborder="0"></iframe><br/>
  		<textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
  		<input type="hidden" name="recaptcha_response_field" value="manual_challenge"/>
</noscript>
EOT;
	}
}
