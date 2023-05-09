<?php
 // Получение статуса SMS сообщений
 Include('QTSMS.class.php');

 $sms= new QTSMS('userX.Y','пароль','yourdomain.com');
 // данные о сообщении SMS_ID=6666
 $r_xml1=$sms->status_sms_id(6666);

 // данные о сообщениях отправки SMS_GROUP_ID=110
 $r_xml2=$sms->status_sms_group_id(110);
 // Получить данные сообщений отправленных с 18.12.2007 00:00:00 по 23.12.2007      23:00:00
 $r_xml3=$sms->status_sms_date('18.12.2007 00:00:00','23.12.2007 23:00:00');

 header("Content-Type: text/xml; charset=UTF-8");
 echo $r_xml1; // результат XML
 //echo $r_xml2;
 //echo $r_xml3;

?>
