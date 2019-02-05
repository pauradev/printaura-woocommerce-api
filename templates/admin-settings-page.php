<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
function printaura_get_web_page($url_setting){
  $content = wp_remote_retrieve_body( wp_remote_get( $url_setting) );
  return ($content);
}
$max_upload = (int)(ini_get('upload_max_filesize'));
$upl = wp_upload_dir() ;
$upload_dir = $upl['path'];
$is_writable = is_writable($upload_dir);
$permition_message=($is_writable) ? "<font color='green'> Looks good! This folder is writable</font>" : "<font color='red'>is not-writable, Please visit <a href='http://codex.wordpress.org/Changing_File_Permissions'  target='_blank'> this tutorial</a> to change your file permissions</font>";
$url_setting = "https://printaura.com/printaura-woocommerce-api-settings/";
$content = printaura_get_web_page($url_setting);
$content = str_replace(array('[MAX_UPLOAD]','[UPLOAD-DIR]','[FILE-PERMISSIONS-MESSAGE]'),array($max_upload,$upload_dir,$permition_message),$content);
print_r ($content);
?>
<form method="post" id="mainform" action="">
<?php
echo $helpers->hiddenFormFields($helpers->getPluginPrefix() . '_sitewide_settings');
?>
<table class="form-table">
<?php
foreach ($attrs['json_api_sitewide_settings']['fields'] as $value) {
  if($helpers->orEq($value,'visible','')) {
?>
      <tr>
      <td width="40%" valign="top">
      <strong>
      <?php
      echo $helpers->labelTag($value)
      ?>
      </strong><br />
      <?php
      echo $value['description']
      ?>
      </td>
      <td>
      <?php
      if ( $helpers->orEq($value,'type','') == 'text') {
        echo $helpers->inputTag( $value );
      } else if ( $helpers->orEq($value,'type','') == 'textarea' ) {
        echo $helpers->textAreaTag( $value );
      } else if ( $helpers->orEq( $value, 'type', '') == 'select') {
        echo $helpers->selectTag( $value );
      }
    ?>
      </td>
      </tr>
      <?php
  }
}
?>
<tr>
<td colspan="2" align="left">
<input type="submit" class="button button-primary" name="submit" value="<?php _e('Save Changes') ?>" />
</td>
</tr>
</table>
</form>
</div>