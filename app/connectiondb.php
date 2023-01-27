<?php
$servername = "45.144.164.52";
$username = "root";
$password = "Rung_tom003";
$db = "reserve_space";
$port = "13306";
// Create connection
$conn = mysqli_connect($servername, $username, $password,$db,$port);
$connect_status = "";
$connect_message = "";
// Check connection
if (!$conn) {
    $connect_status = "failed";
    $connect_message = "Connection failed: " . mysqli_connect_error();
}
else
{
    $connect_status = "success";
    $connect_message = "Connection success";
}
