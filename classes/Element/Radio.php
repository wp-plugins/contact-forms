<?php
class AccuaForm_Element_Radio extends AccuaForm_OptionElement {
	protected $attributes = array("type" => "radio");
	protected $inline;
	protected $maxheight;

	public function jQueryDocumentReady() {
		if(!empty($this->inline))
			echo 'jQuery("#', $this->attributes["id"], ' .pfbc-radio:last").css("margin-right", "0");';
		else	
			echo 'jQuery("#', $this->attributes["id"], ' .pfbc-radio:last").css({ "padding-bottom": "0", "border-bottom": "none" });';

		if(!empty($this->maxheight) && is_numeric($this->maxheight)) {
			echo <<<JS
var radiobuttons = jQuery("#{$this->attributes["id"]} .pfbc-radio-buttons");
if(radiobuttons.outerHeight() > {$this->maxheight}) {
	radiobuttons.css({ 
		"height": "{$this->maxheight}px", 
		"overflow": "auto", 
		"overflow-x": "hidden" 
	});
}	
JS;
		}	
	}
	
	public function render() { 
		$count = 0;
		$checked = false;
		echo '<div id="', $this->attributes["id"], '"><div class="pfbc-radio-buttons">';
		foreach($this->options as $value => $text) {
			$value = $this->getOptionValue($value);
			echo '<div class="pfbc-radio"><input id="', $this->attributes["id"], "-", $count, '"', $this->getAttributes(array("id", "value", "checked")), ' value="', $this->filter($value), '"';
			if(isset($this->attributes["value"]) && $this->attributes["value"] == $value)
				echo ' checked="checked"';
			echo '/>&nbsp;<label for="', $this->attributes["id"], "-", $count, '">', $text, '</label></div>';
			++$count;
		}	
		echo '</div>';

		if(!empty($this->inline))
			echo '<div style="clear: both;"></div>';

		echo '</div>';
	}

	public function renderCSS() {
		if(!empty($this->inline))
			echo '#', $this->attributes["id"], ' .pfbc-radio { float: left; margin-right: 0.5em; }';
		else	
			echo '#', $this->attributes["id"], ' .pfbc-radio { padding: 0.5em 0; border-bottom: 1px solid #f4f4f4; }';
	}		
}
