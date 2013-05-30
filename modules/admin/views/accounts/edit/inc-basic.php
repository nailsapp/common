<fieldset id="edit-user-basic">

	<legend><?=lang( 'accounts_edit_basic_legend' )?></legend>
	
	<div class="box-container">
	<?php
		
		//	First Name
		$_field					= array();
		$_field['key']			= 'first_name';
		$_field['label']		= lang( 'form_label_first_name' );
		$_field['default']		= $user_edit->first_name;
		$_field['required']		= TRUE;
		$_field['placeholder']	= lang( 'accounts_edit_basic_field_first_placeholder' );
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Last name
		$_field					= array();
		$_field['key']			= 'last_name';
		$_field['label']		= lang( 'form_label_last_name' );
		$_field['default']		= $user_edit->last_name;
		$_field['required']		= TRUE;
		$_field['placeholder']	= lang( 'accounts_edit_basic_field_last_placeholder' );
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Email address
		$_field					= array();
		$_field['key']			= 'email';
		$_field['label']		= lang( 'form_label_email' );
		$_field['default']		= $user_edit->email;
		$_field['required']		= TRUE;
		$_field['placeholder']	= lang( 'accounts_edit_basic_field_email_placeholder' );
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Email verified
		$_field					= array();
		$_field['key']			= 'is_verified';
		$_field['label']		= lang( 'accounts_edit_basic_field_verified_label' );
		$_field['default']		= $user_edit->is_verified ? lang( 'yes' ) : lang( 'no' );
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Username
		$_field					= array();
		$_field['key']			= 'username';
		$_field['label']		= lang( 'accounts_edit_basic_field_username_label' );
		$_field['default']		= $user_edit->username;
		$_field['required']		= FALSE;
		$_field['placeholder']	= lang( 'accounts_edit_basic_field_username_placeholder' );
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Gender
		$_field					= array();
		$_field['key']			= 'gender';
		$_field['label']		= lang( 'accounts_edit_basic_field_gender_label' );
		$_field['default']		= $user_edit->gender;
		$_field['required']		= FALSE;
		
		$_options = array();
		$_options['undisclosed']	= 'Undisclosed';
		$_options['male']			= 'Male';
		$_options['female']			= 'Female';
		$_options['transgender']	= 'Transgender';
		$_options['other']			= 'Other';
		
		echo form_field_dropdown( $_field, $_options );
		
		// --------------------------------------------------------------------------
		
		//	Timezone
		$_field					= array();
		$_field['key']			= 'timezone_id';
		$_field['label']		= lang( 'accounts_edit_basic_field_timezone_label' );
		$_field['default']		= $user_edit->timezone_id;
		$_field['required']		= FALSE;
		
		echo form_field_dropdown( $_field, $timezones, lang( 'accounts_edit_basic_field_timezone_tip' ) );
		
		// --------------------------------------------------------------------------
		
		//	Preferred Language
		$_field					= array();
		$_field['key']			= 'language_id';
		$_field['label']		= lang( 'accounts_edit_basic_field_language_label' );
		$_field['default']		= $user_edit->language_setting->id;
		$_field['required']		= FALSE;
		
		echo form_field_dropdown( $_field, $languages, lang( 'accounts_edit_basic_field_language_tip' ) );
		
		// --------------------------------------------------------------------------
		
		//	Registered IP
		$_field					= array();
		$_field['key']			= 'ip_address';
		$_field['label']		= lang( 'accounts_edit_basic_field_register_ip_label' );
		$_field['default']		= $user_edit->ip_address;
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Last IP
		$_field					= array();
		$_field['key']			= 'last_ip';
		$_field['label']		= lang( 'accounts_edit_basic_field_last_ip_label' );
		$_field['default']		= $user_edit->last_ip;
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Created On
		$_field					= array();
		$_field['key']			= 'created';
		$_field['label']		= lang( 'accounts_edit_basic_field_created_label' );
		$_field['default']		= date( 'jS M Y @ H:i', strtotime( $user_edit->created ) );
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Created On
		$_field					= array();
		$_field['key']			= 'last_update';
		$_field['label']		= lang( 'accounts_edit_basic_field_modified_label' );
		$_field['default']		= date( 'jS M Y @ H:i', strtotime( $user_edit->last_update ) );
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Log in count
		$_field					= array();
		$_field['key']			= 'login_count';
		$_field['label']		= lang( 'accounts_edit_basic_field_logincount_label' );
		$_field['default']		= $user_edit->login_count ? $user_edit->login_count : lang( 'accounts_edit_basic_field_not_logged_in' );
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Last Log in
		$_field					= array();
		$_field['key']			= 'last_login';
		$_field['label']		= lang( 'accounts_edit_basic_field_last_login_label' );
		$_field['default']		= $user_edit->last_login ? date( 'jS M Y @ H:i', strtotime( $user_edit->last_login ) ) : lang( 'accounts_edit_basic_field_not_logged_in' );
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Referral Code
		$_field					= array();
		$_field['key']			= 'referral';
		$_field['label']		= lang( 'accounts_edit_basic_field_referral_label' );
		$_field['default']		= $user_edit->referral;
		$_field['required']		= FALSE;
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
		
		// --------------------------------------------------------------------------
		
		//	Referred by
		$_field					= array();
		$_field['key']			= 'referred_by';
		$_field['label']		= lang( 'accounts_edit_basic_field_referred_by_label' );
		$_field['default']		= $user_edit->referred_by;
		$_field['required']		= FALSE;
		$_field['placeholder']	= lang( 'accounts_edit_basic_field_referred_by_placeholder' );
		$_field['readonly']		= TRUE;
		
		echo form_field( $_field );
	
	?>
	</div>
</fieldset>