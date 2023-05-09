<?php
 Include('QTSMS.class.php');
 // Отправка SMS сообщения по списку адресатов
 $sms_text='Привет';

 $target='+79999999991, +79999999992, +7999999999999';
 $sender='vasya';
 $period=600;


 $sms= new QTSMS('userX.Y','пароль','yourdomain.com');

 $result=$sms->post_message($sms_text, $target, $sender, 'x124127456', $period);

 // результат XML
 echo $result;  
 // Отправка SMS по кодовому имени контакт листа
 $sms_text='Привет';

 $phl_codename='druzya';
 $result=$sms->post_message_phl($sms_text, $phl_codename, $sender, 'x124127456', $period);

 header("Content-Type: text/xml; charset=UTF-8");
 // результат XML
 echo $result; 
?>