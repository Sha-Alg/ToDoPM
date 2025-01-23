<?php 
try {

    $conn= new mysqli('todopm.c90aomi2iaab.eu-north-1.rds.amazonaws.com','sharifah','SHailzx&1234','todopmdb')or die("Could not connect to Cloud database".mysqli_error($con));

}catch (Exception $e){
    header('location: 404.html');
}

