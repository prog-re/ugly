<?php
include_once "../ugly.php";
$auth = new UglyAuth();

$auth
    ->WithSelfSignup()
    ->WithBasicLogin()
    ->WithTokenlifetimeMinutes(0.01)
    ->UseDevelopmentStorage(dirname(__FILE__) . '/devusers')
    ->execute();
?>