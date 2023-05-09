<?php
// Взаимодействие с Set Retail Centrum
class SRC {
    public static $transactionTypes = [
        "WRITE_OFF",                // Списание
        "CHARGE",                   // Начисление
        "CHARGE_PAYMENT_RETURN",    // Начисление при возврате
        "WRITE_OFF_BY_RETURN",      // Списание при возврате
        "CANCELING"                 // Экспирация
    ];

    public static function getCardsCatalog($cards, $accountData) {
        $result = ["status" => false, "data" => null];

        $URI = "http://".SR_HOST_IP.":".SR_HOST_PORT."/".SR_ERP_WSDL;

        $cardsCatalogXML = '<cards-catalog>';
        foreach ($cards as $key => $card) {
            // $cardsCatalogXML .= '
            // <internal-card-type guid="'.SR_BONUS_CARD_TYPE_ID.'" name="'.SR_BONUS_CARD_TYPE_NAME.'" color="GOLD" work-period-start="" work-period-end="" personalized="true" percentage-discount="0" deleted="false" show-card-from-range-notification="false">
            //     <card-range guid="'.substr($card, 0, 7).'" startField="'.$card.'" countField="1" deleted="false" />
            // </internal-card-type>
            // <internal-card number="'.$card.'" status="ACTIVE" deleted="false" percentage-discount="0" card-type-guid="'.SR_BONUS_CARD_TYPE_ID.'">
            //     <client	guid="'.$accountData["ext_id"].'" last-name="'.$accountData["lastname"].'" first-name="'.$accountData["firstname"].'" middle-name="'.$accountData["middlename"].'" client-type="PRIVATE" birth-date="'.$accountData["birthdate"].'" phone="'.$accountData["phone"].'" mobile-phone="'.$accountData["phone"].'" deleted="false" />
            // </internal-card>';
            $cardsCatalogXML .= '
            <internal-card-type guid="'.SR_BONUS_CARD_TYPE_ID.'" name="'.SR_BONUS_CARD_TYPE_NAME.'" color="GOLD" work-period-start="" work-period-end="" personalized="true" percentage-discount="0" deleted="false" show-card-from-range-notification="false">
                <card-range guid="'.substr($card, 0, 7).'" startField="'.$card.'" countField="1" deleted="false" />
            </internal-card-type>
            <internal-card number="'.$card.'" status="ACTIVE" deleted="false" percentage-discount="0" card-type-guid="'.SR_BONUS_CARD_TYPE_ID.'">
                <client	guid="'.substr($card, 0, 7).'" last-name="'.$accountData["lastname"].'" first-name="'.$accountData["firstname"].'" middle-name="'.$accountData["middlename"].'" client-type="PRIVATE" birth-date="'.$accountData["birthdate"].'" phone="'.$accountData["phone"].'" mobile-phone="'.$accountData["phone"].'" deleted="false" />
            </internal-card>';
        }
        $cardsCatalogXML .= '</cards-catalog>';

        if (NON_WSDL_MODE) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://webservice.importing.plugins.cards.ERPIntegration.crystals.ru/">
                <SOAP-ENV:Body>
                    <ns1:getCardsCatalog>
                        <cardsCatalogXML>' . base64_encode($cardsCatalogXML) . '</cardsCatalogXML>
                    </ns1:getCardsCatalog>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

            $options = array(
                'http' => array(
                    'header'  => "Content-type: text/xml;charset=UTF-8\r\n",
                    'method'  => 'POST',
                    'content' => $body
                )
            );

            $context  = stream_context_create($options);

            try {
                $requestResult = file_get_contents($URI, false, $context);

                $xml = simplexml_load_string($requestResult);
                $xml->registerXPathNamespace("soap", "http://schemas.xmlsoap.org/soap/envelope/");
                $xml->registerXPathNamespace("xmlns", "http://webservice.importing.plugins.cards.ERPIntegration.crystals.ru/");
                $data = $xml->xpath("//soap:Body/xmlns:getCardsCatalogResponse/return");

                $result["status"] = ($data[0]->__toString() === "true");
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
            }
        } else {
            $client = new SoapClient($URI, array('trace' => 1));

            $params = array("cardsCatalogXML" => $cardsCatalogXML);

            try {
                $requestResult = $client->getCardsCatalog($params);

                $result["status"] = $requestResult->return;
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
                // debug($client->__getLastRequest());
            }
        }

        return $result;
    }

    public static function chargeOnBonusAccount($cardNumber, $amount, $expirationDays = null, $shopNum = -1, $cashNum = -1, $shiftNum = -1, $checkNum = -1) {
        $result = ["status" => false, "data" => null];

        if ($expirationDays == null) $expirationDays = SR_EXPIRATION_DAYS;

        $dt = new DateTime();
        $ad = $dt->format('Y-m-d');
        $dt->add(new DateInterval('P'.$expirationDays.'D'));
        $ed = $dt->format('Y-m-d')."T".$dt->format('H:i:s');

        $URI = "http://".SR_HOST_IP.":".SR_HOST_PORT."/".SR_CARDS_WSDL;

        if (NON_WSDL_MODE) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://processing.cards.crystals.ru/">
                <SOAP-ENV:Body>
                    <ns1:chargeOnBonusAccount>
                        <cardNumber>'. $cardNumber .'</cardNumber>
                        <bonusAccountType>'. SR_BONUS_ACCOUNT .'</bonusAccountType>
                        <shopNum>' . $shopNum . '</shopNum>
                        <cashNum>' . $cashNum . '</cashNum>
                        <shiftNum>' . $shiftNum . '</shiftNum>
                        <checkNum>' . $checkNum . '</checkNum>
                        <chargeSum>'. $amount .'</chargeSum>
                        <activatingDate>'. $ad .'</activatingDate>
                        <expirationDate>'. $ed .'</expirationDate>
                    </ns1:chargeOnBonusAccount>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

            $options = array(
                'http' => array(
                    'header'  => "Content-type: text/xml;charset=UTF-8\r\n",
                    'method'  => 'POST',
                    'content' => $body
                )
            );

            $context = stream_context_create($options);

            try {
                $requestResult = file_get_contents($URI, false, $context);

                $xml = simplexml_load_string($requestResult);
                $xml->registerXPathNamespace("soap", "http://schemas.xmlsoap.org/soap/envelope/");
                $xml->registerXPathNamespace("xmlns", "http://processing.cards.crystals.ru/");
                $data = $xml->xpath("//soap:Body/xmlns:chargeOnBonusAccountResponse/return");

                if ($data[0]->errorCode->__toString() == "0") {
                    $result["status"] = true;
                    $result["data"] = ["transactionId" => intval($data[0]->transactionId->__toString())];
                }
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
            }
        } else {
            $client = new SoapClient($URI, array('trace' => 1));

            $params = [
                "cardNumber" => $cardNumber,
                "bonusAccountType" => SR_BONUS_ACCOUNT,
                "shopNum" => $shopNum,
                "cashNum" => $cashNum,
                "shiftNum" => $shiftNum,
                "checkNum" => $checkNum,
                "chargeSum" => $amount,
                "activatingDate" => $ad,
                "expirationDate" => $ed
            ];

            try {
                $requestResult = $client->chargeOnBonusAccount($params);
                if ($requestResult->return->errorCode == 0) {
                    $result["status"] = true;
                    $result["data"] = ["transactionId" => $requestResult->return->transactionId];
                } else {
                    $result["data"] = ["errorText" => $requestResult->return->errorText];
                }
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
                // debug($client->__getLastRequest());
            }
        }

        return $result;
    }

    public static function writeOffFromBonusAccount($cardNumber, $amount) {
        $result = ["status" => false, "data" => null];

        $URI = "http://".SR_HOST_IP.":".SR_HOST_PORT."/".SR_PROCESSING_WSDL;

        if (NON_WSDL_MODE) {
            $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://services.internalcards.cards.crystals.ru/">
                <soapenv:Header/>
                <soapenv:Body>
                <ser:writeOffFromBonusAccount>
                    <accountTypeCode>' . SR_BONUS_ACCOUNT . '</accountTypeCode>
                    <cardNumber>' . $cardNumber . '</cardNumber>
                    <writeOffSum>' . $amount . '</writeOffSum>
                </ser:writeOffFromBonusAccount>
                </soapenv:Body>
            </soapenv:Envelope>';

            $options = array(
                'http' => array(
                    'header'  => "Content-type: text/xml;charset=UTF-8\r\n",
                    'method'  => 'POST',
                    'content' => $body
                )
            );

            $context = stream_context_create($options);

            try {
                $requestResult = file_get_contents($URI, false, $context);

                $xml = simplexml_load_string($requestResult);
                $xml->registerXPathNamespace("soap", "http://schemas.xmlsoap.org/soap/envelope/");
                $xml->registerXPathNamespace("xmlns", "http://services.internalcards.cards.crystals.ru/");
                $data = $xml->xpath("//soap:Body/xmlns:writeOffFromBonusAccountResponse/return");

                $result["status"] = $data[0]->__toString() == "true";
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
            }
        } else {
            $client = new SoapClient($URI, array('trace' => 1));

            $params = [
                "cardNumber" => $cardNumber,
                "accountTypeCode" => SR_BONUS_ACCOUNT,
                "writeOffSum" => $amount
            ];

            try {
                $requestResult = $client->writeOffFromBonusAccount($params);
                $result["status"] = $requestResult->return;
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
                // debug($client->__getLastRequest());
            }
        }

        return $result;
    }

    public static function getBonusAccountBalances($cardNumber) {
        $result = ["status" => false, "data" => null];

        $URI = "http://".SR_HOST_IP.":".SR_HOST_PORT."/".SR_PROCESSING_WSDL;

        if (NON_WSDL_MODE) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://services.internalcards.cards.crystals.ru/">
                <SOAP-ENV:Body>
                    <ns1:getBonusAccountBalances>
                        <cardNumber>'. $cardNumber .'</cardNumber>
                    </ns1:getBonusAccountBalances>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

            $options = array(
                'http' => array(
                    'header'  => "Content-type: text/xml;charset=UTF-8\r\n",
                    'method'  => 'POST',
                    'content' => $body
                )
            );

            $context = stream_context_create($options);

            try {
                $requestResult = file_get_contents($URI, false, $context);

                if ($requestResult) {
                    $xml = simplexml_load_string($requestResult);
                    $xml->registerXPathNamespace("soap", "http://schemas.xmlsoap.org/soap/envelope/");
                    $xml->registerXPathNamespace("xmlns", "http://services.internalcards.cards.crystals.ru/");
                    $data = $xml->xpath("//soap:Body/xmlns:getBonusAccountBalancesResponse/return");

                    if (count($data)) {
                        $result["status"] = true;
                        $result["data"] = [
                            "active" => 0,
                            "blocked" => 0,
                            "writeOff" => 0,
                            "notActive" => 0,
                            "revoked" => 0
                        ];

                        foreach($data as $item) {
                            switch ($item->balanceType->__toString()) {
                                case "ACTIVE": {
                                    $result["data"]["active"] += intval($item->ammount->__toString());
                                    break;
                                }
                                case "BLOCKED": {
                                    $result["data"]["blocked"] += intval($item->ammount->__toString());
                                    break;
                                }
                                case "WRITE_OFF": {
                                    $result["data"]["writeOff"] += intval($item->ammount->__toString());
                                    break;
                                }
                                case "NOT_ACTIVE": {
                                    $result["data"]["notActive"] += intval($item->ammount->__toString());
                                    break;
                                }
                                case "REVOKED": {
                                    $result["data"]["revoked"] += intval($item->ammount->__toString());
                                    break;
                                }
                            }
                        }
                    }    
                } else {
                    $result["data"] = "Can't receive data from SC";
                }
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
            }
        } else {
            $client = new SoapClient($URI, array('trace' => 1));

            $params = [
                "cardNumber" => $cardNumber
            ];

            try {
                $requestResult = $client->getBonusAccountBalances($params);
                if (count($requestResult->return)) {
                    $result["status"] = true;
                    $result["data"] = [
                        "active" => 0,
                        "blocked" => 0,
                        "writeOff" => 0,
                        "notActive" => 0,
                        "revoked" => 0
                    ];

                    foreach ($requestResult->return as $key => $value) {
                        switch ($value->balanceType) {
                            case "ACTIVE": {
                                $result["data"]["active"] += $value->ammount;
                                break;
                            }
                            case "BLOCKED": {
                                $result["data"]["blocked"] += $value->ammount;
                                break;
                            }
                            case "WRITE_OFF": {
                                $result["data"]["writeOff"] += $value->ammount;
                                break;
                            }
                            case "NOT_ACTIVE": {
                                $result["data"]["notActive"] += $value->ammount;
                                break;
                            }
                            case "REVOKED": {
                                $result["data"]["revoked"] += $value->ammount;
                                break;
                            }
                        }
                    }
                } else {
                    $result["data"] = ["errorText" => $requestResult->return->errorText];
                }
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
                // debug($client->__getLastRequest());
            }
        }

        return $result;
    }

    public static function getCardInformationByNumber($cardNumber) {
        $result = ["status" => false, "data" => null];

        $URI = "http://".SR_HOST_IP.":".SR_HOST_PORT."/".SR_PROCESSING_WSDL;

        if (NON_WSDL_MODE) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://services.internalcards.cards.crystals.ru/">
                <SOAP-ENV:Body>
                    <ns1:getCardInformationByNumber>
                        <cardNumber>'. $cardNumber .'</cardNumber>
                    </ns1:getCardInformationByNumber>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

            $options = array(
                'http' => array(
                    'header'  => "Content-type: text/xml;charset=UTF-8\r\n",
                    'method'  => 'POST',
                    'content' => $body
                )
            );

            $context = stream_context_create($options);

            try {
                $requestResult = file_get_contents($URI, false, $context);

                $xml = simplexml_load_string($requestResult);
                $xml->registerXPathNamespace("soap", "http://schemas.xmlsoap.org/soap/envelope/");
                $xml->registerXPathNamespace("xmlns", "http://services.internalcards.cards.crystals.ru/");
                $data = $xml->xpath("//soap:Body/xmlns:getCardInformationByNumberResponse/return/cardVO");

                $result["status"] = true;
                $result["data"] = [
                    "id" => intval($data[0]->id->__toString()),
                    "firstname" => $data[0]->clientVO->firstName->__toString(),
                    "secondname" => $data[0]->clientVO->lastName->__toString(),
                    "sex" => 0,
                    // "email" => $data[0]->firstName,
                    "birthdate" => $data[0]->clientVO->birthDate->__toString(),
                    "phone" => $data[0]->clientVO->phone->__toString()
                ];
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
            }
        } else {
            $client = new SoapClient($URI, array('trace' => 1));

            $params = [
                "cardNumber" => $cardNumber
            ];

            try {
                $requestResult = $client->getCardInformationByNumber($params);
                if (count($requestResult->return)) {
                    $result["status"] = true;
                    $result["data"] = [
                        "id" => $requestResult->return->cardVO->id,
                        "firstname" => $requestResult->return->cardVO->clientVO->firstName,
                        "secondname" => $requestResult->return->cardVO->clientVO->lastName,
                        "sex" => 0,
                        // "email" => $requestResult->return->cardVO->firstName,
                        "birthdate" => $requestResult->return->cardVO->clientVO->birthDate,
                        "phone" => $requestResult->return->cardVO->clientVO->phone
                    ];
                } else {
                    $result["description"] = "EMPTY";
                }
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
                // debug($client->__getLastRequest());
            }

            // debug($client->__getLastRequest());
            // debug($client-> __getLastResponse());
        }

        return $result;
    }

    public static function getBonusAccountHistory($cardNumber, $startPeriod, $finishPeriod) {
        $result = ["status" => false, "data" => null];

        $URI = "http://".SR_HOST_IP.":".SR_HOST_PORT."/".SR_PROCESSING_WSDL;

        if (NON_WSDL_MODE) {
            $body = '<?xml version="1.0" encoding="UTF-8"?>
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://services.internalcards.cards.crystals.ru/">
                <SOAP-ENV:Body>
                    <ns1:getBonusAccountHistory>
                        <cardNumber>'. $cardNumber .'</cardNumber>
                        <startPeriod>'. $startPeriod .'</startPeriod>
                        <finishPeriod>'. $finishPeriod .'</finishPeriod>
                    </ns1:getBonusAccountHistory>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>';

            $options = array(
                'http' => array(
                    'header'  => "Content-type: text/xml;charset=UTF-8\r\n",
                    'method'  => 'POST',
                    'content' => $body
                )
            );

            $context = stream_context_create($options);

            try {
                $requestResult = file_get_contents($URI, false, $context);

                $transactions = [];

                $xml = simplexml_load_string($requestResult);
                $xml->registerXPathNamespace("soap", "http://schemas.xmlsoap.org/soap/envelope/");
                $xml->registerXPathNamespace("xmlns", "http://services.internalcards.cards.crystals.ru/");
                $data = $xml->xpath("//soap:Body/xmlns:getBonusAccountHistoryResponse/return");
                if (count($data)) {
                    foreach ($data as $key => $value) {
                        if ($value->bonusAccount->bonusAccountsTypeVO->bonusAccountTypeId->__toString() == SR_BONUS_ACCOUNT && in_array($value->transactionType->__toString(), self::$transactionTypes)) {
                            $operationDate = new DateTime($value->operationDate->__toString());
                            $startDate = new DateTime($value->dateStartAction->__toString());
                            $finishDate = new DateTime($value->dateFinishAction->__toString());
                            array_push($transactions, [
                                "ext_id" => intval($value->bonusAccountTransId->__toString()),
                                "amount" => intval($value->bonusSum->__toString()) * 100,
                                "type" => $value->transactionType->__toString(),
                                "operation_date" => $operationDate->format("Y-m-d H:i:s"),
                                "start_date" => $startDate->format("Y-m-d H:i:s"),
                                "finish_date" => $finishDate->format("Y-m-d H:i:s"),
                                "rsa_id" => $value->shop->__toString(),
                                "cash" => $value->cashNumber->__toString(),
                                "shift" => $value->shift->__toString(),
                                "number" => $value->check->__toString()
                            ]);
                        }
                    }
                }

                $result["status"] = true;
                $result["data"] = $transactions;
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();
            }
        } else {
            $client = new SoapClient($URI, array('trace' => 1));

            $params = [
                "cardNumber" => $cardNumber,
                "startPeriod" => $startPeriod,
                "finishPeriod" => $finishPeriod
            ];

            try {
                $requestResult = $client->getBonusAccountHistory($params);

                $transactions = [];

                if (count($requestResult->return)) {
                    foreach ($requestResult->return as $key => $value) {
                        if ($value->bonusAccount->bonusAccountsTypeVO->bonusAccountTypeId == SR_BONUS_ACCOUNT && in_array($value->transactionType, self::$transactionTypes)) {
                            $operationDate = new DateTime($value->operationDate);
                            $startDate = new DateTime($value->dateStartAction);
                            $finishDate = new DateTime($value->dateFinishAction);
                            array_push($transactions, [
                                "ext_id" => $value->bonusAccountTransId,
                                "amount" => $value->bonusSum * 100,
                                "type" => $value->transactionType,
                                "operation_date" => $operationDate->format("Y-m-d H:i:s"),
                                "start_date" => $startDate->format("Y-m-d H:i:s"),
                                "finish_date" => $finishDate->format("Y-m-d H:i:s"),
                                "rsa_id" => $value->shop,
                                "cash" => $value->cashNumber,
                                "shift" => $value->shift,
                                "number" => $value->check
                            ]);
                        }
                    }
                }

                $result["status"] = true;
                $result["data"] = $transactions;
            } catch (Throwable $th) {
                $result["data"] = $th->getMessage();

                // debug($client->__getLastRequest());
                // debug($client-> __getLastResponse());
            }
        }

        return $result;
    }
}
?>