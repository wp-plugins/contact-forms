<?php
class AccuaForm_Element_ColorPicker extends Element {
  public function __construct($label, $name, array $properties = null) {
    parent::__construct($label,$name,$properties);
    $this->setValidation(new AccuaForm_Validation_Color());
  }
  
  public function jQueryDocumentReady() {
    parent::jQueryDocumentReady();
    echo 'jQuery("#', $this->attributes["id"], '").wpColorPicker();';
  }
  
  public function render() {
    $this->validation[] = new Validation_Date;
    parent::render();
  }
}
