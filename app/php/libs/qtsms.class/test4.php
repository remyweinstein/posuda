<?php
 Include('QTSMS.class.php');
 $sms= new QTSMS('XXXXX','пароль','yourdomain.com');

 
 // получение баланса
 $r_xml=$sms->get_balance();
 // результат в XML
 header("Content-Type: text/xml; charset=UTF-8");

 echo $r_xml; // результат XML
?>