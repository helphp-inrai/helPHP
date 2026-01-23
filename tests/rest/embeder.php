<?php
convert_bin_to_file($_GET['data'],$_GET['filename'],$_GET['mime']);

function convert_bin_to_file($data, $filename, $mime) {
       
    header(urldecode($mime) );
    header(urldecode($filename));
    $fp = fopen('php://output', 'w');

    fwrite($fp, urldecode($data));

    fclose($fp);
}
?>