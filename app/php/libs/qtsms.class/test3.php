<?php
 // Единый запрос 
 Include('QTSMS.class.php');
 $sms= new QTSMS('userX.Y','пароль','yourdomain.com');

 // !!! Команда на кеширование запросов
 $sms->start_multipost();    
 // Отправка смс 
 $sms->post_message('Привет', '+79999999991,+79999999992', 'Vasya');    
 // Отправка смс 

 $sms->post_message_phl('С днём рождения!', 'druzya', 'Petya', 'x425256');                           
 // данные о сообщении SMS_ID=6666

 $sms->status_sms_id(6666);
 // данные о сообщениях с SMS_GROUP_ID=110
 $sms->status_sms_group_id(110) ; 
 // !!! отправить всё одним запросом и получить результат в XML

 $r_xml=$sms->process();   
 header("Content-Type: text/xml; charset=UTF-8");
 echo $r_xml; // результат XML

?>