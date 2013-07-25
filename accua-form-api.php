<?php
function accua_form_Load($class) {
  if (substr($class,0,10) === 'AccuaForm_') {
    $class = substr($class, 10);
    $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . str_replace("_", DIRECTORY_SEPARATOR, $class) . ".php";
    if(is_file($file)) {
      include_once $file;
    }
  }
}
spl_autoload_register("accua_form_Load");

require_once('PFBC/Form.php');
require_once('AccuaForm.php');

add_action('plugins_loaded', 'accua_form_init');

define('ACCUA_FORM_API_PLUGIN_TEXTDOMAIN_PATH', dirname( plugin_basename( __FILE__ ) ) . '/languages/');
function accua_form_init(){
  wp_enqueue_script('jquery');
  
  wp_enqueue_style( 'accua-forms-api-base', plugins_url('accua-form-api.css', __FILE__), array(), '2');
  
  load_plugin_textdomain( 'accua-form-api', false, ACCUA_FORM_API_PLUGIN_TEXTDOMAIN_PATH);
  AccuaForm::isValid();
}

add_action('wp_ajax_accua_form_submit', 'accua_form_ajax_submit_handler');
add_action('wp_ajax_nopriv_accua_form_submit', 'accua_form_ajax_submit_handler');
function accua_form_ajax_submit_handler(){
  header('Content-Type: text/html; charset='.get_option('blog_charset'));
  
  echo '<html><head></head><body><pre id="accua-form-ajax-response">';
  
  echo strtr(json_encode(AccuaForm::ajaxSubmit()),
    array(
      '&' => '\\u0026',
      '<' => '\\u003C',
      '>' => '\\u003E',
    ));
  
  echo '</pre><div id="accua-form-ajax-response-loaded"></div>';
  
  echo <<<EOJS
<script type="text/javascript">
<!--
try {
  window.parent.postMessage(document.getElementById("accua-form-ajax-response").innerHTML, '*');
} catch (err) {
}
// -->
</script>
EOJS;
      
  echo '</body></html>';
  die ("");
}
