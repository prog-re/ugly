<?php
include_once "../ugly.php";

$auth = new UglyAuth();

$auth
    ->WithTokenlifetimeMinutes(1)
    ->UseDevelopmentStorage()
    ->AddRole("admin")
    ->execute();
?>