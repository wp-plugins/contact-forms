<?php
class AccuaForm_Element_FieldsetEnd extends Element {
  public function __construct() {
    parent::__construct("", "");
  }
  
  public function render() {
    echo '</fieldset>';
  }
  
}