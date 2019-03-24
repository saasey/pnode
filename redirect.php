<?php
header('Content-Type: text/html'); //application/x-www-form-urlencoded');

//session_start();
$_SESSION = $_POST;
echo json_encode($_POST);
touch("./blank.txt");
if (sizeof($_POST) > 0)
	file_put_contents("./blank.txt", json_encode($_POST));
?>
<a href="http://localhost/digus/purl.php?server=localhost/digus/redirect.php&email=user@mail.com&session=hsgakiiuijt3ghvfjl5l5hd2fi">?asaf</a>