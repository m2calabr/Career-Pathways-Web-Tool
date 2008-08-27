<?php
chdir("..");
include("inc.php");
include("recaptcha-php/recaptchalib.php");


//ModuleInit('help');


if( PostRequest() ) {

	if( IsGuestUser() || !IsLoggedIn() ) {
		$cap = recaptcha_check_answer( $SITE->recaptcha_privatekey(),
										$_SERVER['REMOTE_ADDR'],
										Request('recaptcha_challenge_field'),
										Request('recaptcha_response_field') );
		
		if( !$cap->is_valid ) {
			PrintHeader();
			echo '<p>Sorry, the CAPTCHA was not solved correctly. Go back and try again.</p>';
			PrintFooter();
			die();
		}

		if( Request('message') == '' || Request('email') == '' || Request('name') == '' ) {
			PrintHeader();
			echo '<p>Please go back and fill out all the required fields.</p>';
			PrintFooter();
			die();
		}

	} else {
		if( Request('message') == '' ) {
			PrintHeader();
			echo '<p>Please go back and fill out all the required fields.</p>';
			PrintFooter();
			die();
		}
	}

	$content = array();
	$content['date'] = $DB->SQLDate();
	$content['user_id'] = $_SESSION['user_id'];
	$content['subject'] = $_REQUEST['subject'];

	$content['message'] = '';
	if( Request('name') ) {
		$content['message'] .= 'Name: '.Request('name')."\n";
	}
	if( Request('email') ) {
		$content['message'] .= 'Email: '.Request('email')."\n";
	}
	if( Request('phone') ) {
		$content['message'] .= 'Phone: '.Request('school')."\n";
	}
	if( Request('school') ) {
		$content['message'] .= 'School/Business: '.Request('school')."\n";
	}
	$content['message'] .= "\n".$_REQUEST['message'];
	$DB->Insert('helprequests',$content);

	$email = new SiteEmail('help_request');
	$email->IsHTML(false);
	$email->Assign('EMAIL', (IsLoggedIn()?$_SESSION['email']:Request('email')));
	$email->Assign('SUBJECT', $_REQUEST['subject']);
	$email->Assign('BODY', $content['message']);

	$email->Send();

	header("Location: ".$_SERVER['PHP_SELF'].'?submitted');


} else {

	$message = '';
	$subject = '';
	$page_title = 'Help';
	if( IsGuestUser() ) {
		$helptext = '<p>Thank you for visiting the Career Pathways Web Tool.</p>';
		$helptext .='<p>Please use this form to send us your questions or problems, or write to us at '.EmailEncrypt::EmailLink('help@ctepathways.org').'. We will get back to you within one business day.</p>';
	} else {
		$helptext = '<p>Please use this form to send us your questions or problems with the Web Tool. We will get back to you within one business day.</p>';
	}
	
	switch( Request('template') ) {
	case 'bugreport':

		$page_title = 'Report a Bug';	
		$subject = 'I may have found a bug...';
		$message = '
** This is the URL of the page I was on **
(Copy & paste from the address bar of Internet Explorer or Firefox)
http://oregon.ctepathways.org/....

** This is what I did **




** This is what I expected to happen **




** This is what actually happened **
(Include specific error messages if any)




';
		$helptext = '<p>Following this template when submitting a bug report will greatly facilitate the process of determining what went wrong. However, you are free to type in any format you\'d like.</p>';
		break;

	case 'newfeature':
		$page_title = 'Request a Feature';	
		$subject = 'I would like to see a new feature added';
		$helptext = '<p>Please describe the feature you would like to see the Web Tool offer, and we will get back to you within one business day.</p>';
		break;

	case 'outside':
		$helptext = '<p>Thank you for visiting the Career Pathways Web Tool.</p>';
		$helptext .= '<p>This website is currently only available to Oregon schools and businesses. Please contact us if you would like to use this tool in your school or business outside Oregon. You can contact us using the form below, or by writing to '.EmailEncrypt::EmailLink('help@ctepathways.org').'.</p>';

	}


	PrintHeader();

	if( KeyInRequest('submitted') ) {
		echo '<p>Thank you, your message has been sent to help@ctepathways.org. We will respond to your inquiry within one business day.<br><br>Pathways Web Tool User Support</p>';
	} else {
		?>
		<form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">

		<h1><?= $page_title ?></h1>
		<?= $helptext ?>

		<table>
		<?php if( !IsGuestUser() && IsLoggedIn() ) { ?>
			<tr>
				<th>From</th>
				<td><?= $_SESSION['email'] ?></td>
			</tr>
			<tr>
				<th>To</th>
				<td><?= $DB->GetValue('recipient','email_text','help_request') ?></td>
			</tr>
		<?php } else { ?>
			<tr>
				<th>Your Name*</th>
				<td><input type="textbox" name="name" size="30"></td>
			</tr>
			<tr>
				<th>Your Email*</th>
				<td><input type="textbox" name="email" size="30"></td>
			</tr>
			<tr>
				<th>Phone Number</th>
				<td><input type="textbox" name="phone" size="20"></td>
			</tr>
			<?php 
				if( KeyInRequest('a') ) { ?>
					<tr>
						<th>School or Business Name</th>
						<td><input type="textbox" name="school" size="30"></td>
					</tr>
				<?php }		
			  } ?>
		<tr>
			<th>Subject</th>
			<td><input type="text" name="subject" style="width: 500px" value="<?= $subject ?>"></td>
		</tr>
		<tr>
			<th valign="top">Message*</th>
			<td><textarea style="width: 500px; height: 400px;" name="message"><?= $message ?></textarea></td>
		</tr>
		<?php if( IsGuestUser() || !IsLoggedIn() ) { ?>
		<tr>
			<th>Anti-Spam*</th>
			<td>(not case-sensitive)<br>
				<?= recaptcha_get_html($SITE->recaptcha_publickey(), '', true) ?>
			</td>
		</tr>
		<?php } ?>
		<tr>
			<th>&nbsp;</th>
			<td><input type="submit" class="submit" value="Submit" id="submitButton"></td>
		</tr>
		</table>

		</form>

		<?php
	}
	PrintFooter();
}


?>
