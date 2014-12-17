<?php
/////////////////////////////////////
// Change this email address ////////
$email = "bill@brickswoodfiredpizza.com";
/////////////////////////////////////

if (isset($_POST['name'])) {
	$validation = array();

	if (!isset($_POST['name']) || $_POST['name'] == '') {
		$validation[] = array('message'=>'Please enter Your Name', 'id'=>'email');
	}

	if (!isset($_POST['email']) || $_POST['email'] == '') {
		$validation[] = array('message'=>'Please enter email', 'id'=>'email');
	}

	require_once 'inc/securimage/securimage.php';

	$securimage = new Securimage();

	if ($securimage->check($_POST['captcha']) == false) {
		$validation[] = array('message' => 'Wrong captcha code', 'id' => 'form-captcha');
	}

	$subject = isset($_POST['subject']) ? $_POST['subject'] : 'Restaurant Contact Form Message';
	$name = $_POST['name'];
	$email_contact = $_POST['email'];

	$message = 'Message: '. $_POST['message']. "\r\n";
	$message .= 'E-mail: '. $email_contact. "\r\n".'Name: '. $name;
	$headers = 'From: '. $email_contact. "\r\n" .'Reply-To: '. $email_contact. "\r\n" .'X-Mailer: PHP/' . phpversion();

	header('Content-Type: application/json');

	if (empty($validation)) {
		if (mail($email, $subject, $message, $headers)) {
			echo json_encode(array('success'=>(bool)true, 'message'=>''));
		} else {
			echo json_encode(array('success'=>(bool)false, 'type'=>'system', 'data'=>array('message'=>'Sending error, please try again later')));
		}
	} else {
		echo json_encode(array('success'=>(bool)false, 'type'=>'validation', 'data'=>$validation));
	}

	die();
}