<?php

$conn = oci_connect(
    "kotiNursery1",
    "oracle",
    "localhost/FREEPDB1"
);

if (!$conn) {
    $e = oci_error();
    die("Connection Failed: " . $e['message']);
}
?>