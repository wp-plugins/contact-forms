<?php
class AccuaForm extends Form {
  protected static $submitted = null;
  protected static $valid = null;
  protected static $submittedID = null;
  protected static $submittedBuildID = null;
  protected static $submittedForm = null;
  protected static $submittedFormUsed = false;
  protected static $submittedData = null;
  protected static $rawData = null;
  protected static $submittedMessages = '';
  
  protected $formID = null;
  protected $buildID = null;
  protected $validate_functions = array();
  protected $submit_functions = array();
  protected $elements_sleep;
  public $stats = array();
  protected $accua_ajax;
  protected $locale = null;
  protected $language = null;
  protected $files = array();
  
  protected $original_locale = null;
  protected $original_language = null; 
  protected $original_l10n = null;
  protected $forced_language = false;
  protected $elementsByName = array();
  
  public function force_language() {
    if ((!$this->forced_language) && $this->language && function_exists('qtrans_getLanguage')) {
      global $q_config;
      $this->original_language = $q_config['language'];
      
      if ($this->language != $this->original_language) {
        $this->original_locale =& $GLOBALS['wp_locale'];
        $this->original_l10n =& $GLOBALS['l10n'];

        unset($GLOBALS['wp_locale']);
        unset($GLOBALS['l10n']);
        $GLOBALS['l10n'] = array();
        
        $GLOBALS['q_config']['language'] = $this->language;
        load_default_textdomain();
        load_plugin_textdomain( 'accua-form-api', false, ACCUA_FORM_API_PLUGIN_TEXTDOMAIN_PATH);
        require_once( ABSPATH . WPINC . '/locale.php' );
        $GLOBALS['wp_locale'] =& new WP_Locale();
        $GLOBALS['wp_locale']->register_globals();
        
        $this->forced_language = true;
      }
    }
  }
  
  public function restore_language() {
    if ($this->forced_language) {
      unset($GLOBALS['wp_locale']);
      unset($GLOBALS['l10n']);
      
      $GLOBALS['q_config']['language'] = $this->original_language;
      $GLOBALS['l10n'] =& $this->original_l10n;
      $GLOBALS['wp_locale'] =& $this->original_locale;
      if ($GLOBALS['wp_locale']) {
        $GLOBALS['wp_locale']->register_globals();
      }
      
      $this->forced_language = false;
    }
  }
  
  public function __sleep() {
    $this->elements_sleep = $this->getElements();
    return array('attributes', 'elements_sleep', 'error', 'view', 'prefix', 'widthSuffix', 'ajax', 'ajaxCallback', 'attributes', 'jQueryUITheme', 'resourcesPath', 'prevent', 'width', 'formID', 'buildID', 'validate_functions', 'submit_functions', 'stats', 'accua_ajax', 'locale', 'language', 'files');
  }
  
  public function __wakeup() {
    foreach ($this->elements_sleep as $element) {
      $this->addElement($element);
    }
    unset($this->elements_sleep);
    if ($this->view) {
    	$this->view->setForm($this);
    }
    if ($this->error) {
    	$this->error->setForm($this);
    }
  }
  
  public function addElement($element) {
    $name = $element->getName();
    if ($name) {
      $this->elementsByName[$name] = $element;
    }
    return parent::addElement($element);
  }
  
  public function getElementByName($name) {
    if (isset($this->elementsByName[$name])) {
      return $this->elementsByName[$name];
    } else {
      return null;
    }
  }
  
  public static function sessionID() {
    $sessionid = session_id();
    if ($sessionid === '') {
      session_start();
      $sessionid = session_id();
    }
    return $sessionid;
  }
  
  public static function getBaseURL() {
    static $ret = null;
    if ($ret === null) {
      $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
      $sp = strtolower($_SERVER["SERVER_PROTOCOL"]);
      $protocol = substr($sp, 0, strpos($sp, "/")) . $s;
      $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
      $ret = $protocol . "://" . $_SERVER['SERVER_NAME'] . $port;
    }
    return $ret;
  }
  
  public static function create($baseid = 'pfbc', $params = array()) {
    if (self::isSubmit() && (!self::isValid()) && ($baseid === self::$submittedID) && (!self::$submittedFormUsed)) {
      self::$submittedFormUsed = true;
      if (!(self::isValid() || empty(self::$submittedForm))) {
        $form = self::$submittedForm;
        $form->setValues(self::$submittedData);
        return $form;
      }
      $form = new AccuaForm ($baseid, $params, self::$submittedBuildID);
      $form->setValues(self::$submittedData);
    } else {
      $form = new AccuaForm($baseid, $params);
    }
    if (function_exists($baseid)) {
      call_user_func($baseid, $form);
    }
    do_action('accua_form_alter', $baseid, $form);
    return $form;
  }
  
  function __construct($id = 'pfbc', $params = array(), $buildid = '') {
    if ($buildid === '') {
      $buildid = 'accua-form_' . $id . '_' . uniqid();
    }
    
    if (is_array($params)) {
      $params += array(
        'width' => '',
        'layout' => 'sidebyside',
      );
      //$width = $params['width'];
    } else {
      //$width = $params;
      $params = array(
        //'width' => $width,
        'layout' => 'sidebyside',
      );
    }
    $class = 'accua-form ' . $id;
    switch ($params['layout']) {
      case 'toplabel':
        $this->view = new AccuaForm_View_Standard();
        $class .= ' accua-form-view-standard';
      break;
      case 'sidebyside':
      default:
        $this->view = new AccuaForm_View_SideBySide(
          '19',
          array('labelPaddingRight' => '1')
        );
        $class .= ' accua-form-view-sidebyside';
    }
    $this->error = new AccuaForm_Error_Standard(array(
      'errorfound' => '',
      'errorsfound' => '',
    ));
    if ($width === ""){
      $width = "100%";
    }
    $this->attributes = array(
      'class' => $class,
      'novalidate' => 'novalidate',
    );
    
    parent::__construct($buildid, $width);
    
    
    $this->formID = $id;
    $this->buildID = $buildid;
    $this->addElement(new Element_Hidden('_AccuaForm_ID', $id));
    $this->addElement(new Element_Hidden('_AccuaForm_buildID', $buildid));
    $this->addElement(new Element_Hidden('_AccuaForm_wpnonce', wp_create_nonce( $buildid )));
    $this->addElement(new Element_Hidden('_AccuaForm_jsuuid', ''));
    $this->addElement(new Element_Hidden('_AccuaForm_referrer', ''));
    $this->addElement(new Element_Hidden('_AccuaForm_user_agent', ''));
    $this->addElement(new Element_Hidden('_AccuaForm_platform', ''));
    $this->addElement(new Element_Hidden('_AccuaForm_tentatives', '0'));
    $this->addElement(new Element_Hidden('_AccuaForm_submit_method', 'normal'));
    $this->addElement(new Element_Hidden('_AccuaForm_hash', ''));
    $this->addElement(new Element_Hidden('_AccuaForm_iv', ''));
    $this->addElement(new Element_Hidden('_AccuaForm_data', ''));
    /*
     * - Prima di generare l'html del form, salva una copia serializzata compreso di codice SHA2, criptato, in _AccuaForm_serialized
     * - Quando ricevi il form, decripta _AccuaForm_serialized e controlla che sia valido
     * 
     * */
    
    //$this->addElement(new Element_Hidden('PHPSESSID', self::sessionID()));
    $this->prevent = array('jQuery', 'jQueryUI', 'jQueryUIButtons', 'focus', 'style');
    $this->configure(array('action' => ''));
    
    if (function_exists('qtrans_getLanguage')) {
      $this->language = qtrans_getLanguage();
      $this->locale = $GLOBALS['q_config']['locale'][$this->language];
    } else {
      $this->locale = get_locale();
      $this->language = explode('_', $this->locale);
      $this->language = $this->language[0];
    }
    
    global $post;
    $pid = empty($post->ID) ? 0 : $post->ID;
    
    $uri = isset($GLOBALS['q_config']['url_info']['original_url']) ? $GLOBALS['q_config']['url_info']['original_url'] : $_SERVER['REQUEST_URI'];
    
    $url = self::getBaseURL() . $uri ;
    
    $this->stats = array(
      'pid' => $pid,
      'ip' => $_SERVER['REMOTE_ADDR'], //updated after submit
      'original_ip' => $_SERVER['REMOTE_ADDR'], //unreliable if using a static page caching system
      'uri' => $uri,
      'url' => $url,
      'referrer' => $_SERVER['HTTP_REFERER'], //updated after submit using javascript
      'original_referrer' => $_SERVER['HTTP_REFERER'], //unreliable if using a static page caching system
      'lang' => $this->language,
      'locale' => $this->locale,
      'created' => time(),
      'submitted' => null,
      'user_agent' => '',
      'platform' => '',
      'tentatives' => '',
      'submit_method' => '',
    );
    
    $this->view->setForm($this);
    $this->error->setForm($this);
  }
  
  public static function isSubmit() {
    if (self::$submitted !== null) {
      return self::$submitted;
    }
    self::$submitted = false;
    $sessid = self::sessionID();
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
      self::$rawData = stripslashes_deep($_POST);
    } else {
      self::$rawData = stripslashes_deep($_GET);
    }
    
    if (empty(self::$rawData['_AccuaForm_ID']) || empty(self::$rawData['_AccuaForm_buildID']) || empty(self::$rawData['_AccuaForm_wpnonce']) /* || empty(self::$rawData['PHPSESSID']) */
        || empty(self::$rawData['_AccuaForm_hash']) || empty(self::$rawData['_AccuaForm_iv']) || empty(self::$rawData['_AccuaForm_data'])) {
      return false;
    }
    
    /*
    if (self::$rawData['PHPSESSID'] !== $sessid) {
      return false;
    }
    */
    
    $id = self::$rawData['_AccuaForm_buildID'];
    $form = self::wp_recover(self::$rawData['_AccuaForm_hash'], self::$rawData['_AccuaForm_iv'], self::$rawData['_AccuaForm_data']);
    
    if (empty($form)) {
      return false;
    }
    
    if ($form->formID !== self::$rawData['_AccuaForm_ID'] || $form->buildID !== $id ) {
      return false;
    }
    
    /*
    if (!wp_verify_nonce(self::$rawData['_AccuaForm_wpnonce'], $id)) {
      return false;
    }
    */
    
    /* check if already submitted from uuid and other parameters */
    $already_submitted = false;
    if (!empty(self::$rawData['_AccuaForm_jsuuid'])) {
      if (preg_match("/^[a-z0-9]{25}$/", self::$rawData['_AccuaForm_jsuuid'])) {
        $already_submitted_data = get_transient('accuaformsub_'.self::$rawData['_AccuaForm_jsuuid']);
        if ($already_submitted_data &&  $already_submitted_data['buildID'] == $form->buildID) {
          $form = $already_submitted_data['form'];
          self::$submittedMessages =  $already_submitted_data['submittedMessages'];
          self::$valid = true;
          $already_submitted = true;
        }
      } else {
        return false;
      }
    }
    
    if (!$already_submitted) {
      $form->stats['submitted'] = time();
      $form->stats['ip'] = $_SERVER['REMOTE_ADDR'];
      $form->stats['referrer'] = self::$rawData['_AccuaForm_referrer'];
      $form->stats['jsuuid'] = self::$rawData['_AccuaForm_jsuuid'];
      $form->stats['user_agent'] = self::$rawData['_AccuaForm_user_agent'];
      $form->stats['platform'] = self::$rawData['_AccuaForm_platform'];
      $form->stats['tentatives'] = self::$rawData['_AccuaForm_tentatives'];
      $form->stats['submit_method'] = self::$rawData['_AccuaForm_submit_method'];
    }
    self::$submitted = true;
    self::$submittedID = $form->formID;
    self::$submittedBuildID = $form->buildID;
    self::$submittedForm = $form;
    return true;
  }
  
  public static function isValid() {
    if (!self::isSubmit()){
      return null;
    }
    if (self::$valid !== null) {
      return self::$valid;
    }
    
    //$subdata = array();
    $id = self::$submittedBuildID;
    //$form = self::wp_recover($id);
    $form = self::$submittedForm;
    $valid = true;
    
    $form->force_language();
    
    /*Any values/errors stored in the session for this form are cleared.*/
    self::clearValues($id);
    self::clearErrors($id);

    self::$submittedData = array();
    /*Each element's value is saved in the session and checked against any validation rules applied
    to the element.*/
    $elements = $form->getElements();
    if(!empty($elements)) {
      foreach($elements as $element) {
        $invalidFile = false;
        $name = $element->getName();
        if(substr($name, -2) == "[]") {
          $name = substr($name, 0, -2);
        }

        /*The File element must be handled differently b/c it uses the $_FILES superglobal and
        not $_GET or $_POST.*/
        if($element instanceof AccuaForm_Element_File) {
          if (!empty($form->files[$name]['name'])) {
            $value = $form->files[$name]['name'];
          } else if ((!empty($_FILES[$name])) && ($_FILES[$name]['error'] != UPLOAD_ERR_NO_FILE)) {
            //$file = $_FILES[$name];
            //include_once( ABSPATH . '/wp-admin/includes/file.php' );
            //$overrides = array( 'test_form' => false );
            //$file = wp_handle_upload( $file, $overrides );
            
            $file = $element->handle_upload($_FILES[$name]);
            $value = $file['name'];
            
            if (empty($file['errors'])) {
              $form->files[$name] = $file;
            } else {
              self::setError($id, $file['errors'] , $name);
              $valid = false;
              $invalidFile = true;
            }
          } else {
            $value = null;
          }
        } else if ($element instanceof Element_File){
          $value = $_FILES[$name]["name"];
        } else if (isset(self::$rawData[$name])) {
          $value = self::$rawData[$name];
          if(is_array($value)) {
            foreach($value as $key => $value_i) {
              $value[$key] = (string) $value_i;
            }
          } else {
            $value = (string) $value;
          }
        } else {
          $value = null;
        }
        
        //self::setSessionValue($id, $name, $value);
        
        /*If a validation error is found, the error message is saved in the session along with
        the element's name.*/
        if(!$element->isValid($value)) {
          self::setError($id, $element->getErrors(), $name);
          $valid = false;
          if ($element instanceof AccuaForm_Element_File) {
            $invalidFile = true;
          }
        }
        
        if($invalidFile) {
          if (isset($file['tmp_name'])) {
            unlink($element->getDestPath . $file['tmp_name']);
            unset($form->files[$name]);
          }
        } else if($name !== '') {
          self::$submittedData[$name] = $value;
        }
      }
    }
    $_SESSION["pfbc"][$id]["values"] = self::$submittedData;
    

    if (function_exists(self::$submittedID.'_validate')) {
      $valid = call_user_func(self::$submittedID.'_validate', $valid, self::$submittedID, self::$submittedData, $form);
    }
    $valid = apply_filters('accua_form_validate', $valid, self::$submittedID, self::$submittedData, $form);
    
    asort($form->validate_functions);
    foreach($form->validate_functions as $func => $priority){
      if (function_exists($func)) {
        $valid = call_user_func($func, $valid, self::$submittedID, self::$submittedData, $form);
      }
    }

    if ($valid) {
      if (function_exists(self::$submittedID.'_submit')) {
        call_user_func(self::$submittedID.'_submit', self::$submittedID, self::$submittedData, $form);
      }
      do_action('accua_form_submit', self::$submittedID, self::$submittedData, $form);
      asort($form->submit_functions);
      foreach($form->submit_functions as $func => $priority){
        if (function_exists($func)) {
          call_user_func($func, self::$submittedID, self::$submittedData, $form);
        }
      }
    }
    
    /*If no validation errors were found, the form's session values are cleared.*/
    /*
    if($valid) {
      if($clearValues)
        self::clearValues($id);
      self::clearErrors($id);
    }
    */

    //TODO: Should i save here?
    //$form->save();
    //$_SESSION["pfbc"][$id]["form"] = serialize($form);
    
    $form->restore_language();
    
    if ($valid && self::$rawData['_AccuaForm_jsuuid'] ) {
      set_transient('accuaformsub_'.self::$rawData['_AccuaForm_jsuuid'], array(
          'buildID' => self::$submittedBuildID,
          'form' => self::$submittedForm,
          'submittedMessages' => self::$submittedMessages,
        ), 86400);
    }
    
    return self::$valid = (bool)$valid;
  }
  
  public static function getSubmittedData() {
    self::isValid();
    return self::$submittedData;
  }
  
  public static function getSumbittedID() {
    trigger_error('Use getSubmittedID() instead', (defined('E_USER_DEPRECATED')?E_USER_DEPRECATED:E_USER_NOTICE));
    return self::getSubmittedID();
  }
  
  public static function getSubmittedID() {
    if (self::isSubmit()) {
      return self::$submittedID;
    } else {
      return null;
    }
  }
  
  public static function getSubmittedForm() {
    if (self::isSubmit()) {
      return self::$submittedForm;
    } else {
      return null;
    }
  }
  
  /*This method restores the serialized form instance.*/
  protected static function wp_recover($hash,$iv,$data) {
    /*
    if(!empty($_SESSION["pfbc"][$id]["form"]))
      return unserialize($_SESSION["pfbc"][$id]["form"]);
    */
    /*
    $storename = 'accua_form_' . md5($id);
    if ($stored = get_transient($storename)) {
      return unserialize($stored);
    }
    */

    $keys = get_option('accua_form_api_keys', array());
    @ $hash = base64_decode($hash);
    @ $iv = base64_decode($iv);
    @ $data = base64_decode($data);
    
    if (!($hash && $iv && $data)) {
      return;
    }
    
    if (!class_exists('Crypt_Hash')) {
      require_once('phpseclib-crypt/Hash.php');
    }
    $hasher = new Crypt_Hash('sha1');
    $hasher->setKey($keys['hash']);
    @ $hash2 = $hasher->hash($iv.$data);
    
    if ($hash !== $hash2) {
      return;
    }
    
    if (!class_exists('Crypt_AES')) {
      require_once('phpseclib-crypt/AES.php');
    }
    
    $cipher = new Crypt_AES();
    $cipher->setPassword($keys['aes']);
    @ $cipher->setIV($iv);
    @ $data = $cipher->decrypt($data);
    
    if ($data) {
      @ $form = unserialize($data);
      if ($form) {
        return $form;
      }
    }
  }
  
  protected function wp_save() {
    /*
    $storename = 'accua_form_' . md5($this->buildID);
    //$serialized = isset($_SESSION["pfbc"][$this->buildID]["form"]) ? $_SESSION["pfbc"][$this->buildID]["form"] : serialize($this);
    $serialized = serialize($this);
    set_transient($storename, $serialized, 2764800); // 32 days
    */
    
    if (!class_exists('Crypt_Hash')) {
      require_once('phpseclib-crypt/Hash.php');
    }
    if (!class_exists('Crypt_AES')) {
      require_once('phpseclib-crypt/AES.php');
    }
    
    $keys = get_option('accua_form_api_keys', array());
    if (!(isset($keys['aes']) && isset($keys['hash']))) {
      accua_form_api_install();
      $keys = get_option('accua_form_api_keys', array());
    }
    
    $data = serialize($this);
    $iv = wp_generate_password(64,true,true);
    
    $cipher = new Crypt_AES();
    $cipher->setPassword($keys['aes']);
    $cipher->setIV($iv);
    $data = $cipher->encrypt($data);
    
    $hasher = new Crypt_Hash('sha1');
    $hasher->setKey($keys['hash']);
    $hash = $hasher->hash($iv.$data);
    
    $ret = array(
      '_AccuaForm_hash' => base64_encode($hash),
      '_AccuaForm_iv' => base64_encode($iv),
      '_AccuaForm_data' => base64_encode($data),
    );
    if (isset($_SESSION["pfbc"][$this->buildID]["values"])) {
      $_SESSION["pfbc"][$this->buildID]["values"] = $ret + $_SESSION["pfbc"][$this->buildID]["values"];
    }
    $this->setValues($ret);
    return $ret;
  }
  
  public function getLocale() {
    return $this->locale;
  }
  
  public function getLanguage() {
    return $this->language;
  }
  
  public function addValidateFunction($function_name, $priority = 0) {
    $this->validate_functions[$function_name] = $priority;
  }
  
  public function addSubmitFunction($function_name, $priority = 0) {
    $this->submit_functions[$function_name] = $priority;
  }
  
  public function getSubmittedMessages(){
    return self::$submittedMessages;
  }
  
  public function setSubmittedMessages($msg){
    return self::$submittedMessages = $msg;
  }
  
  public function appendSubmittedmessages($msg){
    return self::$submittedMessages .= $msg;
  }
  
  public function render($returnHTML = false) {
    $this->wp_save();
    if($returnHTML) {
      ob_start();
    }
    parent::render(false);
    if (!empty($this->accua_ajax)) {
      $ajax_url = json_encode(admin_url('admin-ajax.php?action=accua_form_submit'));
      $submit_fail_message = json_encode(__('Unable to submit the form, please retry', 'accua-form-api'));
      $required_message = json_encode(__('Please fill in all required fields', 'accua-form-api'));
      $valid_mail_message = json_encode(__('You have to enter a valid email address where required', 'accua-form-api'));
      $js_buildid = preg_replace('/[^a-zA-Z0-9_]/m','_',$this->buildID);
      $formid = $this->getId();
      $hostname = json_encode($_SERVER['SERVER_NAME']);

      $post_url = json_encode($this->stats['url']);
      
      
      echo <<<JS
<script type="text/javascript">
//<![CDATA[
var _handle_ajax_submit_{$js_buildid} = function() {return true;}
var _handle_ajax_submit_complete_{$js_buildid} = function() {return false;}
var _handle_ajax_submit_timeout_{$js_buildid} = function() {return false;}
var _handle_ajax_submit_message_{$js_buildid} = function() {}
var _handle_ajax_submit_response_{$js_buildid} = function() {}

jQuery(function($) {
  var thisform = $("#{$this->buildID}");
  var ajax_enabled = {$hostname} == location.hostname ;
  
  var response_messages = $("#_response_messages_{$this->buildID}");
  if (! response_messages.length) {
    response_messages = $("<div id='_response_messages_{$this->buildID}' class='accua-form-messages'></div>");
    $("#{$this->buildID}").before(response_messages);
  }
  var throbbler = $("<span class='accua_form_api_throbbler'></span>");
  
  var _ajax_submitting_{$js_buildid} = false;
  var timeout_handler = false;
  var timeout_count = 0;
  var fail_count = 0;
  var disabled_fields = false;
  
  var jsuuid_field = $('input[name="_AccuaForm_jsuuid"]', thisform);
  var jsuuid = jsuuid_field.val();
  if (jsuuid == '') {
    var chars = '0123456789abcdefghijklmnopqrstuvwxyz'.split('');
    var radix = chars.length
    for (i = 0; i < 25; i++) {
      jsuuid += chars[0 | Math.random()*radix];
    }
    jsuuid_field.val(jsuuid);
  }
  
  _handle_ajax_submit_{$js_buildid} = function() {
    if (_ajax_submitting_{$js_buildid}) {
      return false;
    }
    
JS;
    $this->error->clear();
    echo <<<JS
    
    var valid_empty = true;
    var valid_mail = true;
    
    $("#{$this->buildID} .pfbc-element").removeClass('pfbc-invalid');
    
    $('.accuaforms-field-required', thisform).each(function(){
      var field = $(this);
      
      if (field.attr('type') === 'checkbox') {
        if (field.is(':checked')) {
          return true;
        }
      } else {
        if (! field.val().match(/^\s*$/)) {
          return true;
        }
      }
      
      valid_empty = false;
      field.parents("#{$this->buildID} .pfbc-element").addClass('pfbc-invalid');
    });
    
    $('.pfbc-textbox[type="email"]', thisform).each(function(){
      var field = $(this);
      
      if (field.val().match(/^\s*$/)) {
        return true;
      }
      
      if (field.val().match( /^([a-zA-Z0-9_.+%-])+@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9])+$/ )) {
        return true;
      }
      
      valid_mail = false;
      field.parents("#{$this->buildID} .pfbc-element").addClass('pfbc-invalid');
      
    });
    
    if (valid_empty && valid_mail) {
      thisform.append(throbbler);
      _ajax_submitting_{$js_buildid} = true;
      $('input[name="_AccuaForm_tentatives"]', thisform).val(fail_count);
      disabled_fields = $("input, textarea, button, select").not('[type="submit"]').not(':disabled');
      disabled_fields.attr('readonly','readonly');
      timeout_count = 0;
      if (ajax_enabled) {
        $("#submit_target_{$js_buildid}").attr('src','').removeAttr('src');
        timeout_handler = setTimeout(_handle_ajax_submit_timeout_{$js_buildid}, 5000);
      }
      return true;
    } else {
      var message = '';
      if (!valid_empty) {
        message += $required_message + '<br />';
      }
      if (!valid_mail) {
        message += $valid_mail_message + '<br />';
      }
      thisform.append('<div class="pfbc-error ui-state-error ui-corner-all">' + message + '</div>');
      return false;
    }
  }
  
  
  _handle_ajax_submit_timeout_{$js_buildid} = function() {
    if (_ajax_submitting_{$js_buildid}) {
      if (timeout_count < 60) {
        timeout_count++;
        timeout_handler = setTimeout(_handle_ajax_submit_timeout_{$js_buildid}, 500);
        _handle_ajax_submit_complete_{$js_buildid}();
      } else {
        timeout_handler = false;
        _handle_ajax_submit_complete_{$js_buildid}();
        if (_ajax_submitting_{$js_buildid}) {
          _handle_ajax_submit_response_{$js_buildid}(false);
        }
      }
    }
  }
  
  _handle_ajax_submit_complete_{$js_buildid} = function() {
    if (_ajax_submitting_{$js_buildid}) {
      var response = false;
      try {
        var responsedoc = frames['submit_target_{$js_buildid}'].document;
        if (responsedoc.getElementById("accua-form-ajax-response-loaded")) {
          response = $.parseJSON(responsedoc.getElementById("accua-form-ajax-response").innerHTML);
        }
      } catch (err) {
        response = false;
      }
      if (response) {
        return _handle_ajax_submit_response_{$js_buildid} (response);
      }
    }
  }
  
  _handle_ajax_submit_message_{$js_buildid} = function(message) {
    if (_ajax_submitting_{$js_buildid}) {
      var response = false;
      try {
        response = $.parseJSON(message.data);
        if (response.jsuuid != jsuuid || response.buildID != "{$this->buildID}") {
          response = false;
        }
      } catch (err) {
        response = false;
      }
      if (response) {
        return _handle_ajax_submit_response_{$js_buildid} (response);
      }
    }
  }
  
  _handle_ajax_submit_response_{$js_buildid} = function(response) {
    if (_ajax_submitting_{$js_buildid}) {
      if(response && typeof(response) == "object" && typeof(response.submitted) == "boolean") {
        response_messages.html(response.messages);
        if (response.submitted) {
          if (response.valid) {
JS;
            /*A callback function can be specified to handle any post submission events.*/
            if(!empty($this->ajaxCallback)) {
              echo $this->ajaxCallback, "(response);";
            } else {
              echo "$('#{$this->buildID}').hide();";
            }
            echo <<<JS
          } else {
JS;
            $this->error->applyAjaxErrorResponse();
            echo <<<JS
            
            for (var name in response.files) {
              $(".pfbc-fieldwrap:has(input[type='file'][name='"+name+"'])", thisform).html(response.files[name]);
            }
            
            $("input[name='_AccuaForm_hash']",thisform).val(response._AccuaForm_hash);
            $("input[name='_AccuaForm_iv']",  thisform).val(response._AccuaForm_iv);
            $("input[name='_AccuaForm_data']",thisform).val(response._AccuaForm_data);
            
            disabled_fields.removeAttr('readonly');
          }
        }
      } else {
        fail_count++;
        if (fail_count > 2) {
          ajax_enabled = false;
          thisform.attr("action", {$post_url} );
          thisform.removeAttr("target");
          $('input[name="_AccuaForm_submit_method"]', thisform).val('fallback');
        }
        thisform.append('<div class="pfbc-error ui-state-error ui-corner-all">' + $submit_fail_message + '</div>');
      }
      throbbler.remove();
      _ajax_submitting_{$js_buildid} = false;
      if (timeout_handler) {
        clearTimeout(timeout_handler);
        timeout_handler = false;
      }
    }
  }
  
  if (ajax_enabled) {
    thisform.attr("action", {$ajax_url} );
    thisform.attr("target","submit_target_{$js_buildid}");
    try {
      window.addEventListener('message', _handle_ajax_submit_message_{$js_buildid}, false);
    } catch (e) { }
    $('input[name="_AccuaForm_submit_method"]', thisform).val('iframe');
  } else {
    thisform.attr("action", {$post_url} );
  }
  thisform.attr("onsubmit","return _handle_ajax_submit_{$js_buildid}()");
});
//]]>    
</script>
<iframe id="submit_target_{$js_buildid}" name="submit_target_{$js_buildid}" onload="_handle_ajax_submit_complete_{$js_buildid}()" onerror="_handle_ajax_submit_complete_{$js_buildid}()" style="width:0;height:0;border:0px solid #fff"></iframe>
JS;
    }
    
    echo <<<JSREFERRER
<script type="text/javascript">
jQuery(function($){
  var referrerfield = $("#{$this->buildID} input[name='_AccuaForm_referrer']");
  if (referrerfield.val() == '') {
    referrerfield.val(document.referrer);
  }
  $("#{$this->buildID} input[name='_AccuaForm_user_agent']").val(navigator.userAgent);
  $("#{$this->buildID} input[name='_AccuaForm_platform']").val(navigator.platform);
});
</script>
JSREFERRER;
    
    if($returnHTML) {
      $html = ob_get_contents();
      ob_end_clean();
      return $html;
    }
  }
  
  protected function renderJS() {
    $this->renderJSFiles();
  
    echo '<script type="text/javascript">';
    $this->view->renderJS();
    foreach($this->elements as $element)
      $element->renderJS();
  
    $id = $this->attributes["id"];
  
    echo 'jQuery(document).ready(function() {';
    /*jQuery is used to set the focus of the form's initial element.*/
    if(!in_array("focus", $this->prevent))
      echo 'jQuery("#', $id, ' :input:visible:enabled:first").focus();';
  
    $this->view->jQueryDocumentReady();
    foreach($this->elements as $element)
      $element->jQueryDocumentReady();
  
    /*For ajax, an anonymous onsubmit javascript function is bound to the form using jQuery.  jQuery's
     serialize function is used to grab each element's name/value pair.* /
    if(!empty($this->ajax)) {
      echo 'jQuery("#', $id, '").bind("submit", function() {';
      $this->error->clear();
      echo <<<JS
			jQuery.ajax({
				url: "{$this->attributes["action"]}",
				type: "{$this->attributes["method"]}",
				data: jQuery("#$id").serialize(),
				success: function(response) {
					if(response != undefined && typeof response == "object" && response.errors) {
JS;
      $this->error->applyAjaxErrorResponse();
      echo <<<JS
						jQuery("html, body").animate({ scrollTop: jQuery("#$id").offset().top }, 500 );
					}
					else {
JS;
      /*A callback function can be specified to handle any post submission events.* /
      if(!empty($this->ajaxCallback))
        echo $this->ajaxCallback, "(response);";
      echo <<<JS
					}
				}
			});
			return false;
		});
  
JS;
    }
  */
    echo <<<JS
	});
</script>
JS;
  }
  
  protected function renderCSS() {

  }
  
  public function getAjax() {
    return $this->accua_ajax;
  }
  
  public function getFile($fieldname) {
    if (isset($this->files[$fieldname])) {
      return $this->files[$fieldname];
    }
  }
  
  public function renameFile($fieldname, $newname) {
    if (isset($this->files[$fieldname])) {
      $file = $this->files[$fieldname];
      @ $renamed = rename($file['dest_path'].$file['tmp_name'], $file['dest_path'].$newname);
      if ($renamed) {
        $this->files[$fieldname]['new_name'] = $newname;
        return true;
      }
    }
    return false;
  }
  
  public static function renderAjaxErrorResponse($return = false) {
    if ($form = self::$submittedForm) {
      $form->error->setForm($form);
      return $form->error->renderAjaxErrorResponse($return);
    }
  }
  
  public function setFormError($errors, $element = '') {
    return self::setError($this->buildID, $errors, $element);
  }
  
  public function setClass($class) {
    if(!empty($this->attributes["class"]))
      $this->attributes["class"] .= " " . $class;
    else
      $this->attributes["class"] = $class;
  }
  
  public static function ajaxSubmit() {
    $ret = array(
        'valid' => false,
        'messages' => self::getSubmittedMessages(),
        'jsuuid' => self::$rawData['_AccuaForm_jsuuid'],
        'buildID' => self::$submittedBuildID,
        'files' => array(),
    );
    if ($ret['submitted'] = self::isSubmit()){
      if ($ret['valid'] = self::isValid()) {
    
      } else {
        $ret['errors'] = self::renderAjaxErrorResponse(true);
      }
      $form = self::$submittedForm;
      foreach ($form->files as $fieldname => $file) {
        if (!empty($file['name'])) {
          $ret['files'][$fieldname] = $form->getElementByName($fieldname)->getAlreadySubmittedText();
        }
      }
      $ret += $form->wp_save();
    }
    return $ret;
  }
}
