<?php

if(isset($_POST['payload'])) {
	file_put_contents('notify.json', $_POST['payload']);
	$json = json_decode($_POST['payload']);

	$repository = $json->{'repository'}->{'owner_name'}.'/'.$json->{'repository'}->{'name'};
	$repository_url = $json->{'repository'}->{'url'};
	$branch = $json->{'branch'};
	$branch_url = $repository_url.'/tree/'.$branch;
	$build_number = $json->{'number'};
	$build_url = 'https://travis-ci.org/'.$repository.'/builds/'.$build_number;
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
	echo $html;
	if(sendMail($committer_email, $title, $html)) {
		echo 'Mail sent!';
	} else {
		echo 'Error while sending the mail.';
	}
}

function getTimestamp($date_formatted) {
	$format = 'Y-m-d h: i: s';
	$date_formatted = str_replace(array('T', 'Z'), array(' ', ''), $date_formatted);
	$date = DateTime::createFromFormat($format, $date_formatted);
	if($date === false) {
		exit(print_r(DateTime::getLastErrors()));
	}
	return $date->getTimestamp();
}

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
	$mail->addAddress($recipient);

	$mail->WordWrap = 50;
	$mail->isHTML(true);

	$mail->Subject = $subject;
	$mail->Body = $message;

	return $mail->send();
}

function getSMTPPassword() {
	$password = file_get_contents('/var/www/travis/smtp_info');
	return trim($password);
}

?>