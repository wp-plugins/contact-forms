<?php
class AccuaForm_Error_Standard extends Error {
  protected $errorfound;
  protected $errorsfound;
  
	public function applyAjaxErrorResponse() {
		$id = $this->form->getId();
		//$errorfound = json_encode($this->errorfound);
		//$errorsfound = json_encode($this->errorsfound);
		echo <<<JS
var errorSize = response.errors.length;
var errorHTML = '<div class="pfbc-error ui-state-error ui-corner-all"><ul>';
for(e = 0; e < errorSize; ++e)
  errorHTML += '<li>' + response.errors[e] + '</li>';
errorHTML += '</ul></div>';
jQuery("#$id").append(errorHTML);
JS;

	}

  public function __construct(array $properties = null) {
    $this->errorfound = __('The following error was found:', 'accua-form-api');
    $this->errorsfound = __('The following @errorsize errors were found:', 'accua-form-api');
    $this->configure($properties);
  }

  public function setErrorsFound($text = '') {
    $this->errorsfound = $text;
  }
  
  public function setErrorFound($text = '') {
    $this->errorfound = $text;
  }
  
	private function parse($errors) {
		$list = array();
		if(!empty($errors)) {
			$keys = array_keys($errors);
			$keySize = sizeof($keys);
			for($k = 0; $k < $keySize; ++$k) 
				$list = array_merge($list, $errors[$keys[$k]]);
		}
		return $list;
	}

    public function render() {
        $errors = $this->parse($this->form->getErrors());
        if(!empty($errors)) {
            $size = sizeof($errors);
            if($size == 1)
                $format = $this->errorfound;
            else
                $format = str_replace('@errorsize', $size, $this->errorsfound);

            echo '<div class="pfbc-error ui-state-error ui-corner-all">', $format, '<ul><li>', implode("</li><li>", $errors), "</li></ul></div>";
        }
    }

    public function renderAjaxErrorResponse($return = false) {
        $errors = $this->parse($this->form->getErrors());
        if ($return) {
          return $errors;
        }
        if(!empty($errors)) {
            header("Content-type: application/json");
            echo json_encode(array("errors" => $errors));
        }
    }
}
