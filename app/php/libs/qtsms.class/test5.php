<?php
 Include('QTSMS.class.php');
 $sms= new QTSMS('XXXXX','пароль','yourdomain.com');
 
 // получение только новых входящих смс для ящика 134 
 $r_xml=$sms->inbox_sms(1, 134);
 // получение только новых входящих смс 

 // для ящика 134 c 10.01.2010 00:00:00  до 15.01.2010 00:00:00 
 // $r_xml=$sms->inbox_sms(0,134,'10.01.2010 00:00:00','15.01.2010 00:00:00'); 
 
 // результат в XML
 header("Content-Type: text/xml; charset=UTF-8");
 echo $r_xml; // результат XML

?>