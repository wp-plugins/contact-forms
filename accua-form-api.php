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
add_action( 'wp_print_scripts', 'accua_form_init_scripts');

define('ACCUA_FORM_API_PLUGIN_TEXTDOMAIN_PATH', dirname( plugin_basename( __FILE__ ) ) . '/languages/');

function accua_form_init_scripts(){
  global $wp_scripts;
  wp_enqueue_script('jquery');
  
  if(!wp_script_is('wp-color-picker', 'registered')) {
    if (!wp_script_is('iris', 'registered')) {
      $wp_scripts->add( 'iris', '/wp-admin/js/iris.min.js', array( 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch' ), false, 1 );
    }
    $wp_scripts->add( 'wp-color-picker', "/wp-admin/js/color-picker$suffix.js", array( 'iris' ), false, 1 );
    $wp_scripts->localize( 'wp-color-picker', 'wpColorPickerL10n', array(
        'clear' => __( 'Clear' ),
        'defaultString' => __( 'Default' ),
        'pick' => __( 'Select Color' ),
        'current' => __( 'Current Color' ),
    ) );
  }
  wp_enqueue_script('wp-color-picker');
}
function accua_form_init(){
    
  wp_enqueue_style( 'wp-color-picker' );
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
  die('');
}
