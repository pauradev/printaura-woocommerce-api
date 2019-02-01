

<form method="post" id="mainform" action="">
    <?php echo $helpers->hiddenFormFields($helpers->getPluginPrefix() . '_mail_settings'); ?>
<h3><?php echo $attrs['order_mail_settings']['option']; ?></h3>
<p><?php echo $attrs['order_mail_settings']['option_desc']; ?></p>
<table class="form-table">
<tbody>
    <?php foreach ($attrs['order_mail_settings']['fields'] as $value) {
        if ( $helpers->orEq($value,'type','') == 'text') {
        ?>
    <tr valign="top">
					<th scope="row" class="titledesc">
						<?php echo $helpers->labelTag($value) ?>
					</th>
                    <td class="forminp">
                        <?php echo $helpers->inputTag( $value ); ?>
                    </td>
     </tr>
     <?php
    }
    }
     ?>
</tbody></table>
<h3>Email Template</h3>
<table class="form-table">

<tbody>
    <?php foreach ($attrs['order_mail_settings']['fields'] as $value) {
                if ( $helpers->orEq($value,'type','') == 'textarea') {
                    $mailconent= $value;
        ?>
    <tr valign="top">
					<th scope="row" class="titledesc">
						<?php echo $helpers->labelTag($value) ?>
					</th>
                    <td class="forminp">
                    	<p style="margin-top:0"><?php
                              echo $value['description']?></p>
                        <br /> <br />
                        <?php
                       
// echo $helpers->textAreaTag( $value );
                         wp_editor($value['value'],"order_email_body1", array('textarea_rows'=>12,'textarea_name'=>$value['name'], 'editor_class'=>'mytext_class')); 
                        ?>
                    </td>
      </tr>
        <?php
    }
    }
     ?>
      <tr>
       <th scope="row" class="titledesc">Variable Used:</th>   
       <td>
           {Sender} : Name 
       </td>
          
      </tr>
</tbody></table>
<p class="submit">
<input name="submit" type="submit" class="button-primary" value="Save changes">
</p>
</form>
