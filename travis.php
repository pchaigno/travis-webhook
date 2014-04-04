<?php

define('TRAVIS_TOKEN', getTravisToken());
define('OWNER_EMAIL', 'paul.chaignon@gmail.com');

// Exists if the sender is not Travis:
if(!checkTravisAuthorization(&$repository)) {
	notifyAdmin();
	header('401 Not Authorized');
	exit('You\'re not Travis!');
}

if(isset($_POST['payload'])) {
	// Logs the JSON document.
	file_put_contents('notify.json', $_POST['payload']);
	
	/**
	 * Reads all information from the JSON document.
	 */
	$json = json_decode($_POST['payload']);
	$repository_url = $json->{'repository'}->{'url'};
	$branch = $json->{'branch'};
	$branch_url = $repository_url.'/tree/'.$branch;
	$build_id = $json->{'id'};
	$build_number = $json->{'number'};
	$build_url = 'https://travis-ci.org/'.$repository.'/builds/'.$build_id;
	$committer_name = $json->{'matrix'}[0]->{'committer_name'};
	$committer_email = $json->{'matrix'}[0]->{'committer_email'};
	$commit = substr($json->{'matrix'}[0]->{'commit'}, 0, 7);
	$commit_url = 'https://github.com/'.$repository.'/commit/'.$json->{'matrix'}[0]->{'commit'};
	$commit_message = $json->{'matrix'}[0]->{'message'};
	$compare_url = $json->{'compare_url'};
	$started_date = getTimestamp($json->{'started_at'});
	$finished_date = getTimestamp($json->{'finished_at'});
	$duration = $finished_date - $started_date;
	$duration_text = date('i', $duration).' minutes and '.date('s', $duration).' seconds';
	$title = '[Broken] '.$repository.'#'.$build_number.' ('.$branch.' - '.$commit.')';

	/**
	 * Reads the HTML text and writes all the variables.
	 */
	$html = file_get_contents('notify.html');
	$html = str_replace('##repository##', str_replace('/', ' / ', $repository), $html);
	$html = str_replace('##repository_url##', $repository_url, $html);
	$html = str_replace('##branch##', $branch, $html);
	$html = str_replace('##branch_url##', $branch_url, $html);
	$html = str_replace('##build_number##', $build_number, $html);
	$html = str_replace('##build_url##', $build_url, $html);
	$html = str_replace('##committer##', $committer_name, $html);
	$html = str_replace('##commit##', $commit, $html);
	$html = str_replace('##commit_message##', $commit_message, $html);
	$html = str_replace('##duration##', $duration_text, $html);
	$html = str_replace('##compare_url##', $compare_url, $html);
	$html = str_replace('##commit_url##', $commit_url, $html);

	// Sends the mail:
	if(sendMail($committer_email, $title, $html)) {
		echo 'Mail sent!';
	} else {
		echo 'Error while sending the mail.';
	}
}

/**
 * Convert a Travis date into a timestamp.
 * Exists if the date is incorrect.
 * @param date_formatted The date in the Travis format.
 * @return The date as a timestamp.
 */
function getTimestamp($date_formatted) {
	$format = 'Y-m-d h: i: s';
	$date_formatted = str_replace(array('T', 'Z'), array(' ', ''), $date_formatted);
	$date = DateTime::createFromFormat($format, $date_formatted);
	if($date === false) {
		exit(print_r(DateTime::getLastErrors()));
	}
	return $date->getTimestamp();
}

/**
 * Checks that the sender of the request is Travis.
 * If he is he should have sent a hash (with the Travis token) in the headers.
 * @param repository The repository will be written in this variable.
 * @return True if the sender is Travis.
 */
function checkTravisAuthorization($repository) {
	$repository = getHeader('Travis-Repo-Slug');
	$hashSubmitted = getHeader('Authorization');
	if($hashSubmitted!=null && $repository!=null) {
		$hash = hash('sha256', $repository.TRAVIS_TOKEN);
		if($hash == $hashSubmitted) {
			return true;
		}
	}
	return false;
}

/**
 * Notifies the owner/admin.
 * It sends the IP address of the "attacker".
 * If a payload was sent as POST content, it is also communicated.
 */
function notifyAdmin() {
	$message = 'Hi master,<br/><br/>';
	$message .= 'Someone tried to impersonate Travis.<br/>';
	$message .= 'His IP address is: '.$_SERVER['REMOTE_ADDR'].'.<br/>';
	if(isset($_POST['payload'])) {
		$message .= 'He used the following HTTP POST content:<br/>';
		$message .= '<code>';
		$message .= $_POST['payload'];
		$message .= '</code>';
	}
	$message .= '<br/><br/>Travis';
	sendMail(OWNER_EMAIL, 'Unauthorized request on WebHook', $message);
}

/**
 * Sends an email using PHPMailer.
 * Note: The owner is always in the recipients.
 * @param recipient The recipient for the email.
 * @param subject The subject of the email.
 * @param message An HTML text to be displayed as the message.
 * @return True if the email was successfully sent.
 */
function sendMail($recipient, $subject, $message) {
	require_once('PHPMailer/class.phpmailer.php');

	$mail = new PHPMailer();
	$mail->CharSet = 'UTF-8';
	$mail->IsSMTP();
	$mail->Host = 'mailhost.insa-rennes.fr';
	$mail->Port = 587;
	$mail->SMTPAuth = true;
	$mail->Username = 'pchaigno';
	$mail->Password = getSMTPPassword();
	$mail->SMTPSecure = 'tls';

	$mail->SetFrom('pchaigno@insa-rennes.fr', 'Travis CI');
	if($recipient != OWNER_EMAIL) {
		$mail->addAddress($recipient);
	}
	$mail->addAddress(OWNER_EMAIL);

	$mail->WordWrap = 50;
	$mail->isHTML(true);

	$mail->Subject = $subject;
	$mail->Body = $message;

	return $mail->send();
}

/**
 * Reads a header value from the HTTP header.
 * Exists if the header can't be found.
 * @param header The header to read.
 * @return The header value.
 */
function getHeader($header) {
	$headers = apache_request_headers();
	if(isset($headers[$header])) {
		return null;
	}
	return $headers[$header];
}

/**
 * Retrieves the token for Travis.
 * @return The Travis token.
 */
function getTravisToken() {
	$token = file_get_contents('/var/www/travis/travis_info');
	return trim($token);
}

/**
 * Retrieves the password for SMTP.
 * @return The SMTP password.
 */
function getSMTPPassword() {
	$password = file_get_contents('/var/www/travis/smtp_info');
	return trim($password);
}

?>