<?php
include_once "../ugly.php";

$auth = new UglyAuth();

$auth
    ->WithSelfSignup()
    ->WithBasicLogin()
    ->WithTokenlifetimeMinutes(1)
    ->UseDevelopmentStorage(dirname(__FILE__) . '/devusers')
    ->AddRole("admin")
    ->execute();
?>