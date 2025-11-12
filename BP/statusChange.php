<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");


//=== functions
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}
require_once($_SERVER["DOCUMENT_ROOT"]."/functions/bp_workflow_functions.php");

//=== functions

if (!function_exists('getStageType')) {
    function getStageType($stage_id)
    {
        if ($stage_id) {
            $stageGroupElement = getCIBlockElementsByFilter(array("NAME" => $stage_id, "IBLOCK_ID" => 16));
            if (count($stageGroupElement)) {
                return $stageGroupElement[0]["STAGE_GROUP"];
            }
        }
        return 0;
    }

}
if (!function_exists('alreadyInQueue')) {
    function alreadyInQueue($queueString, $dealID)
    {
        $queue = explode("|", "$queueString");
        if (in_array($dealID, $queue)) {
            return true;
        } else return false;
    }
}
if (!function_exists('firstInQueue')) {
    function firstInQueue($queueString, $dealID)
    {
        $queue = explode("|", "$queueString");
        if ($dealID == $queue[1]) {
            return true;
        } else return false;
    }
}

if (!function_exists('new_stage')) {
    function new_stage($dealID, $arProducts, $deal)
    {
        if (count($arProducts)) {
            $elementsForUpdate = array();
            $prodStatus = "";
            foreach ($arProducts as $product) {
                $element = getCIBlockElementByID($product["PRODUCT_ID"]);
                $queue = explode("|", $element["QUEUE"]);
                if (!in_array($dealID, $queue)) {
                    $element["QUEUE"] = str_replace("|$dealID", "", $element["QUEUE"]);
                    $element["QUEUE"] .= "|$dealID";
                }
                if ($element["OWNER_DEAL"] == $dealID) {
                    $notification = $element["PRODUCT_TYPE"] . " N" . $element["Number"] . " გათავისუფლდა ";
                    sendNotificationToQueue($element["QUEUE"], $notification);
                    sendNotificationToResponsible($dealID, $notification);
                    if ($element["QUEUE"]) {
                        $element["_WJ6N47"] = "ჯავშნის რიგი";
                        $element["DEAL_RESPONSIBLE"] = intval($deal["ASSIGNED_BY_ID"]);
                    } else {
                        $element["_WJ6N47"] = "თავისუფალი";
                    }
                    // $element["DEAL_RESPONSIBLE"] = "";
                    $element["RESERVATION_PERIOD"] = "";
                    $element["JAVSHANI_TYPE"] = "";
                    $element["__3Y8CJ0"] = "";
                    $element["__MZ1V7B"] = "";
                    $element["OWNER_DEAL"] = "";
                    $element["OWNER_CONTACT"] = "";
                    $element["OWNER_COMPANY"] = "";
                    $element["bankLoan"] = "";
                    $element["barter"] = "";
                } else {
                    if ($element["_WJ6N47"] == "თავისუფალი" && $element["QUEUE"]) {
                        $element["_WJ6N47"] = "ჯავშნის რიგი";
                        $element["DEAL_RESPONSIBLE"] = $deal["ASSIGNED_BY_ID"];
                    }
                }
                $elementsForUpdate[$product["PRODUCT_ID"]] = $element;
            }

            $logText = updateProdElement($elementsForUpdate);

            return "ჯავშნის რიგი";
        }
    }
}
if (!function_exists('reservation')) {
    function reservation($dealID, $arProducts, $deal)
    {
        $sendNotification = false;
        $errors = array();
        if (count($arProducts)) {
            $elementsForUpdate = array();
            $errors = array();
            foreach ($arProducts as $product) {
                $element = getCIBlockElementByID($product["PRODUCT_ID"]);
                if ($element["OWNER_DEAL"] == $dealID) {
                    if ($element["_WJ6N47"] != "დაჯავშნილი") $sendNotification = true;
                    $element = preparationProductForReservation($element, $deal);
                } else {
                    if ($element["_WJ6N47"] == "თავისუფალი" || ($element["_WJ6N47"] == "ჯავშნის რიგი" && firstInQueue($element["QUEUE"], $dealID))) {
                        $element = preparationProductForReservation($element, $deal);
                        $sendNotification = true;
                    } else {
                        array_push($errors, $element["PRODUCT_TYPE"] . " N" . $element["Number"] . " ProdID " . $element["ID"] . " არ არის თავისუფალი");
                    }
                }
                $elementsForUpdate[$product["PRODUCT_ID"]] = $element;
            }

            if (empty($errors)) {
                $logText = updateProdElement($elementsForUpdate);
                $logText .= "დაიჯავშნა";
                if ($sendNotification) {
                    sendNotificationToResponsible($dealID, $logText);
                } else {
                    $logText = "";
                }

            } else {

                $logText = changeDealStageToNew($deal, $errors);
                updateQueue($arProducts, $dealID);
                sendNotificationToResponsible($dealID, $logText);

            }
        } else {
            changeDealStageToNew($deal, $errors);
            $logText = "დილზე პროდუქტი არ არის მიბმული";
            sendNotificationToResponsible($dealID, $logText);

        }
        return $logText;
    }
}

if (!function_exists('sold')) {
    function sold($dealID, $arProducts, $deal)
    {
        $errors = array();
        $sendNotification = false;
        if (count($arProducts)) {
            $elementsForUpdate = array();
            foreach ($arProducts as $product) {
                $element = getCIBlockElementByID($product["PRODUCT_ID"]);

                if ($element["OWNER_DEAL"] == $dealID) {
                    if ($element["_WJ6N47"] != "გაყიდული") $sendNotification = true;
                    $element = preparationProductForSale($element, $deal);
                } else {
                    if ($element["_WJ6N47"] == "თავისუფალი" || ($element["_WJ6N47"] == "ჯავშნის რიგი" && firstInQueue($element["QUEUE"], $dealID))) {
                        $element = preparationProductForSale($element, $deal);
                        $sendNotification = true;
                    } else {
                        array_push($errors, $element["PRODUCT_TYPE"] . " N" . $element["Number"] . " ProdID " . $element["ID"] . " არ არის თავისუფალი");
                    }
                }
                $elementsForUpdate[$product["PRODUCT_ID"]] = $element;
            }

            if (empty($errors)) {
                $logText = updateProdElement($elementsForUpdate);
                $logText .= "გაიყიდა";
                if ($sendNotification) sendNotificationToResponsible($dealID, $logText);
            } else {
                $logText = changeDealStageToNew($deal, $errors);
                updateQueue($arProducts, $dealID);
                sendNotificationToResponsible($dealID, $logText);

            }
        } else {
            changeDealStageToNew($deal, $errors);
            $logText = "დილზე პროდუქტი არ არის მიბმული";
            sendNotificationToResponsible($dealID, $logText);

        }
        return $logText;
    }
}
if (!function_exists('junk')) {
    function junk($dealID, $arProducts)
    {
        if (count($arProducts)) {
            $elementsForUpdate = array();
            $needNotification = false;
            foreach ($arProducts as $product) {
                $element = getCIBlockElementByID($product["PRODUCT_ID"]);
                $element["QUEUE"] = str_replace("|$dealID", "", $element["QUEUE"]);

                if ($element["OWNER_DEAL"] == $dealID) {
                    $notification = $element["PRODUCT_TYPE"] . " N" . $element["Number"] . " გათავისუფლდა ";
                    sendNotificationToQueue($element["QUEUE"], $notification);
                    sendNotificationToResponsible($dealID, $notification);
                    if ($element["QUEUE"]) {
                        $element["_WJ6N47"] = "ჯავშნის რიგი";
                    } else {
                        $element["_WJ6N47"] = "თავისუფალი";
                    }
                    $element["DEAL_RESPONSIBLE"] = "";
                    $element["RESERVATION_PERIOD"] = "";
                    $element["JAVSHANI_TYPE"] = "";
                    $element["__3Y8CJ0"] = "";
                    $element["__MZ1V7B"] = "";
                    $element["OWNER_DEAL"] = "";
                    $element["OWNER_CONTACT"] = "";
                    $element["OWNER_COMPANY"] = "";
                    $element["bankLoan"] = "";
                    $element["barter"] = "";

                    $needNotification = true;
//                    deleteProdFromDeal($dealID);
                } else {
                    if ($element["_WJ6N47"] == "ჯავშნის რიგი" && !$element["QUEUE"]) {
                        $element["_WJ6N47"] = "თავისუფალი";
                        $needNotification = true;
                    }
                }
                $elementsForUpdate[$product["PRODUCT_ID"]] = $element;
            }
            $logtext = updateProdElement($elementsForUpdate) . "გათავისუფლდა";
            if (!$needNotification) {
                $logtext = false;
            }
            return $logtext;
        }
    }
}

if (!function_exists('deleteProdFromDeal')) {
    function deleteProdFromDeal($dealID)
    {
        $arrLoadProductRows = array();
        $saveRes = CCrmDeal::SaveProductRows($dealID, $arrLoadProductRows);
        $arrForAdd ["UF_CRM_1693385964548"] = "";     //ბინის N
        $arrForAdd ["UF_CRM_1734522977289"] = "";     //ბინის № (num)
        $arrForAdd ["UF_CRM_1709803989"] = "";     //სართული

        $arrForAdd ["UF_CRM_1693385948133"] = "";     //პროექტი
        $arrForAdd ["UF_CRM_1702018321416"] = "";     //ბლოკი
        $arrForAdd ["UF_CRM_1693385814530"] = "";     //კვ/მ ფასი
        $arrForAdd ["UF_CRM_1693385992603"] = "";     //ფართის ტიპი
        $arrForAdd ["UF_CRM_1693386021079"] = "";     //საერთო ფართი
        // $arrForAdd ["UF_CRM_1693398443196"] = "";     //ხელშეკრულების გაფორმების თარიღი
        $arrForAdd ["UF_CRM_1706204702364"] = "";     //საკადასტრო კოდი
        $arrForAdd ["UF_CRM_1732805556346"] = "";     //აივნების ჯამური ფართი
        $arrForAdd ["UF_CRM_1708587255399"] = "";     //სადარბაზო
        $arrForAdd ["UF_CRM_1702018321416"] = "";         //ბლოკი
        $arrForAdd ["UF_CRM_1718097224965"] = "";     //კორპუსი
        $arrForAdd ["UF_CRM_1702650885089"] = "";     //საცხოვრებელი ფართი მ²
        $arrForAdd ["UF_CRM_1733492965560"] = "";     // საწყისი ფასი check
        $arrForAdd ["UF_CRM_1735139781133"] = "";     // ქვეტიპი
        $arrForAdd ["UF_CRM_1735030244"] = "";     // დილის ფასი მძიმეებით
        $arrForAdd ["UF_CRM_1720100945"] = "";     // საწ ფასი
        $arrForAdd ["UF_CRM_1699907477758"] = "";     // ხელშ ნომ
        $arrForAdd ["UF_CRM_1709821902793"] = "";     // ხელშ ნომ





        $Deal = new CCrmDeal();
        $result = $Deal->Update($dealID, $arrForAdd);
    }
}

if (!function_exists('preparationProductForSale')) {
    function preparationProductForSale($element, $deal)
    {
        $dealID = $deal["ID"];
        $element["_WJ6N47"] = "გაყიდული";
        $element["OWNER_DEAL"] = $dealID;
        $element["DEAL_RESPONSIBLE"] = $deal["ASSIGNED_BY_ID"];
        $element["OWNER_CONTACT"] = $deal["CONTACT_ID"];
        $element["OWNER_COMPANY"] = $deal["COMPANY_ID"];
        $element["QUEUE"] = str_replace("|$dealID", "", $element["QUEUE"]);
        return $element;
    }
}
if (!function_exists('preparationProductForReservation')) {
    function preparationProductForReservation($element, $deal)
    {
        $dealID = $deal["ID"];
        $element["_WJ6N47"] = "დაჯავშნილი";
        $element["OWNER_DEAL"] = $dealID;
        $element["DEAL_RESPONSIBLE"] = $deal["ASSIGNED_BY_ID"];
        $element["OWNER_CONTACT"] = $deal["CONTACT_ID"];
        $element["OWNER_COMPANY"] = $deal["COMPANY_ID"];
        $element["QUEUE"] = str_replace("|$dealID", "", $element["QUEUE"]);
        return $element;
    }
}
if (!function_exists('updateProdElement')) {
    function updateProdElement($elements)
    {
        $count = 1;
        $logText = "";
        foreach ($elements as $element) {
            $el = new CIBlockElement;
            $arLoadProductArray = array(
                "PROPERTY_VALUES" => $element,
                "NAME" => $element["NAME"],
                "ACTIVE" => "Y",            // активен
            );
            $res = $el->Update($element["ID"], $arLoadProductArray);
            if ($res) {
                $logText .= "$count)" . $element["PRODUCT_TYPE"] . " N" . $element["Number"] . " ProdID " . $element["ID"] . "\n";
            }
        }
        return $logText;
    }
}
if (!function_exists('changeDealStageToNew')) {
    function changeDealStageToNew($deal, $errors)
    {
        $logText = "";
        $arrForAdd ["STAGE_ID"] = $deal["UF_CRM_1695034234043"] ?: "UC_9QIHB1";     //სთეიჯი
        $Deal = new CCrmDeal();
        $result = $Deal->Update($deal["ID"], $arrForAdd);
        for ($i = 0; $i < count($errors); $i++) {
            $logText .= $i + 1 . ") " . $errors[$i];
        }

        return $logText;
    }
}
if (!function_exists('updateQueue')) {
    function updateQueue($arProducts, $dealID)
    {
        $elementsForUpdate = array();
        foreach ($arProducts as $product) {
            $element = getCIBlockElementByID($product["PRODUCT_ID"]);
            $Queue = explode("|", $element["QUEUE"]);
            if (!in_array($dealID, $Queue)) $element["QUEUE"] .= "|$dealID";
            $elementsForUpdate[$product["PRODUCT_ID"]] = $element;
        }

        updateProdElement($elementsForUpdate);
    }
}
$root=$this->GetRootActivity();
$dealID=$root->GetVariable("DEAL_ID");




$dealID=intval($dealID);
$deal = getDealInfoByID($dealID);
$logText = "";
$allocation = 0;

$arProducts = CCrmDeal::LoadProductRows($dealID);
if($deal["CLOSED"] == "Y"){
    if($deal["STAGE_ID"]=="WON"){
        $logText = sold($dealID,$arProducts,$deal);
    }
    else{
        $logText = junk($dealID,$arProducts);
    }
}
else{
    $stage_group = getStageType($deal["STAGE_ID"]);

    if($stage_group == "new"){
        if($deal["STAGE_ID"] == "FINAL_INVOICE") {
            $logText = new_stage($dealID, $arProducts, $deal);
        }else{
            $logText = junk($dealID,$arProducts);
        }
    }
    else if($stage_group == "Reservation") {
        $logText = reservation($dealID,$arProducts,$deal);
        if(count($arProducts)>1){
            $allocation = 1;
        }
    }
    else if($stage_group == "Sold") {
        $logText = sold($dealID,$arProducts,$deal);
    }
    else if($stage_group == "junk") {
        $logText = junk($dealID,$arProducts);
    }
    else{
        $logText = new_stage($dealID, $arProducts, $deal);
    }
}

$this->SetVariable("log", $logText, JSON_UNESCAPED_UNICODE);
$this->SetVariable("allocation", $allocation);