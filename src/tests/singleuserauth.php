<?php
include_once "../ugly.php";

$auth = new UglyAuth();

$auth
    ->SingleUser('SingleUser','Hunter10')
    ->WithBasicLogin()
    ->WithTokenlifetimeMinutes(1)
    ->execute();
?>