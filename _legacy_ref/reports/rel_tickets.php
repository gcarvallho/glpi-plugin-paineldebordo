<?php
include('../../../../inc/includes.php');
Session::checkLoginUser();
header('Location: ../shell.php?page=reports');
exit;
