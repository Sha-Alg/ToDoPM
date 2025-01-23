<?php
try {
    // Create a new MySQL connection
    $conn = new mysqli(
        'todopm.c90aomi2iaab.eu-north-1.rds.amazonaws.com', // Hostname (AWS RDS endpoint)
        'sharifah',                                        // Username
        'SHailzx&1234',                                    // Password
        'todopmdb'                                         // Database name
    ) or die("Could not connect to Cloud database" . mysqli_error($con));
} catch (Exception $e) {
    // Redirect to an error page if the connection fails
    header('location: 404.html');
}


