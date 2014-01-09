<?php
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Accua_Forms_Submissions_List_Table extends WP_List_Table {

    var $message = NULL;
    var $active_items = 0;
    var $del_items = 0;
  
    function __construct(){
        global $status, $page;
        $this->message = '';
        parent::__construct( array(
            'singular'  => 'submission',
            'plural'    => 'submissions',
            'ajax'      => false
        ) );
    }
    
    function get_num_of_active_items () {
      return $this->active_items;
    }
    
    function get_num_of_del_items () {
      return $this->del_items;
    }
    
    function column_default($item, $column_name){
      if (isset($item[$column_name])) {
        //return htmlspecialchars($item[$column_name], ENT_QUOTES);
        return $item[$column_name];
      } else {
        return '';
      }
    }
    
    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item['ID']                //The value of the checkbox should be the record's id
        );
    }
    
    function set_message($single_message) {
      $this->message=$single_message;
    }
    
    function get_message() {
      if($this->message!=NULL)
        return $this->message;
      else
        return NULL;
    }
    
    function get_columns(){
        global $wpdb;
        $columns = array(
            'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
            'ID' => 'ID',
            'form_title' => 'Form',
            'form_id' => __("Form ID", 'accua-form-api'),
            'pid' => __("Page ID", 'accua-form-api'),
            'ip' => 'IP',
            'uri'  => __("Page", 'accua-form-api'),
            'referrer' => __("Referrer", 'accua-form-api'),
            'lang' => __("Language", 'accua-form-api'),
            'created' => __("Opened", 'accua-form-api'),
            'submitted' => __("Submitted", 'accua-form-api'),
        );
        $query = "SELECT DISTINCT afsv_field_id FROM `{$wpdb->prefix}accua_forms_submissions_values`"; 
        $res = $wpdb->get_col($query);
        
        $avail_fields = get_option('accua_forms_avail_fields', array());
        foreach ($res as $col) {
          if (empty($avail_fields[$col])) {
            $columns['_field_'.$col] = $col . __("removed", 'accua-form-api');
          } else {
            $columns['_field_'.$col] = $avail_fields[$col]['name'];
          }
        }
        return $columns;
    }
    
    function get_bulk_actions() {
      if(isset($_GET['del']) && $_GET['del']==1) {
        $actions = array(
            'restore' => __('Restore', 'accua-form-api')
        );
      } else {
        $actions = array(
            'delete' => __('Move to trash', 'accua-form-api')
        );
      }
      return $actions;
    }
    
    function prepare_items($all=false) {
        global $wpdb, $hook_suffix;
        $per_page = 20;
        $del = isset($_GET['del']) && $_GET['del']==1;
        
        $this->active_items = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}accua_forms_submissions` WHERE afs_status >= 0");
        $this->del_items = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}accua_forms_submissions` WHERE afs_status < 0");
        
        $filter = $filter_query_custom_field = "";
        $search = NULL;
        
        if(isset($_GET['_wp_http_referer'])) {
          foreach (explode('&', $_GET['_wp_http_referer']) as $coppia) {
            $param = explode("=", $coppia);
            if($param[0]=='fid' && $param[1]!='-1') $filter .= " AND afs_form_id = '".$param[1]."' ";
            if($param[0]=='pid' && $param[1]!='-1') $filter .= " AND afs_post_id = ".$param[1]. " ";
            if($param[0]=='s' && $param[1]!='-1')  $search = $param[1];
          }
        }
        
        if(isset($_GET['fid']) && ($_GET['fid'])!=-1) {
          $filter .= " AND afs_form_id = '".$_GET['fid']."' ";
        }
        if(isset($_GET['pid']) && ($_GET['pid']!=-1)) {
          $filter .= " AND afs_post_id = ".$_GET['pid']. " ";
        }

        if(isset($_GET['s']) && ($_GET['s'])!=-1) {
            $search = $_GET['s'];
        }
        
        $columns = $this->get_columns();
        $hidden = get_hidden_columns($hook_suffix);
        $sortable = array();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        $current_page = $this->get_pagenum();
        
        $limit = ($current_page - 1) * $per_page;
        
        $forms_data = get_option('accua_forms_saved_forms', array());
        
        if ($del) {
          $afs_status_cond = 'afs_status < 0';
        } else {
          $afs_status_cond = 'afs_status >= 0';
        }
        
        $searching_data = array();
        if( $search != NULL && $search!= '')
        {
            $search = trim($search); 
            $query1 = " SELECT SQL_CALC_FOUND_ROWS
            afs_id AS ID,
            afs_form_id AS form_id,
            afs_post_id AS pid,
            afs_ip AS ip,
            afs_uri AS uri,
            afs_referrer AS referrer,
            afs_lang AS lang,
            afs_created AS created,
            afs_submitted AS submitted
            FROM `{$wpdb->prefix}accua_forms_submissions`
            WHERE $afs_status_cond ".$filter."
            AND (
            afs_ip LIKE '%%".$search."%%'
            OR afs_uri LIKE '%%".$search."%%'
            OR afs_referrer LIKE '%%".$search."%%'
            OR afs_lang LIKE '%%".$search."%%'
            OR afs_created LIKE '%%".$search."%%'
            OR afs_submitted LIKE '%%".$search."%%'
            OR afs_id LIKE '%%".$search."%%'
            OR afs_id
            IN (
              SELECT DISTINCT (afsv_sub_id)
              FROM `{$wpdb->prefix}accua_forms_submissions_values`
              WHERE afsv_value LIKE '%%".$search."%%')
             )
            ORDER BY afs_id DESC";
        }
         else 
        {
          $query1 = "SELECT SQL_CALC_FOUND_ROWS
          afs_id AS ID,
          afs_form_id AS form_id,
          afs_post_id AS pid,
          afs_ip AS ip,
          afs_uri AS uri,
          afs_referrer AS referrer,
          afs_lang AS lang,
          afs_created AS created,
          afs_submitted AS submitted
          FROM `{$wpdb->prefix}accua_forms_submissions`
          WHERE $afs_status_cond ".$filter."
          ORDER BY afs_id DESC";
        }

        $data1 = $wpdb->get_results($query1, ARRAY_A);
        
        $total_items = $wpdb->get_var('SELECT FOUND_ROWS()');
        
        $data = array();
        $submissions = array();
        foreach ($data1 as $row) {
          $fid = $row['form_id'];
          
          if (isset($forms_data[$fid]['title']) && (trim($forms_data[$fid]['title']) !== '')) {
            $row['form_title'] = htmlspecialchars($forms_data[$fid]['title']);
          } else {
            $row['form_title'] = $fid;
          }
          
          $sid = $row['ID'];
          $data[$sid] = $row;
          $submissions[] = $sid;
        }
        
        $submissions = implode(',',$submissions);
        $query2 = "SELECT *
              FROM `{$wpdb->prefix}accua_forms_submissions_values`
              WHERE afsv_sub_id IN ($submissions) ";
        
        $data2 = $wpdb->get_results($query2, OBJECT);
        
        foreach ($data2 as $row) {
          switch ($row->afsv_type) {
            case 'file' :
              $fieldid = rawurlencode($row->afsv_field_id);
              $filename = rawurlencode($row->afsv_value);
              $url = admin_url('admin-ajax.php') . "?action=accua_forms_download_submitted_file&subid={$row->afsv_sub_id}&field={$fieldid}&file={$filename}";
              $url = htmlspecialchars($url,ENT_QUOTES);
              $filename = htmlspecialchars($row->afsv_value,ENT_QUOTES);
              $fielddata = "<a href='{$url}' target='_blank'>{$filename}</a>";
            break;
            case 'colorpicker':
              if ($row->afsv_value === '') {
                $fielddata = '';
              } else {
                $value_esc = htmlspecialchars($row->afsv_value,ENT_QUOTES);
                $fielddata = "<span style='color: {$value_esc}'><font color='{$value_esc}'>&#9608;</font></span> $value_esc";
              }
            break;
            default:
              $fielddata = htmlspecialchars($row->afsv_value,ENT_QUOTES);
          }
          $data[$row->afsv_sub_id]['_field_'.$row->afsv_field_id] = $fielddata;
        }
        
        $total_items = count($data);
        if($all) {
          $this->items = $data;
        }
        else {
          $this->items = array_slice($data,(($current_page-1)*$per_page),$per_page);
        }
       
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items/$per_page)
        ) );
        
        
    }
    
    function process_bulk_action() {
      if('delete'=== $this->current_action()) {
        $trashed = array();
        if ((!empty($_GET['submission'])) && is_array($_GET['submission'])) {
          foreach($_GET['submission'] as $i) {
            $i = (int) $i;
            $trashed[$i] = $i;
          }
        }
        if ($trashed) {
          $trashed = implode(',',$trashed);
          global $wpdb;
          $res = $wpdb->query("UPDATE `{$wpdb->prefix}accua_forms_submissions` SET afs_status = -1 WHERE afs_id in ( $trashed )");
          if ($res === false) {
            $this->set_message(__("Error moving submissions to trash.", 'accua-form-api').'<br />');
          } else if ($res == 1) {
            $this->set_message(__("Moved 1 submission to trash.", 'accua-form-api').'<br />');
          } else {
            $this->set_message(strtr(__("Moved %res submissions to trash.", 'accua-form-api'), array('%res' => $res)).'<br />');
          }
        } else {
          $this->set_message(__("No submission selected.", 'accua-form-api').'<br />');
        }
      } else if('restore'=== $this->current_action()) {
        $restored = array();
        if ((!empty($_GET['submission'])) && is_array($_GET['submission'])) {
          foreach($_GET['submission'] as $i) {
            $i = (int) $i;
            $restored[$i] = $i;
          }
        }
        if ($restored) {
          $restored = implode(',',$restored);
          global $wpdb;
          $res = $wpdb->query("UPDATE `{$wpdb->prefix}accua_forms_submissions` SET afs_status = 0 WHERE afs_id in ( $restored )");
          if ($res === false) {
            $this->set_message(__("Error restoring submissions.", 'accua-form-api').'<br />');
          } else if ($res == 1) {
            $this->set_message(__("Restored 1 submission.", 'accua-form-api').'<br />');
          } else {
            $this->set_message(strtr(__("Restored %res submissions.", 'accua-form-api'), array('%res' => $res)).'<br />');
          }
        } else {
          $this->set_message(__("No submission selected.", 'accua-form-api').'<br />');
        }
      }
    }
}

function accua_forms_submissions_list_page($head = false){
    static $listTable = null;
    
    global $wpdb;
    if ($listTable === null) {
      if (isset($_POST['action'])) {
        if ($_POST['action'] === 'trash' && !empty($_POST['submission']) && is_array($_POST['submission'])) {
          echo "yes";
          $trashed = array();
          foreach($_POST['submission'] as $i) {
            $i = (int) $i;
            $trashed[$i] = $i;
          }
          $trashed=implode(',',$trashed);
         
          $res = $wpdb->query("UPDATE `{$wpdb->prefix}accua_forms_submissions` SET afs_status = -1 WHERE afs_id in ( $trashed )");
          if ($res === false) {
            $this->set_message(__("Error moving submissions to trash.", 'accua-form-api').'<br />');
          } else {
            $this->set_message(strtr(__("Moved %res submissions to trash.", 'accua-form-api'), array('%res' => $res)).'<br />');
          }
        }
      }
      
      /*
      wp_enqueue_script('jquery-ui-mouse');
      wp_enqueue_script('jquery-ui-widget');
      */
      
      wp_enqueue_script('jquery');
      wp_enqueue_script('jquery-ui-core');
      wp_enqueue_script('jquery-ui-sortable');
      
      $listTable = new Accua_Forms_Submissions_List_Table();
      $listTable->process_bulk_action();
      $listTable->prepare_items();
    }
    
    if ($head === true) {
      return;
    }
?>
<style>
.tablenav.bottom {
    float: left;
    width: 100%;
}

.accua_tablenav {
  float: left;
}

.esportazione {
  margin-left:10px;
}

.esportazione a {
    cursor: pointer;
    line-height: 28px;
    text-decoration: underline;
}
.tablenav.top {
    clear: none;
    float: left;
    width: 100%;
}
</style>
    <div id="accua_forms_submissions_list_page" class="accua_forms_admin_page wrap">
        
        <div id="icon-users" class="icon32"><br/></div>

        <h2 style="margin-bottom: 20px;" ><?php _e("Forms Submissions", 'accua-form-api'); ?></h2>
      
        <?php $filter_form = $filter_post = $filter_search =  Null ;
        $filter = '';
        if(isset($_GET['_wp_http_referer'])) {
          foreach (explode('&', $_GET['_wp_http_referer']) as $coppia) {
            $param = explode("=", $coppia);
          
            if($param[0]=='fid' && $param[1]!='-1') { $filter_form =$param[1];  }
            if($param[0]=='pid' && $param[1]!='-1') { $filter_post =$param[1];  }
            if($param[0]=='s' && $param[1]!='-1') { $filter_search =$param[1]; $filter.= "&amp;s=".$filter_search; ?>
              <script type="text/javascript">
              <!--
              jQuery(document).ready(function($) {
                jQuery('#search_id-search-input').val(<?php echo json_encode($filter_search); ?>);
              });
              //-->
              </script>
            <?php
            }
          }
        }
        
        if(isset($_GET['fid']) && ($_GET['fid'])!=-1) {
           $filter_form = $_GET['fid'];
        }
        if(isset($_GET['pid']) && ($_GET['pid']!=-1)) {
          $filter_post = $_GET['pid'];
        } 
        if(isset($_GET['s']) && ($_GET['s']!=-1)) {
          $filter_search = $_GET['s'];
          $filter.= "&amp;s=".$filter_search;
        }
        
        $del = isset($_GET['del']) && ($_GET['del']==1);
        
        ?>
        
       <?php if($listTable->get_message()!=NULL) { ?>
          <div class="updated"><p><?php echo $listTable->get_message(); ?></p></div>
       <?php } ?>
       
       <ul class="subsubsub">
          <li>
            <a <?php if (!$del) { echo " class='current' "; } ?> href="admin.php?page=accua_forms_submissions_list">
            <?php _e("Active", 'accua-form-api'); ?></a> (<?php echo $listTable->get_num_of_active_items(); ?>) |
          </li>
          <li>
            <a  <?php if ($del) { echo " class='current' "; } ?> href="admin.php?page=accua_forms_submissions_list&del=1">
            <?php _e("Trash", 'accua-form-api'); ?></a> (<?php echo $listTable->get_num_of_del_items(); ?>)
          </li>
       </ul>
       
       <p style="clear:both;"><?php _e("Use the screen options to add or remove columns from the table below. Only the visible columns will be exported.", 'accua-form-api'); ?></p>
       <form style="margin-top: 20px;" id="submissions-action" method="get" action="">
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
              <input type="hidden" name="page" value="<?php echo htmlspecialchars(stripslashes($_REQUEST['page'])); ?>" />
              <input type="hidden" name="del" value="<?php echo htmlspecialchars(stripslashes($_REQUEST['del'])); ?>" />
               <!-- SEARCH FORM -->
               <?php $listTable->search_box(__("Search", 'accua-form-api'), 'search_id'); ?>
               <!-- FINE SEARCH FORM -->
        <!-- FILTRI --> 
        <div class="accua_tablenav">       
        <?php
            //tutti i form salvati e non
            
            $saved_forms_id = array();
            $forms_data = get_option('accua_forms_saved_forms', array());
            foreach($forms_data as $single_form_key=>$single_form){ 
              $saved_forms_id[]= "'".$wpdb->escape($single_form_key)."'";
            }
            $saved_forms_id_string = implode(',',$saved_forms_id);
            
            $id_del_other_forms = $wpdb->get_results("SELECT distinct afs_form_id
                FROM {$wpdb->prefix}accua_forms_submissions WHERE afs_form_id NOT IN ($saved_forms_id_string) AND afs_status >= 0", ARRAY_A);
            

            //post submissions
            $id_posts = $wpdb->get_results("SELECT distinct afs_post_id
			          FROM {$wpdb->prefix}accua_forms_submissions WHERE afs_post_id <> 0 AND afs_status >= 0 ", ARRAY_A); 
            
            
            ?>
           
            <span><label><?php _e("Page: ", 'accua-form-api'); ?></label><select name='pid' id='pid'>
              <option value="-1" selected ><?php _e("Show all pages", 'accua-form-api'); ?></option>
              <?php foreach($id_posts as $id_post){
                  	 echo "<option value='".$id_post['afs_post_id']."'";
                  	 if($filter_post==$id_post['afs_post_id']) {
                        echo " selected ";
                        $filter.= "&amp;pid=".$filter_post;
                     }
                  	 echo ">".get_the_title($id_post['afs_post_id'])."</option>";
                    } ?>
              </select></span>
              <span style="margin-left: 10px;"><label><?php _e("Forms: ", 'accua-form-api'); ?></label><select name='fid' id='fid'>
              <option value="-1" selected > <?php _e("Show all forms", 'accua-form-api'); ?> </option>
              <?php foreach($forms_data as $single_form_key=>$single_form){
                  	 echo "<option value='".$single_form_key."'";
                  	 if($filter_form==$single_form_key) {
                          echo " selected ";
                          $filter.= "&amp;fid=".$filter_form;
                     }
                  	 echo ">";
                  	 if($single_form['title']!=NULL)
                  	   echo $single_form['title']; //TITOLO
                  	 else 
                  	   echo $single_form_key; //ID
                  	 echo "</option>";
                  	 }
                  	 ///no saved forms///
                  	 foreach($id_del_other_forms as $id_del_other_form) {
                  	   echo "<option value='".$id_del_other_form['afs_form_id']."'";
                        if($filter_form==$id_del_other_form['afs_form_id']) {
                          echo " selected ";
                          $filter.= "&amp;fid=".$filter_form;
                        }
                        echo $id_del_other_form['afs_form_id']." (del) </option>";
                  	 }
                  	 
                     ?>
              </select>
              </span><span>
              <input type="submit" value="<?php _e("Filter", 'accua-form-api'); ?>" class="button-secondary action" name=""></span>
          </form>
          </div>   
          <!-- FINE FILTRI -->    
           <script type="text/javascript">
          <!--
             function set_parameter(sel_column) {
               var stringa ='';
               var name_action = ajaxurl + "?action=accua_forms_submission_page_save_excel<?php if ($del) {echo '&del=1';} ?>"; 
               if(sel_column) { 
                 jQuery('#adv-settings input[type=checkbox]:checked').each(function() {
                   stringa += jQuery(this).attr("id").replace("-hide","")  + "%2C";
                 });
               }
               else {
                 jQuery('#adv-settings input[type=checkbox]').each(function() {
                   stringa += jQuery(this).attr("id").replace("-hide","")  + "%2C";
                 });
               }
               var search = jQuery("#search_id-search-input").val();
               var pid = jQuery("select#pid").val();
               var fid = jQuery("select#fid").val();   
               if (search) name_action+='&s='+search;
               if (pid) name_action+='&pid='+pid;
               if (fid) name_action+='&fid='+fid;
               if(stringa) name_action+= '&accua_show_field='+stringa;
               
               if(sel_column) { jQuery('#esporta_link_visible_column').attr("href",name_action); }
               else jQuery('#esporta_link_all_column').attr("href",name_action);
               
             }
             //-->
           </script>
       <!-- ESPORTAZIONE --> 
         
       <div class="accua_tablenav esportazione wp-core-ui ">  
         <a onclick ="set_parameter(1);" id="esporta_link_visible_column" class="button-primary"><?php _e("Export visible columns to Excel", 'accua-form-api'); ?></a>  
         <a onclick ="set_parameter(0);" id="esporta_link_all_column" class="button-primary"><?php _e("Export all columns to Excel", 'accua-form-api'); ?></a>  
       </div>
                   
       <!-- FINE ESPORTAZIONE -->      
       <form style="margin-top: 20px;" id="submissions-action" method="get" action="">
       <!-- Now we can render the completed list table -->
         <input type="hidden" name="page" value="<?php echo htmlspecialchars(stripslashes($_REQUEST['page'])); ?>" />
         <input type="hidden" name="del" value="<?php echo htmlspecialchars(stripslashes($_REQUEST['del'])); ?>" />
            <?php $listTable->display(); ?>
        </form>
        <?php /* <pre>$listTable = <?php echo htmlspecialchars(print_r($listTable, true)); ?></pre> */ ?>
    </div>

    <?php
    
}




