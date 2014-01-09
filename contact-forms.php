<?php
/*
Plugin Name: WordPress Contact Forms by Cimatti
Description: Quickly create and publish forms in your WordPress powered website.
Version: 1.3
Plugin URI: http://www.cimatti.it/wordpress/contact-forms/
Author: Cimatti Consulting
Author URI: http://www.cimatti.it
*/

/*
WordPress Contact Forms by Cimatti
Copyright (c) 2011-2013 Andrea Cimatti

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.


The full copy of the GNU General Public License is available here: http://www.gnu.org/licenses/gpl.txt

*/

define('ACCUA_FORMS_DB_VERSION', '1');

require_once('accua-form-api.php');
require_once('accua-forms.php');
require_once('accua-shortcode-button.php');

function accua_dashboard_page_head(){
  $screen = get_current_screen();
  $screen->add_help_tab( array(
    'id'	=> 'accua_help_tab',
    'title'	=> __('WordPress Contact Forms by Cimatti'),
    'content'	=> '<p>' . __( 'Marketing tools for WordPress', 'accua-form-api') . '</p>',
  ) );
  
  wp_enqueue_script('accua', plugins_url('/flot/jquery.flot.js', __FILE__ ), array( 'jquery' ));
  //wp_enqueue_style('accua_flot',  plugins_url('/flot/layout.css', __FILE__ ));
  wp_enqueue_style('accua',  plugins_url('accua.css', __FILE__ ));
  
}

function accua_dashboard_page()
{ 
  global $wpdb;
  $num_posts = $wpdb->get_results("SELECT count(DISTINCT afs_post_id ) as num_row
		FROM {$wpdb->prefix}accua_forms_submissions WHERE afs_post_id <> 0 AND afs_status >= 0", ARRAY_A);
  $num_forms = $wpdb->get_results("SELECT count(DISTINCT afs_form_id ) as num_row
		FROM {$wpdb->prefix}accua_forms_submissions WHERE afs_status >= 0", ARRAY_A);
  $num_submissions = $wpdb->get_results("SELECT count(*) as num_row
    FROM {$wpdb->prefix}accua_forms_submissions WHERE afs_status >= 0", ARRAY_A);
  
  //seleziono i campi email
  $email_fields = array();
  $avail_fields = get_option('accua_forms_avail_fields', array());
  foreach($avail_fields as $key=>$single_field){
    if($single_field['type'] == 'email' || $single_field['type'] == 'autoreply_email')
      $email_fields[] = "'".$key."'";
  }
  $email_fields_string = implode(',',$email_fields);
  $num_emails = $wpdb->get_results("SELECT COUNT(DISTINCT (`afsv_value`)) as num_row
    FROM {$wpdb->prefix}accua_forms_submissions, {$wpdb->prefix}accua_forms_submissions_values
    WHERE `afs_id` = `afsv_sub_id`
    AND afsv_field_id IN ({$email_fields_string})
    AND afs_status >= 0", ARRAY_A);
  
  
  $forms_data = get_option('accua_forms_saved_forms', array());
  
  $fid = '';
  $fid_param = '';
  if (isset($_GET['fid'])) {
    $fid = stripslashes( (string) $_GET['fid'] );
    if (isset($forms_data[$fid])) {
      $fid_param = $wpdb->prepare('AND afs_form_id = %s', $fid);
    } else {
      $fid = '';
    }
  }
  
  
  //costruisco il grafico
  
  $query_grafico = $wpdb->get_results("SELECT YEAR( afs_submitted ) AS `year` , MONTH( afs_submitted ) AS `month` , COUNT( DISTINCT (`afs_id`) ) AS `submissions` , COUNT( DISTINCT (`afsv_value`)) AS `unique_submissions`
  FROM `{$wpdb->prefix}accua_forms_submissions`
    LEFT JOIN `{$wpdb->prefix}accua_forms_submissions_values` ON `afs_id` = `afsv_sub_id`
  WHERE afsv_field_id IN ({$email_fields_string})
  AND afs_status >= 0
  $fid_param
  GROUP BY `year` , `month`
  ORDER BY `year` DESC , `month` DESC");
   ?>
   
  <div id="accua_dashboard_page" class="accua_forms_admin_page wrap">
    <div id="icon-contact-forms-cimatti-logo" class="icon32"><br></div>
    <h2>WordPress Contact Forms by Cimatti</h2>
    <div class="metabox-holder accua-forms-metabox-holder">
      <div class="postbox ">
        <h3 class="hndle"><span><?php _e('Forms Stats', 'accua-form-api'); ?></span></h3>
        <div class="inside" id="dashboard_right_now">
          <table>
	          <tr class="first"><td class="first b"><?php echo $num_forms[0]['num_row']; ?></td><td class="t"><?php _e('Forms', 'accua-form-api'); ?></td></tr>
	          <tr class="first"><td class="first b"><?php echo $num_posts[0]['num_row']; ?></td><td class="t"><?php _e('Pages', 'accua-form-api'); ?></td></tr>
	          <tr class="first"><td class="first b"><?php echo $num_submissions[0]['num_row']; ?></td><td class="t"><?php _e('Submissions', 'accua-form-api'); ?></td></tr>
	          <tr class="first"><td class="first b"><?php echo $num_emails[0]['num_row']; ?></td><td class="t"><?php _e('Distinct Emails', 'accua-form-api'); ?></td></tr>
	        </table>
	        <form method="get">
	        <input type="hidden" name="page" value="accua_forms" />
	        <select name="fid">
	        <?php
	        $selected = ($fid === '') ? 'selected="selected"' : '';
	        echo "<option value='' $selected >", __('Submissions from all forms', 'accua-form-api'),'</option>';
	        foreach($forms_data as $ffid => $form) {
	          $selected = ($fid == $ffid) ? 'selected="selected"' : '';
	          $title = trim($form['title']);
	          if ($title === '') {
	            $title = $ffid;
	          }
	          $ffid = htmlspecialchars($ffid, ENT_QUOTES);
	          $title = htmlspecialchars($title, ENT_QUOTES);
	          echo "<option value='$ffid' $selected >$title</option>";
	        }
	        ?>
	        </select>
	        <input type="submit" value="<?php _e('filter', 'accua-form-api') ?>" />
	        </form>
          
	   <?php
	   if ($query_grafico) {
       echo '<div id="accua-forms-graph-content" ><div id="accua-form-report-graph" style="width: 99%"></div></div>';

       $data = array(
          array( 'label' => __('Unique monthly Submissions', 'accua-form-api'), 'data' => array()),
          array( 'label' => __('Total monthly Submissions', 'accua-form-api'), 'data' => array()),
       );
        for($i=date('Y'); $i>=$query_grafico[count($query_grafico)-1]->year; $i-- ) {
          $start_month=12;
          $end_month = 1;
          if($i==date('Y')) $start_month= date('n');
          if($i==$query_grafico[count($query_grafico)-1]->year) $end_month=$query_grafico[count($query_grafico)-1]->month;
          for($j=$start_month;$j>=$end_month;$j--) {
            $time = mktime(0, 0, 0, $j , 1, $i) * 1000;
            $find=false;
            foreach ($query_grafico as $result){
              if(!$find && (int)$result->year == $i && (int)$result->month==$j) {
                $data[0]['data'][] = array($time, (int)$result->unique_submissions,$j, $i);
                $data[1]['data'][] = array($time, (int)$result->submissions,$j, $i);
                $find=true;
              }
            }
            if(!$find)
            {
                $data[0]['data'][] = array($time, 0,$j, $i);
                $data[1]['data'][] = array($time, 0,$j, $i);
            }
          }
        }
       ?>
      <script type="text/javascript">
      var data = <?php echo json_encode($data); ?> ;
      var options = {
          xaxis: {
            //autoscaleMargin: 0.005,
            mode: "time",
            timeformat: "%b %y",
            minTickSize: [1, "month"]
          },
          legend: {
            position: "nw"
          },
         
          
      };
      jQuery(document).ready(function($) {
        jQuery.plot($("#accua-form-report-graph"), data, options); 
      });
      
      jQuery(window).resize(function() { 
        jQuery.plot(jQuery("#accua-form-report-graph"), data, options); 
      });
      </script>
      <?php
        } else {
          echo '<p style="height:100px;">', __('No forms submitted', 'accua-form-api'), '</p>';
        } ?>
      <?php 
         $plugin_data = get_plugin_data(__FILE__);
         _e('Version', 'accua-form-api'); echo " ".$plugin_data['Version'];
       ?>
      </div>
       
    </div>
    <span id="accua-forms-version">by Cimatti - <a href="http://www.cimatti.it/wordpress/contact-forms/">www.cimatti.it/wordpress/contact-forms</a>
      <br /><?php 
         $plugin_data = get_plugin_data( __FILE__ );
         _e('Version', 'accua-form-api'); echo " ".$plugin_data['Version'];
       ?></span>
  </div>
<?php

}

register_activation_hook(__FILE__, 'accua_forms_install');
function accua_forms_install(){
  $modified = false;
  $keys = get_option('accua_form_api_keys', array());
  if (!isset($keys['hash'])) {
    $modified = true;
    $keys['hash'] = wp_generate_password(64,true,true);
  }
  if (!isset($keys['aes'])) {
    $modified = true;
    $keys['aes'] = wp_generate_password(64,true,true);
  }
  if ($modified) {
    update_option('accua_form_api_keys', $keys);
  }

  global $wpdb;

  require_once(ABSPATH.'wp-admin/upgrade-functions.php');

  $charset_collate = '';
  if ( ! empty($wpdb->charset) )
    $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
  if ( ! empty($wpdb->collate) )
    $charset_collate .= " COLLATE $wpdb->collate";

  $sql = "CREATE TABLE `{$wpdb->prefix}accua_forms_submissions` (
  afs_id BIGINT(20) NOT NULL AUTO_INCREMENT,
  afs_form_id VARCHAR(77) NOT NULL DEFAULT '',
  afs_post_id BIGINT(20) NOT NULL DEFAULT 0,
  afs_ip VARCHAR(255) NOT NULL DEFAULT '',
  afs_uri VARCHAR(255) NOT NULL DEFAULT '',
  afs_referrer VARCHAR(255) NOT NULL DEFAULT '',
  afs_lang VARCHAR(60) NOT NULL DEFAULT '',
  afs_created TIMESTAMP NOT NULL DEFAULT 0,
  afs_submitted TIMESTAMP NOT NULL DEFAULT 0,
  afs_status TINYINT(1) NOT NULL DEFAULT 0,
  afs_stats TEXT NOT NULL DEFAULT '',
  PRIMARY KEY  (afs_id),
  KEY form (afs_form_id, afs_status),
  KEY uri (afs_uri),
  KEY pid (afs_post_id),
  KEY referrer (afs_referrer),
  KEY status (afs_status, afs_id)
  ) $charset_collate;";
  dbDelta($sql);

  $sql = "CREATE TABLE `{$wpdb->prefix}accua_forms_submissions_values` (
  afsv_sub_id BIGINT(20) NOT NULL,
  afsv_field_id VARCHAR(77) NOT NULL,
  afsv_type varchar(255) NOT NULL DEFAULT '',
  afsv_value TEXT NOT NULL DEFAULT '',
  PRIMARY KEY  (afsv_sub_id, afsv_field_id),
  KEY value_index (afsv_field_id(77), afsv_value(256))
  ) $charset_collate;";
  dbDelta($sql);
  
  $avail_fields = get_option('accua_forms_avail_fields', array());
  if (!is_array($avail_fields)) {
    $avail_fields = array();
  }
  $avail_fields += array(
    'first_name' => array(
      'id' => 'first_name',
      'name' => 'First Name',
      'type' => 'textfield',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
    'last_name' => array(
      'id' => 'last_name',
      'name' => 'Last Name',
      'type' => 'textfield',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
    'email' => array(
      'id' => 'email',
      'name' => 'Email',
      'type' => 'autoreply_email',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
    'address' => array(
      'id' => 'address',
      'name' => 'Address',
      'type' => 'textfield',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
    'city' => array(
      'id' => 'city',
      'name' => 'City',
      'type' => 'textfield',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
    'state_province' => array(
      'id' => 'state_province',
      'name' => 'State/Province',
      'type' => 'textfield',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
    'country' => array(
      'id' => 'country',
      'name' => 'Country',
      'type' => 'select',
      'description' => '',
      'default_value' =>  '-',
      'allowed_values' =>  "-|Select...
Afghanistan
Åland Islands
Albania
Algeria
American Samoa
Andorra
Angola
Anguilla
Antarctica
Antigua And Barbuda
Argentina
Armenia
Aruba
Australia
Austria
Azerbaijan
Bahamas
Bahrain
Bangladesh
Barbados
Belarus
Belgium
Belize
Benin
Bermuda
Bhutan
Bolivia
Bosnia And Herzegovina
Botswana
Bouvet Island
Brazil
British Indian Ocean Territory
Brunei Darussalam
Bulgaria
Burkina Faso
Burundi
Cambodia
Cameroon
Canada
Cape Verde
Cayman Islands
Central African Republic
Chad
Chile
China
Christmas Island
Cocos (Keeling) Islands
Colombia
Comoros
Congo
Congo, The Democratic Republic Of The
Cook Islands
Costa Rica
Côte D'Ivoire
Croatia
Cuba
Cyprus
Czech Republic
Denmark
Djibouti
Dominica
Dominican Republic
Ecuador
Egypt
El Salvador
Equatorial Guinea
Eritrea
Estonia
Ethiopia
Falkland Islands (Malvinas)
Faroe Islands
Fiji
Finland
France
French Guiana
French Polynesia
French Southern Territories
Gabon
Gambia
Georgia
Germany
Ghana
Gibraltar
Greece
Greenland
Grenada
Guadeloupe
Guam
Guatemala
Guernsey
Guinea
Guinea-Bissau
Guyana
Haiti
Heard Island And Mcdonald Islands
Holy See (Vatican City State)
Honduras
Hong Kong
Hungary
Iceland
India
Indonesia
Iran, Islamic Republic Of
Iraq
Ireland
Isle Of Man
Israel
Italy
Jamaica
Japan
Jersey
Jordan
Kazakhstan
Kenya
Kiribati
Korea, Democratic People'S Republic Of
Korea, Republic Of
Kuwait
Kyrgyzstan
Lao People'S Democratic Republic
Latvia
Lebanon
Lesotho
Liberia
Libyan Arab Jamahiriya
Liechtenstein
Lithuania
Luxembourg
Macao
Macedonia, The Former Yugoslav Republic Of
Madagascar
Malawi
Malaysia
Maldives
Mali
Malta
Marshall Islands
Martinique
Mauritania
Mauritius
Mayotte
Mexico
Micronesia, Federated States Of
Moldova, Republic Of
Monaco
Mongolia
Montenegro
Montserrat
Morocco
Mozambique
Myanmar
Namibia
Nauru
Nepal
Netherlands
Netherlands Antilles
New Caledonia
New Zealand
Nicaragua
Niger
Nigeria
Niue
Norfolk Island
Northern Mariana Islands
Norway
Oman
Pakistan
Palau
Palestinian Territory, Occupied
Panama
Papua New Guinea
Paraguay
Peru
Philippines
Pitcairn
Poland
Portugal
Puerto Rico
Qatar
Réunion
Romania
Russian Federation
Rwanda
Saint Barthélemy
Saint Helena
Saint Kitts And Nevis
Saint Lucia
Saint Martin
Saint Pierre And Miquelon
Saint Vincent And The Grenadines
Samoa
San Marino
Sao Tome And Principe
Saudi Arabia
Senegal
Serbia
Seychelles
Sierra Leone
Singapore
Slovakia
Slovenia
Solomon Islands
Somalia
South Africa
South Georgia And The South Sandwich Islands
Spain
Sri Lanka
Sudan
Suriname
Svalbard And Jan Mayen
Swaziland
Sweden
Switzerland
Syrian Arab Republic
Taiwan, Province Of China
Tajikistan
Tanzania, United Republic Of
Thailand
Timor-Leste
Togo
Tokelau
Tonga
Trinidad And Tobago
Tunisia
Turkey
Turkmenistan
Turks And Caicos Islands
Tuvalu
Uganda
Ukraine
United Arab Emirates
United Kingdom
United States
United States Minor Outlying Islands
Uruguay
Uzbekistan
Vanuatu
Venezuela, Bolivarian Republic Of
Viet Nam
Virgin Islands, British
Virgin Islands, U.S.
Wallis And Futuna
Western Sahara
Yemen
Zambia
Zimbabwe",
    ),
    'message' => array(
      'id' => 'message',
      'name' => 'Message',
      'type' => 'textarea',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
    'captcha' => array(
      'id' => 'captcha',
      'name' => 'Captcha',
      'type' => 'captcha',
      'description' => '',
      'default_value' =>  '',
      'allowed_values' =>  '',
    ),
  );
  update_option('accua_forms_avail_fields', $avail_fields);
  
  
  $form_data = get_option('accua_forms_default_form_data',array());
  if (!is_array($form_data)) {
    $form_data = array();
  }
  $form_data += array(
    'success_message' => '<div style="padding: 10px; background-color: #fbf8b2; border: 1px solid #f7c84b;">
<h2>Thank you {first_name} {last_name},</h2>
We have received your contact request. Check your inbox for the confirmation message.

<strong>Can\'t find the email?</strong>

It doesn\'t happen often but your mailbox could apply strict spam rules that block our email from reaching your inbox. Try checking your spam folder.

Also check that the email you entered in the form (<strong>{email}</strong>) is correct. If it is not exact you can submit the form again.

For other possible problems you may have encountered do not hesitate to contact us

</div>',
    'error_message' => '<div style="padding: 10px; background-color: #fbf8b2; border: 1px solid #f7c84b;">
<h2>Oops! Something went wrong.</h2>
Internet is an awfully complex place and even though we take every precaution to make sure things run smoothly every once in a while things can go wrong that are not under our control.

Please try filling in the form again.

For other possible problems you may have encountered do not hesitate to contact us

</div>',
    'emails_from' => '',
    'admin_emails_to' => get_option('admin_email', ''),
    'emails_bcc' => '',
    'admin_emails_subject' => 'A contact from your site',
    'admin_emails_message' => '<table style="font-family: \'Lucida Sans\',\'Lucida Grande\', Verdana, Arial, Sans-Serif !important; background: #fff; margin-top: 10px; border: 1px solid #DDDDDD; max-width: 700px;" cellspacing="0" cellpadding="0" align="center">
<tbody>
<tr>
<td>
<table style="max-width:700px;" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody>
<tr>
<td>
<table style="padding: 10px; background-color: #fbf8b2; border: 1px solid #f7c84b; max-width:700px;" border="0" cellspacing="0" cellpadding="0" align="center" bgcolor="#FFFFFF">
<tbody>
<tr valign="top">
<td style="max-width:22px;"></td>
<td style="font-family: \'Lucida Sans\',\'Lucida Grande\', Verdana, Arial, Sans-Serif !important; padding: 15px 0px; max-width: 645px;">
<table>
<tbody>
<tr>
<td>On {__submitted_day_month_year} at {__submitted_hour} the following form was filled in.

<hr />

<strong>Page where the form was filled in</strong>
<a href="{__url}">{__url}</a></td>
</tr>
<tr>
<td id="submitted_html">{__submitted_html}</td>
</tr>
<tr>
<td>[form_if {__referrer} [<strong>Referrer</strong> - where did the contact come from <em>before reaching the page</em>: {__referrer}]]</td>
</tr>
<tr>
<td><strong>Contact IP</strong> {__ip}
<hr />
</td>
</tr>
<tr>
<td>[form_if {__autoreply} [The contact received in his email <a href="mailto:{email}">{email}</a> the following confirmation message:
<table>
<tbody>
<tr>
<td>{__confirmation_emails_message}</td>
</tr>
</tbody>
</table> ]]
</td>
</tr>
</tbody>
</table>
</td>
<td style="max-width:23px"></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>',
    'confirmation_emails_subject' => 'Your message',
    'confirmation_emails_message' => '<h2>Thank you {first_name} {last_name},</h2>
Thank Your for your contact request.

We will contact you as soon as possible.

Sincerely,

The Website Team

<hr />',

    'layout' => 'sidebyside',
    'style_margin' => '',
    'style_border_color' => '',
    'style_border_width' => '',
    'style_border_radius' => '',
    'style_background_color' => '',
    'style_padding' => '',
    'style_color' => '',
    'style_font_size' => '',
    'style_field_spacing' => '',
    'style_field_border_color' => '',
    'style_field_border_width' => '',
    'style_field_border_radius' => '',
    'style_field_background_color' => '',
    'style_field_padding' => '',
    'style_field_color' => '',
    'style_submit_border_color' => '',
    'style_submit_border_width' => '',
    'style_submit_border_radius' => '',
    'style_submit_background_color' => '',
    'style_submit_padding' => '',
    'style_submit_color' => '',
    'style_submit_font_size' => '',
  );
  update_option('accua_forms_default_form_data', $form_data);
  update_option('accua_forms_db_version', ACCUA_FORMS_DB_VERSION);
}

add_action('admin_init', 'accua_forms_check_db_version_and_update');
function accua_forms_check_db_version_and_update() {
  $db_version = get_option('accua_forms_db_version', '');
  if (ACCUA_FORMS_DB_VERSION != $db_version) {
    accua_forms_install();
  }
}
