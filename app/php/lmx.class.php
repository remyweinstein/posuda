<?php
class LMX {
    private $SAPI_accessToken = null;
    private $PAPI_accessToken = null;

    function __construct($SAPI_accessToken = "", $PAPI_accessToken = "") {
        if (!empty($SAPI_accessToken)) $this->SAPI_accessToken = $SAPI_accessToken;
        if (!empty($PAPI_accessToken)) $this->PAPI_accessToken = $PAPI_accessToken;
    }

    private function doRequest($url, $opts, $returnHeaders = false) {
        $result = ["status" => false, "data" => null];
        $optSsl = array(
                        "ssl" => array(
                            "verify_peer"      => false,
                            "verify_peer_name" => false,
                        ),
                    );  
        $options = array_merge($optSsl, $opts);
        $context = stream_context_create($options);
        try {
            $requestResult = file_get_contents($url, false, $context);

            if ($requestResult) {
                $result["status"] = true;
                $result["data"] = json_decode($requestResult);
            } else {
                $result["description"] = "ERROR_DESCRIPTION";
            }

            if ($returnHeaders) $result["headers"] = $http_response_header;
        } catch (\Throwable $th) {
            $result["description"] = $th->getMessage();
        }

        return $result;
    }
    
    // Common methods

    public function setPhone($phone, $personId) {
        $this->initSAPIToken();
        $setPhoneResult = $this->SAPI_SetPhone($personId, $phone);
    }

    public function registerConsumer($phone, $profile, $debug = false) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];
            $personId = null;

            // Запрашиваем текущий статус клиента по номеру телефона
            $usersResult = $this->SAPI_Users($phone);
            if ($usersResult["status"] && $usersResult["data"]->result->state == "Success") {
                if (empty($usersResult["data"]->data)) {
                    // Клиент отсутствует в базе
                    $beginRegistrationResult = $this->SAPI_BeginRegistration(["login" => $phone, "password" => "", "withoutCall" => true]);
                    if ($beginRegistrationResult["status"] && $beginRegistrationResult["data"]->result->state == "Success" && !empty($beginRegistrationResult["data"])) {
                        $personId = $beginRegistrationResult["data"]->data->personId;
                    } else {
                        $result["description"] = $beginRegistrationResult["data"]->result->message;  
                    } 
                } else {
                    if ($usersResult["data"]->data[0]->state == "Registered") {
                        // Клиент уже зарегистрирован
                        return ["status" => true, "description" => "Клиент уже зарегистрирован", "data" => ["personId" => $usersResult["data"]->data[0]->id]];
                    } else {
                        // Клиент существует, регистрация уже начата
                        if ($usersResult["data"]->data[0]->id) $personId = $usersResult["data"]->data[0]->id;
                    }
                }

                // Получен идентификатор клиента
                if ($personId) {
                    // Запрашиваем набор действий для завершения регистрации
                    $registartionActionsResult = $this->SAPI_RegistrationActions($personId);
                    if ($registartionActionsResult["status"] && $registartionActionsResult["data"]->result->state == "Success" && !empty($registartionActionsResult["data"]->data->actions)) {
                        // Обрабатываем каждое из действий
                        foreach ($registartionActionsResult["data"]->data->actions as $key => $action) {
                            if ($action->isDone) continue;

                            switch ($action->userActionType) {
                                case "AcceptTenderOffer": {
                                    $acceptTenderOfferResult = $this->SAPI_AcceptTenderOffer($personId);
                                    break;
                                }
                                case "ChangePhone": {
                                    $setPhoneResult = $this->SAPI_SetPhone($personId, $phone);
                                    break;
                                }
                                case "PasswordRequired": {
                                    $setRandomPasswordResult = $this->SAPI_SetRandomPassword($personId);
                                    break;
                                }
                                case "Questions": {
                                    $answers = [
                                        [
                                            "questionId" => 4,
                                            "fixedAnswerIds" => null,
                                            "value" => $profile["firstname"],
                                            "tag" => "0",
                                            "questionGroupId" => 1,
                                            "fixedAnswers" => []
                                        ],[
                                            "questionId" => 5,
                                            "fixedAnswerIds" => null,
                                            "value" => $profile["lastname"],
                                            "tag" => "0",
                                            "questionGroupId" => 1,
                                            "fixedAnswers" => []
                                        ],[
                                            "questionId" => 6,
                                            "fixedAnswerIds" => null,
                                            "value" => $profile["middlename"],
                                            "tag" => "0",
                                            "questionGroupId" => 1,
                                            "fixedAnswers" => []
                                        ],[
                                            "questionId" => 35,
                                            "fixedAnswerIds" => null,
                                            "value" => $profile["birthdate"],
                                            "tag" => "0",
                                            "questionGroupId" => 1,
                                            "fixedAnswers" => []
                                        ],[
                                            "questionId" => 38,
                                            "fixedAnswerIds" => null,
                                            "value" => $profile["email"],
                                            "tag" => "0",
                                            "questionGroupId" => 1,
                                            "fixedAnswers" => []
                                        ],[
                                            "questionId" => 37,
                                            "fixedAnswerIds" => null,
                                            "value" => $profile["city"],
                                            "tag" => "0",
                                            "questionGroupId" => 1,
                                            "fixedAnswers" => []
                                        ]
                                    ];
                                    $updateAnswersResult = $this->SAPI_UpdateAnswers($personId, $answers);
                                    break;
                                }
                            }
                        }

                        // Повторно запрашиваем статус регистрации
                        $registartionActionsResult = $this->SAPI_RegistrationActions($personId);
                        if ($registartionActionsResult["status"] && $registartionActionsResult["data"]->result->state == "Success" && !empty($registartionActionsResult["data"]->data->actions)) {
                            $isDone = true;

                            foreach ($registartionActionsResult["data"]->data->actions as $key => $action) if (!$action->isDone) {
                                $isDone = false;
                                if ($debug) $result["debug"] = $action;
                                break;
                            }

                            if ($isDone) {
                                $tryToFinishRegistrationResult = $this->SAPI_TryFinishRegistration($personId);
                                if ($tryToFinishRegistrationResult["status"] && $tryToFinishRegistrationResult["data"]->result->state == "Success" && $tryToFinishRegistrationResult["data"]->data->registrationCompleted) {
                                    $result = ["status" => true, "data" => ["personId" => $personId]];
                                } else {
                                    $result["description"] = "Не удалось завершить регистрацию.";
                                    if ($debug) $result["debug"] = $tryToFinishRegistrationResult;
                                }
                            } else {
                                $result["description"] = "Остаются незавершенные этапы регистрации.";
                            }
                        } else {
                            $result["description"] = "Не удалось запросить контрольный список действий для завершения регистрации.";
                        }
                    } else {
                        $result["description"] = "Не удалось получить список действий для завершения регистрации.";
                    }
                } else {
                    $result["description"] = ($result["description"] ? $result["description"] : "Не удалось получить идентификатор клиента.");
                }
            } else {
                $result["description"] = "Не удалось запросить информацию о клиенте.";   
            }
        } else {
            $result["description"] = "Не удалось получить токен.";
        }

        return $result;
    }

    public function getConsumerData($phone) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $usersResult = $this->SAPI_Users($phone);
            if ($usersResult["status"] && $usersResult["data"]->result->state == "Success") {
                if (!empty($usersResult["data"]->data)) {
                    $result["status"] = true;
                    $result["data"] = $usersResult["data"]->data[0];
                } else {
                    $result["description"] = "Клиент отсутствует в базе.";
                }
            } else {
                $result["description"] = "Не удалось запросить информацию о клиенте.";
            }
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function emitVirtual($personId, $debug = false) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $emitVirtualResult = $this->SAPI_EmitVirtual($personId);
            if ($debug) $result["debug"] = $emitVirtualResult["data"];
            if ($emitVirtualResult["status"]) {
                if ($emitVirtualResult["data"]->result->state == "Success") {
                    $result["status"] = true;
                } else {
                    $result["status"] = true;
                    $result["description"] = $emitVirtualResult["data"]->result->message; 
                }
            } else {
                $result["description"] = "Не удалось выпустить виртуальную карту.";   
            }
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;  
    }

    private function getCurrencyDetailedBalance($items)
    {
        $currencyItem = new stdClass;
        $amount = 0;
        $notActivatedAmount = 0;
        $lifeTimesByTime = [];
        $currency = new stdClass;
        $currency->name = "Бонусы";

        for($i=0; $i < count($items); $i++) {
            if ($items[$i]->currency->id == 4) {
                $amount = $items[$i]->amount;
                $notActivatedAmount = $items[$i]->notActivatedAmount;
                $lifeTimesByTime = $items[$i]->lifeTimesByTime;
                $currency->name = $items[$i]->currrency->name;
            }
        }

        $currencyItem->amount = $amount;
        $currencyItem->notActivatedAmount = $notActivatedAmount;
        $currencyItem->lifeTimesByTime = $lifeTimesByTime;
        $currencyItem->currency = $currency;

        return $currencyItem;
    }

    private function getCurrencyBalance($items)
    {
        $currencyItem = new stdClass;
        $balance = 0;
        $notActivated = 0;
        $currency = new stdClass;
        $currency->name = "Бонусы";

        for($i=0; $i < count($items); $i++) {
            if ($items[$i]->currency->id == 4) {
                $balance = $items[$i]->balance;
                $notActivated = $items[$i]->notActivated;
                $currency->name = $items[$i]->currrency->name;
            }
        }

        $currencyItem->balance = $balance;
        $currencyItem->notActivated = $notActivated;
        $currencyItem->currency = $currency;

        return $currencyItem;
    }

    public function getBalancePron($personId, $debug = false) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_DetailedBalance($personId);
            if ($debug) $result["debug"] = $methodResult;
            if ($methodResult["status"] && $methodResult["data"]->result->state == "Success") {

                //if (!empty($methodResult["data"]->data->items)) {
                    $itemCurr = $this->getCurrencyDetailedBalance($methodResult["data"]->data->items);
                    $lifeTimes = [];

                    if (!empty($itemCurr->lifeTimesByTime))
                        foreach($itemCurr->lifeTimesByTime as $value) {
                            array_push($lifeTimes, [
                                "amount" => $value->amount * 100,
                                "date" => $value->date
                            ]);
                        }

                    $result["status"] = true;
                    $result["data"] = [
                        "balance"       => $itemCurr->amount,
                        "activation"    => $itemCurr->notActivatedAmount,
                        "lifeTimes"     => $lifeTimes
                    ];
                //} else {
                //    $result["description"] = "Бонусные счета отсутствуют.";
                //}
            } else {
                $result["description"] = "Не удалось запросить информацию о балансе.";
            }
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function getBalance($personId, $debug = false) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_DetailedBalance($personId);
            if ($debug) $result["debug"] = $methodResult;
            if ($methodResult["status"] && $methodResult["data"]->result->state == "Success") {
                //if (!empty($methodResult["data"]->data->items)) {
                    $itemCurr = $this->getCurrencyDetailedBalance($methodResult["data"]->data->items);
                    $lifeTimes = [];

                    if (!empty($itemCurr->lifeTimesByTime))
                        foreach($itemCurr as $value) {
                                array_push($lifeTimes, [
									"amount" => round(($value->amount * 100) + gmp_sign(intval($value->amount)) * 0.5),
                                    "date" => $value->date
                                ]);
                        }

                    $result["status"] = true;
                    $result["data"] = [
                        "balance"       => $itemCurr->amount,
                        "activation"    => $itemCurr->notActivatedAmount,
                        "lifeTimes"     => $lifeTimes
                    ];
                //} else {
                //    $result["description"] = "Бонусные счета отсутствуют.";
                //}
            } else {
                $result["description"] = "Не удалось запросить информацию о балансе.";
            }
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function getBalanceNew($personId, $debug = false) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_Balance($personId);
            if ($debug) $result["debug"] = $methodResult;
            if ($methodResult["status"] && $methodResult["data"]->result->state == "Success") {
                //if (!empty($methodResult["data"]->data)) {
                    $itemCurr = $this->getCurrencyBalance($methodResult["data"]->data);
                    $result["status"] = true;
                    $result["data"] = [
                        "name" => $itemCurr->currency->name,
                        "amount" => $itemCurr->balance + $itemCurr->notActivated
                    ];
                //} else {
                //    $result["description"] = "Бонусные счета отсутствуют.";
                //}
            } else {
                $result["description"] = "Не удалось запросить информацию о балансе.";  
            }
        }

        return $result;
    }

    public function getConsumerCards($personId, $debug = false) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_UsersCards($personId);
            if ($debug) $result["debug"] = $methodResult["data"];
            if ($methodResult["status"] && array_key_exists("data", $methodResult) && is_object($methodResult["data"]) && $methodResult["data"]->result->state == "Success") {
                if (!empty($methodResult["data"]->data)) {
                    $result["status"] = true;
                    $result["data"] = $methodResult["data"]->data;
                } else {
                    $result["description"] = "Карты отсутствуют.";
                }
            } else {
                $result["description"] = "Не удалось запросить информацию о картах.";
            }
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function getPurchases($filters) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_Purchases($filters);
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function getPurchase($purchaseId) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_Purchase($purchaseId);
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function getPurchaseOperations($purchaseId) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_PurchaseOperations($purchaseId);
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function getChequePositions($purchaseId) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_ChequePositions($purchaseId);
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function deregisterConsumer($personId) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_Deregister($personId);
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    private function getHistoryForPartner($rows) {
        $items = [];

        for ($i = 0; $i < count($rows); $i++) {
            if ($rows[$i]->partnerId == "3fba867b-1681-3ade-c5fa-efe294c5b48d") {
                $row = new stdClass;
                $row = $rows[$i];
                $items[] = $row;
            }
        }

        return $items;
    } 

    public function getHistory($personId, $filters = null, $debug = false) {
        // Пример:
        // $filters = [
        //     "fromDate" => "2021-01-01",
        //     "count" => 999
        // ];

        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_History($personId, $filters, $debug);
            if ($debug) $result["debug"] = $methodResult["data"];
            if ($methodResult["status"] && $methodResult["data"]->result->state == "Success") {
                $rows = $this->getHistoryForPartner($methodResult["data"]->data->rows);
                //if (!empty($methodResult["data"]->data->rows)) {
                    $acceptedTypes = [
                        "RewardData",
                        "WithdrawData"
                    ];

                    $acceptedWithdrawTypes = [
                        "Expiration",
                        "Bonus"
                    ];

                    $acceptedRewardTypes = [
                        "Bonus"
                    ];

                    $result["data"] = [];

                    foreach ($rows as $key => $row) {
                        if (in_array($row->type, $acceptedTypes)) {
                            $transaction = [
                                "extId" => $row->id,
                                "date" => (new DateTime($row->dateTime))->format("Y-m-d H:i:s"),
                                "description" => $row->description
                            ];
    
                            switch ($row->type) {
                                case "RewardData": {
                                    if (in_array($row->data->rewardType, $acceptedRewardTypes)) $transaction["type"] = $row->data->rewardType;

                                    break;
                                }

                                case "WithdrawData": {
                                    if (in_array($row->data->withdrawType, $acceptedWithdrawTypes)) $transaction["type"] = $row->data->withdrawType;

                                    break;
                                }
                            }

                            if (isset($transaction["type"])) {
                                $transaction["amount"] = $row->data->amount->amount * 100;
                                array_push($result["data"], $transaction);
                            }
                        }
                    }

                    $result["status"] = true;
                //} else {
                //    $result["description"] = "История покупок за выбранный период пуста.";    
                //}
            } else {
                $result["description"] = "Не удалось получить историю покупок.";    
            }
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;   
    }

    public function getPurchasesFullData($filters, $debug = false) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->SAPI_Purchases($filters);
            if ($methodResult["status"] && $methodResult["data"]->result->state == "Success") {
                if (!empty($methodResult["data"]->data)) {
                    if ($debug) debug($methodResult["data"]);
                    
                    $purchases = [];

                    foreach ($methodResult["data"]->data as $key => $purchase) {
                        $chequePositionsResult = $this->SAPI_ChequePositions($purchase->purchaseId);
                        if ($chequePositionsResult["status"] && $chequePositionsResult["data"]->result->state == "Success") {
                            if ($debug) debug($chequePositionsResult["data"]);
                            
                            $oper_day = new DateTime($purchase->purchaseTime);
                            $sale_time = new DateTime($purchase->completeTime);
                            $purchase = [
                                "rsa_id" => $purchase->merchant->order,
                                "operation_type" => 1,
                                "oper_day" => $oper_day->format('Y-m-d'),
                                "sale_time" => $sale_time->format('Y-m-d H:i:s'),
                                "cash" => 0,
                                "shift" => 0,
                                "number" => $purchase->chequeNumber,
                                "amount" => 0,
                                "cashback_amount" => 0,
                                "discount_amount" => 0,
                                "payment_amount" => 0,
                                "discount_card" => $purchase->personIdentifier,
                                "positions" => []
                            ];

                            if (!empty($chequePositionsResult["data"]->data)) foreach ($chequePositionsResult["data"]->data as $key => $chequePosition) {
                                $position = [
                                    "product_id" => null,
                                    "title" => $chequePosition->gooodName,
                                    "count" => $chequePosition->quantity * 1000,
                                    "cost" => $chequePosition->amount->amount * 100,
                                    "cashback_amount" => 0,
                                    "discount_amount" => 0,
                                    "payment_amount" => 0,
                                    "amount" => $chequePosition->amount->amount * 100
                                ];

                                if (!empty($chequePosition->discounts)) {
                                    foreach ($chequePosition->discounts as $key => $discount) {
                                        if (!$discount->amount->amount) continue;
    
                                        switch ($discount->type) {
                                            case "CalculatedDiscount": {
                                                $position["discount_amount"] += $discount->amount->amount * 100;
                                                $position["amount"] += $discount->amount->amount * 100;
                                                break;
                                            }
    
                                            case "CalculatedCashback":{
                                                $position["cashback_amount"] += $discount->amount->amount * 100;
                                                break;
                                            }
    
                                            case "CalculatedPayment":{
                                                $position["payment_amount"] += $discount->amount->amount * 100;
                                                $position["amount"] += $discount->amount->amount * 100;
                                                break;
                                            }
                                        }
                                    }
                                }

                                if (!empty($chequePosition->refunds)) {
                                    $purchase["operation_type"] = 0;

                                    foreach ($chequePosition->refunds as $key => $refund) {
                                        if (!$refund->amount->amount) continue;
    
                                        $position["cashback_amount"] += $refund->amount->amount * 100;
                                    }
                                }

                                $purchase["amount"]             += $position["amount"];
                                $purchase["cashback_amount"]    += $position["cashback_amount"];
                                $purchase["discount_amount"]    += $position["discount_amount"];
                                $purchase["payment_amount"]     += $position["payment_amount"];

                                array_push($purchase["positions"], $position);
                            }
                            array_push($purchases, $purchase);
                        }
                    }

                    $result["status"] = true;
                    $result["data"] = ["purchases" => $purchases];
                } else {
                    $result["description"] = "Список чеков пуст."; 
                }
            } else {
                $result["description"] = "Не удалось получить список чеков.";   
            }
        } else {
            $result["description"] = "Не удалось получить токен.";   
        }

        return $result;
    }

    public function getMerchants($phone) {
        $methodResult = $this->PAPI_Merchants();
        if ($methodResult["status"] && $methodResult["data"]->result->state == "Success") {
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить историю покупок.";    
        }

        return $result;
    }

    public function setDiscountAttributeValue($personId, $value) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $data = [
                "id" => 365658,
                "attribute" =>  [
                    '$type' => "Loymax.SystemApi.Models.Attributes.UserAttributeViewModel, Loymax.SystemApi",
                    "type" => "Bool",
                    "params" => null,
                    "description" => "Атрибут клиента Дисконт",
                    "mergeMode" => "AlwaysCopy",
                    "required" => false,
                    "isDeleted" => false,
                    "isHidden" => false,
                    "isDynamic" => false,
                    "items" => [],
                    "hasMultipleValues" => false,
                    "useTemplateEngine" => false,
                    "id" => 39,
                    "name" => "Дисконт",
                    "order" => 0,
                    "logicalName" => "AtrDiscount",
                    "historyIsRecorded" => false
                ],
                "value" => $value
            ];
            $result = $this->SAPI_UpdateAttributeValue($personId, $data);
        } else {
            $result["description"] = "Не удалось получить токен.";
        }

        return $result;
    }

    public function setCardToAccount($personId, $cardNumber){
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $operationResult = $this->SAPI_SetCardToAccount($personId, [
                "cardNumber" => $cardNumber,
                "cvcCode" => ""
            ]);
            if ($operationResult["status"]) {
                if ($operationResult["data"]->result->state == "Success") {
                    $result["status"] = true;
                } else {
                    $result["description"] = $operationResult["data"]->result->message;
                }
            } else {
                $result["description"] = $operationResult["description"];
            }
        } else {
            $result["description"] = "Не удалось получить токен.";
        }

        return $result;
    }

    public function chargeOns($cardNumbers, $amount, $extId, $description = "bonus.мирпосуды27.рф", $deposit = true, $identifierType = "cardNumber") {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $type = ($deposit ? "Deposit" : "Withdraw");
	    $str = "";
            foreach ($cardNumbers as $el) {
                $str .= '{
                    "Identifier": "' . $el . '",
                    "identifierType": "' . $identifierType . '",
                    "amount": ' . $amount . ',
                    "description": "",
                    "externalInfo": ""
                },';
            }

            $rawData = '{
                "operations": [
                    ' . $str . '
                ],
                "lifeTimeDefinition": {
                    "id": ' . $extId . '
                },
                "legal": {
                    "id": 12,
                    "partnerId": 1
                },
                "currency": {
                    "id": 4,
                    "name": "Тарелочки"
                },
                "loyaltyProgram": {
                    "externalId": "D28308F6-B7F2-4851-8AAD-245A2BD4FFB9",
                    "description": null,
                    "paymentSystemId": 1,
                    "images": null,
                    "id": 1,
                    "name": "Default"
                },
                "partner": {
                    "id": 2,
                    "externalId": "3fba867b-1681-3ade-c5fa-efe294c5b48d",
                    "name": "Мир посуды",
                    "canEdit": true,
                    "loyaltyPrograms": [
                        {
                            "id": 1,
                            "name": "Default"
                        }
                    ],
                    "legalName": null
                },
                "targetGroup": null,
                "type": "' . $type . '",
                "description": "'.$description.'",
                "internalDescription": "'.$description.'"
            }';

            $result = $this->SAPI_Deposit($rawData);
            if ($result["status"] && $result["data"]->result->state == "Success") {
                $result["data"] = $result["data"]->data;
            } else {
                $result["status"] = false;
            }
        } else {
            $result["description"] = "Не удалось получить токен.";
        }

        return $result;
    }

    public function chargeOn($cardNumber, $amount, $extId, $description = "bonus.mp27.ru", $deposit = true) {
        $result = $this->initSAPIToken();
        if ($result["status"]) {
            $type = ($deposit ? "Deposit" : "Withdraw");

            $rawData = '{
                "operations": [
                    {
                        "Identifier": "'.$cardNumber.'",
                        "identifierType": "cardNumber",
                        "amount": '.$amount.',
                        "description": "",
                        "externalInfo": ""
                    }
                ],
                "lifeTimeDefinition": {
                    "id": '.$extId.'
                },
                "legal": {
                    "id": 12,
                    "partnerId": 1
                },
                "currency": {
                    "id": 4,
                    "name": "Тарелочки"
                },
                "loyaltyProgram": {
                    "externalId": "D28308F6-B7F2-4851-8AAD-245A2BD4FFB9",
                    "description": null,
                    "paymentSystemId": 1,
                    "images": null,
                    "id": 1,
                    "name": "Default"
                },
                "partner": {
                    "id": 2,
                    "externalId": "3fba867b-1681-3ade-c5fa-efe294c5b48d",
                    "name": "Мир посуды",
                    "canEdit": true,
                    "loyaltyPrograms": [
                        {
                            "id": 1,
                            "name": "Default"
                        }
                    ],
                    "legalName": null
                },
                "targetGroup": null,
                "type": "' . $type . '",
                "description": "'.$description.'",
                "internalDescription": "'.$description.'"
            }';

            $result = $this->SAPI_Deposit($rawData);
            if ($result["status"] && $result["data"]->result->state == "Success") {
                $result["data"] = $result["data"]->data;
            } else {
                $result["status"] = false;
            }
        } else {
            $result["description"] = "Не удалось получить токен.";
        }

        return $result;
    }

    // Public API methods

    private function initPAPIToken($phone) {
        $result = ["status" => false, "data" => null];

        if (!$this->PAPI_accessToken) {
            $result = $this->PAPI_GetAnonymousToken();
            if ($result["status"]) {
                $result = $this->PAPI_Authorize($phone, $result["data"]->access_token);
                if ($result["status"]) {
                    $result = $this->PAPI_GetToken(explode("code=", $result["headers"][5])[1]);
                    if ($result["status"]) $this->PAPI_accessToken = $result["data"]->access_token;
                }
            } 
        } else {
            $result["status"] = true;
        }

        return $result;
    }

    private function PAPI_GetAnonymousToken() {
        $result = ["status" => false, "data" => null];

        $url = LMX_HOST . "/authorizationservice/token?client_id=" . LMX_CLIENT_ID;
        $data = array("grant_type" => "anonymous");
        $options = array(
            'http' => array(
                'header'  => [
                    "Content-type: application/x-www-form-urlencoded"
                ],
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        
        $result = $this->doRequest($url, $options);

        return $result;
    }

    private function PAPI_Authorize($phone, $anonymousToken, $scope = ["account", "profile", "coupon", "cards", "counters", "history", "merchants"]) {
        $result = ["status" => false, "data" => null];
        // GET https://localhost/authorizationService/oauth/authorize?client_id={app_id}&redirect_uri={redirect_uri}&response_type=code&scope=account HTTP/1.1
        // Authorization: Bearer {merchant_token}
        // X-Identifier: {card_number}

        $url = LMX_HOST . "/authorizationService/oauth/authorize?client_id=" . LMX_CLIENT_ID . "&redirect_uri=" . LMX_REDIRECT_URL . "&response_type=code&scope=" . join("%20", $scope);
        $options = array(
            'http' => array(
                'header'  => [
                    "authorization: Bearer " . $anonymousToken,
                    "X-Identifier: " . $phone
                ]
            )
        );
        
        $result = $this->doRequest($url, $options, true);

        return $result;
    }

    private function PAPI_GetToken($code) {
        $result = ["status" => false, "data" => null];

        $url = LMX_HOST . "/authorizationservice/token";
        $data = [
            "grant_type" => "authorization_code",
            "client_id" => LMX_CLIENT_ID,
            "client_secret" => LMX_SECRET,
            "redirect_uri" => LMX_REDIRECT_URL,
            "code" => $code
        ];
        $options = array(
            'http' => array(
                'header'  => [
                    "Content-type: application/x-www-form-urlencoded"
                ],
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        
        $result = $this->doRequest($url, $options);

        return $result;
    }

    private function PAPI_CheckToken() {
        return ["status" => $this->PAPI_accessToken != null, "description" => (!$this->PAPI_accessToken ? "Отсутствует ключ" : "Ok")];
    }

    private function PAPI_History($filters) {
        // $filters = [
        //     "filter.fromDate" => "2021-09-01",
        //     "filter.historyItemType" => "Purchase",
        //     "filter.currentUser" => "true"
        // ];

        $result = $this->PAPI_CheckToken();
        if ($result["status"]) {
            $result = ["status" => false, "data" => null];

            $params = [];
            foreach ($filters as $key => $param) array_push($params, $key . "=" . $param);

            $url = LMX_HOST . "/publicapi/v1.2/History?" . join("&", $params);
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->PAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function PAPI_Merchants() {
        // $filters = [
        //     "filter.fromDate" => "2021-09-01",
        //     "filter.historyItemType" => "Purchase",
        //     "filter.currentUser" => "true"
        // ];

        $url = LMX_HOST . "/publicapi/v1.2/Merchants";
        $options = array(
            'http' => array(
                'header'  => [
                    // "Content-Type: application/json"
                ]
            )
        );
        
        $result = $this->doRequest($url, $options);

        return $result;
    }
    
    // System API methods
    
    public function initSAPIToken() {
        $result = ["status" => false, "data" => null];

        if (!$this->SAPI_accessToken) {
            $result = $this->SAPI_GetToken();
            if ($result["status"]) $this->SAPI_accessToken = $result["data"]->access_token;    
        } else {
            $result["status"] = true;
        }

        return $result;
    }

    private function SAPI_GetToken() {
        $result = ["status" => false, "data" => null];

        $url = LMX_HOST . "/authorizationservice/token";
        $data = array('grant_type' => 'password', 'username' => LMX_ARM_USER_NAME, 'password' => LMX_ARM_USER_PASS, 'area' => 'users');
        $options = array(
            'http' => array(
                'header'  => [
                    "Content-type: application/x-www-form-urlencoded"
                ],
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        
        $result = $this->doRequest($url, $options);

        return $result;
    }

    private function SAPI_CheckToken() {
        return ["status" => $this->SAPI_accessToken != null, "description" => (!$this->SAPI_accessToken ? "Отсутствует ключ" : "Ok")];
    }

    private function SAPI_Users($phone) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $result = ["status" => false, "data" => null];

            $url = LMX_HOST . "/systemapi/api/Users?phone=" . $phone;
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );

            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_Cards($cardNumber) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $result = ["status" => false, "data" => null];

            $url = LMX_HOST . "/systemapi/api/Cards?cardNumber=" . $cardNumber;
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_BeginRegistration($data) {
        // $data = [
        //     "login" => "xxxxx",
        //     "password" => "",
        //     "withoutCall" => true
        // ];

        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Registration/BeginRegistration";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method'  => 'POST',
                    'content' => json_encode($data)
                )
            );

            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_RegistrationActions($personId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/RequiredActions";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_AcceptTenderOffer($personId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/AcceptTenderOffer";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method'  => 'POST',
                    'content' => json_encode([])
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_SetRandomPassword($personId, $needNotify = "false") {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/SetRandomPassword?needNotify=" . $needNotify;
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method'  => 'POST',
                    'content' => json_encode([])
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_SetPhone($personId, $phone) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/SetPhone";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method'  => 'POST',
                    'content' => json_encode([
                        "phone" => $phone,
                        "withoutCall" => true
                    ])
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_Questions($personId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/Questions";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_UpdateAnswers($personId, $answers) {
        // $answers = [
        //     [
        //         "questionId" => 1,
        //         "fixedAnswerIds" => [2, 15, 16, 23],
        //         "value" => "Иван",
        //         "tag" => "0",
        //         "questionGroupId" => 1,
        //         "fixedAnswers" => []
        //     ],[
        //         "questionId" => 2,
        //         "fixedAnswerIds" => null,
        //         "value" => "Иванов",
        //         "tag" => "0",
        //         "questionGroupId" => 1,
        //         "fixedAnswers" => []
        //     ],[
        //         "questionId" => 17,
        //         "fixedAnswerIds" => null,
        //         "value" => "Иркутск",
        //         "tag" => "0",
        //         "questionGroupId" => 1,
        //         "fixedAnswers" => []
        //     ]
        // ];

        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/UpdateAnswers";
            // $url = "http://localhost/log?sds=s";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method' => 'POST',
                    'content' => json_encode($answers)
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    public function SAPI_TryFinishRegistration($personId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/v1.2/Registration/TryFinishRegistrationCustomer";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/x-www-form-urlencoded",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method' => 'POST',
                    'content' => "personId=" . $personId
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_Balance($personId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/Balance";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_DetailedBalance($personId, $date = "") {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/DetailedBalance" . ($date ? "?date=" . $date : "");
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_History($personId, $filters = null, $debug = false) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/History";
            if ($filters) $url .= "?";
            if (isset($filters["fromDate"])) $url .= "&filter.fromDate=" . $filters["fromDate"];
            if (isset($filters["count"])) $url .= "&filter.count=" . $filters["count"];

            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_UsersCards($personId, $cardShowMode = "active") {
        if ($personId && $personId !=="") {
            $result = $this->SAPI_CheckToken();
        } else {
            $result["status"] = false;
        }
        
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/Users/" . $personId . "/Cards?cardShowMode=" . $cardShowMode;
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_Purchases($filters) {
        // $filters = [
        //     "startChequeTime" => "2021-09-01", <!--Нижняя граница времени покупки-->
        //     "lastChequeTime" => "2020-04-20T07:09:31.963Z", <!--Верхняя граница времени покупки-->
        //     "merchantIds" => [ <!--Идентификаторы магазинов-->
        //       
        //     ],
        //     "loyaltyPrograms" => [ <!--Идентификаторы программ лояльности-->
        //       
        //     ],
        //     "chequeNumber" => "string", <!--Номер чека-->
        //     "count" => 9999, <!--Количество элементов выборки-->
        //     "from" => 0, <!--Порядковый номер элемента, с которого формировать выборку-->
        //     "cardNumber" => "", <!--Номер карты-->
        //     "personId" => 0, <!--Идентификатор Участника ПЛ-->
        //     "personIdentifier" => "", <!--Идентификатор, использованный при совершении покупки (номер карты, номер телефона)-->
        //     "state" => "Confirmed" <!--Состояние покупки: Created - создана, Confirmed - подтверждена, Canceled - отменена-->
        // ];

        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/purchases";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method' => 'POST',
                    'content' => json_encode($filters)
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_Purchase($purchaseId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/purchases/" . $purchaseId;
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_PurchaseOperations($purchaseId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/purchases/" . $purchaseId . "/operations";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_ChequePositions($purchaseId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/purchases/" . $purchaseId . "/ChequePositions?showCanceledOperations=false";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ]
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }
    
    private function SAPI_EmitVirtual($personId) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/users/" . $personId . "/EmitVirtual";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method' => "PUT",
                    'content' => json_encode('')
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_Deregister($personId, $reason = "Так положено") {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/users/" . $personId . "/Deregister";
            $options = array(
                'http' => array(
                    'header' => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method' => "POST",
                    'content' => json_encode(["reason" => $reason])
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_UpdateAttributeValue($personId, $data) {    
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/users/" . $personId . "/UpdateAttributeValue";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method'  => 'POST',
                    'content' => json_encode($data)
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_Deposit($data) {    
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/deposit";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method'  => 'PUT',
                    'content' => $data
                )
            );
            
            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    private function SAPI_SetCardToAccount($personId, $data) {
        $result = $this->SAPI_CheckToken();
        if ($result["status"]) {
            $url = LMX_HOST . "/systemapi/api/users/" . $personId . "/SetCard";
            $options = array(
                'http' => array(
                    'header'  => [
                        "Content-Type: application/json",
                        "authorization: Bearer " . $this->SAPI_accessToken
                    ],
                    'method'  => 'POST',
                    'content' => json_encode($data)
                )
            );

            $result = $this->doRequest($url, $options);
        }

        return $result;
    }

    //////////////////////////////////////////////////

    private function getTokenAdminLMX() {
        $postdata = http_build_query(
            array(
                'grant_type' => 'password',
                'username' => 'i.sorokin',
                'password' => 'sorokin',
                'area' => 'users',
            )
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/authorizationService/token', false, $context));


        return $result['access_token'];
    }

    private function beginRegistrationLMX($phone, $authToken) {
        $postdata = array(
            'login' => $phone,
            'password' => "",
            'withoutCall'=> true
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json; charset=UTF-8',
                    'Accept: application/json, text/plain, */*',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => json_encode($postdata)
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/systemapi/api/Registration/BeginRegistration', false, $context));

        $resultData = (array) $result["data"];

        return $resultData;
    }

    private function acceptTenderOfferLMX($authToken, $personalID) {

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json; charset=UTF-8',
                    'Accept: application/json, text/plain, */*',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => 'test'
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/systemapi/api/Users/' .$personalID. '/AcceptTenderOffer', false, $context));


        return $result;
    }

    private function getPhoneNumberLMX($authToken) {

        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => 'test'
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/publicapi/v1/User/PhoneNumber/', false, $context));

        return $result;
    }

    private function regNewCardLMX($authToken, $cardNumber) {

        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => 'test'
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/systemapi/api/Cards?cardNumber='.$cardNumber, false, $context));


        return $result;
    }

    private function getPersonalIdLMX($authToken) {

        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => 'test'
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/publicapi/v1.2/User', false, $context));

        $resultData = (array) $result['data'];


        return $resultData['id'];
    }

    private function setPhoneLMX($authToken, $phone, $id) {
        $postdata = array(
            'phone' => $phone,
            'withoutCall' => true
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json; charset=UTF-8',
                    'Accept: application/json, text/plain, */*',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => json_encode($postdata)
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/systemapi/api/users/' . $id . '/SetPhone', false, $context));


        return $result;
    }

    public function setPasswordLMX($personalID, $authToken) {

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => 'test'
            )
        );

        $context  = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/systemapi/api/Users/' .$personalID. '/SetRandomPassword?needNotify=false', false, $context);


        return $result;
    }

    public function getQuestionsLMX($personalID, $authToken) {

        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => 'test'
            )
        );

        $context  = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/systemapi/api/Users/' .$personalID. '/Questions', false, $context);


        return $result;
    }

    public function setProfileLMX($personalID, $authToken, $data) {

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json; charset=UTF-8',
                    'Accept: application/json, text/plain, */*',
                    'Accept-Encoding: gzip, deflate, br',
                    'Accept-Language: ru',
                    'Connection: close',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => json_encode($data)
            )
        );

        $context  = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/systemapi/api/Users/' .$personalID. '/UpdateAnswers', false, $context);


        return $result;
    }

    public function setVirtualCardLMX($personalID, $authToken) {

        $opts = array('http' =>
            array(
                'method'  => 'PUT',
                'header'  => [
                    'Content-Type: application/json; charset=UTF-8',
                    'Accept: application/json, text/plain, */*',
                    'Accept-Encoding: gzip, deflate, br',
                    'Accept-Language: ru',
                    'Connection: close',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => 'test'
            )
        );

        $context  = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/systemapi/api/Users/' .$personalID. '/EmitVirtual', false, $context);


        return $result;
    }

    private function getActionsLMX($personalID, $authToken) {

        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Content-Type: application/json;charset=utf-8',
                    'Authorization: Bearer ' . $authToken,
                ]
            )
        );

        $context  = stream_context_create($opts);

        header('Content-Type: application/json');
        header('Authorization: ' . $authToken);

        $result = file_get_contents(LMX_HOST.'/systemapi/api/users/' .$personalID. '/RegistrationActions', false, $context);


        return $result;
    }

    private function finishRegistrationLMX($personalID, $authToken) {
        $postdata = array(
            "personId" => (int) $personalID,
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json; charset=UTF-8',
                    'Accept: application/json, text/plain, */*',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => json_encode($postdata)
            )
        );

        $context  = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/systemapi/api/Registration/TryFinishRegistration', false, $context);

        return $result;
    }

    private function getAnonTokenLMX() {
        $postdata = http_build_query(
            array(
                'grant_type' => 'anonymous',
            )
        );


        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/authorizationService/token?client_id='.LMX_CLIENT_ID, false, $context));

        return $result['access_token'];
    }

    private function getAuthCodeLMX($accessToken, $phone) {
        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Authorization: Bearer ' . $accessToken,
                    'X-Identifier: '. $phone
                ],
            )
        );

        $context = stream_context_create($opts);

        file_get_contents(LMX_HOST.'/authorizationService/oauth/authorize?client_id='.LMX_CLIENT_ID.'&redirect_uri='.LMX_REDIRECT_URL.'&response_type=code&scope=account%20profile%20history', false, $context);

        $location = $http_response_header[5];

        $code = explode('code=', $location)[1];

        return $code;

    }

    private function OauthLMX($phone) {
        $accessToken = $this->getAnonTokenLMX();
        $authCode = $this->getAuthCodeLMX($accessToken, $phone);

        $postdata = http_build_query(
            array(
                'grant_type' => 'authorization_code',
                'client_id' => LMX_CLIENT_ID,
                'client_secret' => LMX_SECRET,
                'redirect_uri' => LMX_REDIRECT_URL,
                'code' => $authCode
            )
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context  = stream_context_create($opts);

        $result = (array) json_decode(file_get_contents(LMX_HOST.'/authorizationService/token', false, $context));

        return $result['access_token'];
    }

    private function getBalanceLMX($authToken) {
        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $authToken,
                ],
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/publicapi/v1.2/User/Balance', false, $context);

        return $result;
    }

    private function getTransactionsLMX($authToken) {
        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $authToken,
                ],
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/publicapi/v1.2/History', false, $context);

        return $result;
    }

    private function chargeOnBonusAccountLMX($cardNumber, $amount, $chargeType, $chargeDescription, $lifeTimeDefinition = null) {
        $result = ["status" => false, "data" => null];
        $authToken = $this->getTokenAdminLMX();
        $date = date('Y-m-d H:i:s');

        if ($lifeTimeDefinition !== null){
            $lifeTimeDefinition = [
                'certainActivationDate' => $date,
                'certainExpirationDate' => date('Y-m-d H:i:s', strtotime($date. '+ '.$lifeTimeDefinition.' days')),
            ];
        }


        $postdata = array(
            'operations' => array(array(
                '$type' => "Loymax.SystemApi.Models.BatchOperations.OperationCardModel, Loymax.SystemApi",
                'description' => $chargeType,
                'amount' => $amount,
                'externalInfo'=> '',
                'cardNumber'=> $cardNumber,
            )),
            'lifeTimeDefinition' => null,
            'legal' => array(
                'id' => 1,
                'partnerId' => 1,
            ),
            'currency' => array(
                'id' => 1,
                'name'=> 'Бонусы',
            ),
            'loyaltyProgram' => array(
                'id' => 1,
                'name'=> 'Default',
                'description'=> null,
                'externalId'=> 'D28308F6-B7F2-4851-8AAD-245A2BD4FFB9',
                'paymentSystemId'=> 1,
                'images'=> null,
            ),
            'partner' => array(
                'id' => 1,
                'name'=> 'Столица',
                'externalId'=> 'e5cb14c6-03d1-5666-1b15-30ddf7a5c3dc',
                'canEdit'=> true,
                'loyaltyPrograms'=> array(
                    'id' => 1,
                    'name'=> 'Default',
                ),
                'legalName' => null
            ),
            'targetGroup' => null,
            'description' => $chargeDescription,
            'type' => $chargeType,
            'internalDescription' => $chargeDescription,

        );

        $opts = array('http' =>
            array(
                'method'  => 'PUT',
                'header'  => [
                    'Content-Type: application/json; charset=UTF-8',
                    'Accept: application/json, text/plain, */*',
                    'Authorization: Bearer ' . $authToken
                ],
                'content' => json_encode($postdata)
            )
        );

        $context  = stream_context_create($opts);

        $result = file_get_contents(LMX_HOST.'/systemapi/api/deposit', false, $context);


        return $result;


    }

    public function getAttributes($phone, $logicalName) {
        $result = $this->initPAPIToken($phone);
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->PAPI_getAttributes($logicalName);
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить токен.";
        }

        return $result;
    }

    private function PAPI_getAttributes($logicalName) {
        $result = $this->PAPI_CheckToken();
        if ($result["status"]) {
            $result = ["status" => false, "data" => null];


            $url = LMX_HOST . "/publicapi/v1.2/User/Attributes/Common/Values/" . $logicalName;
            $options = array(
                'http' => array(
                    'header' => [
                        "Authorization: Bearer " . $this->PAPI_accessToken
                    ]
                )
            );

            $result = $this->doRequest($url, $options);
        }

        return $result;
    }


    public function setAttributes($phone, $logicalName, $value) {
        $result = $this->initPAPIToken($phone);
        if ($result["status"]) {
            $result = ["status" => false, "description" => ""];

            $methodResult = $this->PAPI_setAttributes($logicalName, $value);
            $result = $methodResult;
        } else {
            $result["description"] = "Не удалось получить токен.";
        }

        return $result;
    }


    private function PAPI_setAttributes($logicalName, $value) {
        $result = $this->PAPI_CheckToken();
        if ($result["status"]) {
            $result = ["status" => false, "data" => null];
            $attrData = [
                'xPath' => '',
                'value' => $value
            ];

            $url = LMX_HOST . "/publicapi/v1.2/User/Attributes/Common/Values/" . $logicalName;
            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => [
                        "Content-type: application/x-www-form-urlencoded",
                        "Authorization: Bearer " . $this->PAPI_accessToken
                    ],
                    'content' => json_encode($attrData)
                )
            );

            $result = $this->doRequest($url, $options);
        }

        return $result;
    }
}
