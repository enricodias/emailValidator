<?php

include './vendor/autoload.php';
include './src/EmailValidator.php';

$validator = new \enricodias\EmailValidator('test@gmail.com');
var_dump($validator->isValid());