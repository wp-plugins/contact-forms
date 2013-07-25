<?php
class AccuaForm_Element_File extends Element_File {
  protected $destPath;
  protected $alreadySubmittedText;
  protected $validExtensions = array('txt','doc','rtf','pdf','jpg','jpeg','png','zip','gz','bz','bz2');
  protected $maxSize;
  protected $limitsText;
  protected $errorsText;
  
  public function __construct($label, $name, array $properties = null) {
    $this->alreadySubmittedText = __("File already submitted.", 'accua-form-api');
    $this->limitsText = __("Maximum filesize: %size%. Allowed extensions: %extensions%.", 'accua-form-api');
    $this->errorsText = array(
      'upload' => __('Upload failed, please retry. If the problem persists please contact us', 'accua-form-api'),
      'size' => __('Uploaded file is too big', 'accua-form-api'),
      'empty' => __('Uploaded file is empty', 'accua-form-api'),
      'name' => __('File name is not valid. Please rename it avoiding unusual characters', 'accua-form-api'),
      'ext' => __('File extension not allowed. Allowed extensions are %extensions%', 'accua-form-api'),
    );
    $this->destPath = realpath(ABSPATH . '/wp-content/uploads/accua-forms').'/';
    $this->maxSize = self::file_upload_max_size();
    parent::__construct($label, $name, $properties);
  }
  
  public function __sleep() {
    return array("attributes", "label", "validation", 'alreadySubmittedText', 'validExtensions', 'maxSize', 'limitsText', 'errorsText', 'destPath');
  }
  
  public function render() {
    if(empty($this->attributes["value"])){
      parent::render();
      if ($this->limitsText !== ''){
        if ($this->validExtensions) {
          $validExtensions = implode(', ', $this->validExtensions);
        } else {
          $validExtensions = '*';
        }
        $search = array('%size%', '%extensions%');
        $replace = array(self::format_size($this->maxSize),$validExtensions);
        echo '<br /><small>', str_replace($search, $replace, $this->limitsText), '</small>';
      }
    } else {
      echo $this->alreadySubmittedText;
    }
  }
  
  public function getAlreadySubmittedText() {
    return $this->alreadySubmittedText;
  }
  
  public static function format_size($size) {
    if ($size >= 1073741824) {
      return round($size/1073741824, 2).' GB';
    } else if ($size >= 1048576) {
      return round($size/1048576, 2).' MB';
    } else if ($size >= 1024) {
      return round($size/1024, 2).' KB';
    } else if ($size > 0) {
      return round($size).' B';
    } else {
      return '-';
    }
  }
  
  public static function parse_size($size) {
    if (is_numeric($size)) {
      return (int) $size;
    }
    
    $suffixes = array(
      '' => 1,
      'k' => 1024,
      'm' => 1048576, // 1024 * 1024
      'g' => 1073741824, // 1024 * 1024 * 1024
    );
    if (preg_match('/([0-9.,]+)\s*(k|m|g)?(b?(ytes?)?)/i', $size, $match)) {
      return $match[1] * $suffixes[strtolower($match[2])];
    }
  }
  
  public static function file_upload_max_size() {
    static $max_size = -1;
  
    if ($max_size < 0) {
      $upload_max = self::parse_size(ini_get('upload_max_filesize'));
      $post_max = self::parse_size(ini_get('post_max_size'));
      $max_size = ($upload_max < $post_max) ? $upload_max : $post_max;
    }
    return $max_size;
  }
  
  public function setMaxSize($size) {
    $phpMaxSize = self::file_upload_max_size();
    $size = self::parse_size($size);
    return $this->maxSize = ($phpMaxSize < $size) ? $phpMaxSize : $size;
  }
  
  public function setErrorsText($errors){
    if(is_array($errors)) {
      $this->errorsText = $errors + $this->errorsText;
    }
  }
  
  public function setDestPath($path) {
    if (substr($path,0,1) !== '/') {
     $path = ABSPATH . '/' . $path;
    }
    if (!is_dir($path)){
      @ mkdir($path, 0777, true);
    }
    return $this->destPath = realpath($path) . '/';
  }
  
  public function handle_upload($filedata){
    $valid = true;
    $file = array(
      'dest_path' => $this->destPath,
    );

    if (!empty($filedata['error'])){
      $valid = false;
      switch($filedata['error']){
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
          $file['errors'][] = $this->errorsText['size'];
        break;
        default:
          $file['errors'][] = $this->errorsText['upload'];
      }
    } else if ($filedata['size'] <= 0) {
      $valid = false;
      $file['errors'][] = $this->errorsText['empty']; 
    } else if ($this->maxSize > 0 && $filedata['size'] > $this->maxSize) {
      $valid = false;
      $file['errors'][] = $this->errorsText['size'];
    } else {
      $file['size'] = $filedata['size'];
    }
    
    if (isset($filedata['name']) && $filedata['name'] !== ''){
      $file['name'] = $filedata['name'];
      if ((strpos($file['name'], "\0") !== false) || (strpbrk($file['name'], "\1\2\3\4\5\6\7\10\11\12\13\14\15\16\17\20\21\22\23\24\25\26\27\30\31\32\33\34\35\36\37\177\\/:*?\"<>|") !== false) ) {
        $valid = false;
        $file['errors'][] = $this->errorsText['name'];
      } else if ($this->validExtensions) {
        $ext = strrchr($file['name'], '.');
        $ext = ($ext === false) ? '' : strtolower(ltrim($ext, '.'));
        if (!in_array($ext, $this->validExtensions, true)) {
          $valid = false;
          $file['errors'][] = str_replace('%extensions%', implode(', ',$this->validExtensions), $this->errorsText['ext']);
        }
      }
    } else {
      $file['name'] = '';
      if ($valid) {
        $valid = false;
        $file['errors'][] = $this->errorsText['name'];
      }
    }
    
    if ($valid) {
      if (!is_dir($this->destPath)){
        @ mkdir($this->destPath, 0777, true);
      }
      
      do {
        $tmpname = 'tmp' . mt_rand() . '_' . $file['name'];
      } while (is_file($this->destPath.$tmpname));
      
      @ $valid = move_uploaded_file($filedata['tmp_name'], $this->destPath.$tmpname);
      if ($valid) {
        $file['tmp_name'] = $tmpname;
      } else {
        $file['errors'][] = $this->errorsText['upload'];
      }
      
    }
    
    return $file;
  }
}
