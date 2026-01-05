<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
ob_end_clean();
$APPLICATION->SetTitle("Title");

function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getCIBlockElementsByID($ID) {
    $arSelect = Array();
    $res = CIBlockElement::GetList(Array(), array("ID"=>$ID), false, Array("nPageSize"=>50), $arSelect);
    if($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        $price = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = $price["PRICE"];

        return $arPushs;
    }
}

function getDealInfoByID ($dealID) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());

    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
    return false;
}


function getDealFields($fieldName,$fieldValue){
    $option=array();
    $rsUField = CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $fieldName));
    while($arUField = $rsUField->GetNext())   {
        if($arUField["VALUE"] == $fieldValue){
            return $arUField["ID"];
        }else{
            return 0;
        }
    }

}

function sendNotificationToQueue ($dealID,$element,$notification) {
    $queue      =   str_replace("|$dealID!","",$element["BOOKING_VGNO4X"]);
    $queue      =   str_replace("!","",$queue);
    $arrQueueDealIDs   =   explode("|",$queue);

    foreach ($arrQueueDealIDs as $QueuedealID) {
        if($QueuedealID>0 && is_numeric($QueuedealID)) {
            $dealData = getDealInfoByID($QueuedealID);
            $responsible = $dealData["ASSIGNED_BY_ID"];
            $arFields = array(
                "MESSAGE_TYPE" => "S", # P - private chat, G - group chat, S - notification
                "TO_USER_ID" => 1,
                "FROM_USER_ID" => 1,
                "MESSAGE" => $notification."$QueuedealID/",
                "AUTHOR_ID" => 1,
                "EMAIL_TEMPLATE" => "some",

                "NOTIFY_TYPE" => 4,  # 1 - confirm, 2 - notify single from, 4 - notify single
                "NOTIFY_MODULE" => "main", # module id sender (ex: xmpp, main, etc)
                "NOTIFY_EVENT" => "IM_GROUP_INVITE", # module event id for search (ex, IM_GROUP_INVITE)
                "NOTIFY_TITLE" => "title to send email", # notify title to send email
            );
            CModule::IncludeModule('im');
            CIMMessenger::Add($arFields);
        }
    }
}

function freeProduct($productIDs,$dealID){
    foreach ($productIDs as $productID){
        if ($productID != "") {
            $element = getCIBlockElementsByID($productID);
//            printArr($element);

            //-------------------------element update-------------------------//

            $el = new CIBlockElement;
            if ($element["STATUS"] == "ჯავშნის რიგი" && $element["QUEUE"] == "|$dealID") {
                $element["STATUS"] = "თავისუფალი";                             //status
            }
            $element["QUEUE"] = str_replace("|$dealID","",$element["QUEUE"]);

            $arLoadProductArray = array(
                "PROPERTY_VALUES" => $element,
                "NAME" => $element["NAME"],
                "ACTIVE" => "Y",            // активен
            );
            $res = $el->Update($element["ID"], $arLoadProductArray);
        }

    }
}

function addQueueToProd($productID,$dealID)
{
    if (is_numeric($productID)) {
        $element = getCIBlockElementsByID($productID);
//            printArr($element);

        //-------------------------element update-------------------------//

        $el = new CIBlockElement;
        $arrQueue = explode("|", $element["QUEUE"]);

        if ($element["STATUS"] == "დაჯავშნილი" && $element["OWNER_DEAL"] != $dealID) {
            if (!(in_array("$dealID", $arrQueue))) {
                $element["QUEUE"] = $element["QUEUE"] . "|$dealID";
            }
        }
        $arLoadProductArray = array(
            "PROPERTY_VALUES" => $element,
            "NAME" => $element["NAME"],
            "ACTIVE" => "Y",            // активен
        );
        $res = $el->Update($element["ID"], $arLoadProductArray);
        return array("status" => 200, "text" => "updated");

    }
    return array("status"=>400,"text"=>"error");

}

function getDealProds ($dealID) {
    $prods = CCrmDeal::LoadProductRows($dealID);
    $products = [];
    foreach ($prods as $prod) {
        array_push($products, $prod["PRODUCT_ID"]);
    }
    return $products;
}

function getProjectDropdownID($productsProject,$fieldName){

    $rsUField = CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $fieldName));
    while($arUField = $rsUField->GetNext())   {
        if($arUField["VALUE"]==$productsProject){
            return $arUField["ID"];
        }
    }
    return false;

}

function getCIBlockElementsByFilter($arrFilter)
{
    $arElements = array();
    $res = CIBlockElement::GetList(array("ID"=>"DESC"), $arrFilter, false, Array("nPageSize" => 1), Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"));
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function getStageType($stage_id){
    $stageGroupElement = getCIBlockElementsByFilter(array("NAME"=>$stage_id,"IBLOCK_ID"=>22));
    if(count($stageGroupElement)){
        return $stageGroupElement[0]["STAGE_GROUP"];
    }
    return 0;
}

function deleteElementFromArray($array, $element)
{
    $index = array_search($element, $array);
    if ($index !== false) {
        unset($array[$index]);
    }
    return $array;
}

function getProdsFroDeal($prodIDs){
    $arrProducts = $prodIDs;
    foreach ($prodIDs as $prodID){
        $productData = getCIBlockElementsByID($prodID);
        if (count($productData) > 0) {
            $arrRELATED_PRODUCT = $productData["RELATED_PRODUCT"];
            if (is_array($arrRELATED_PRODUCT)){
                $arrProducts = array_unique(array_merge($arrProducts, $arrRELATED_PRODUCT));
            }
        }else{
            $arrProducts = deleteElementFromArray($arrProducts,$prodID);
        }
    }
    return $arrProducts;
}

function putCommasInNum($price){
    return number_format($price, 2, '.', ',');
}



function DATE_Sityvierad($date){
    $date=explode("/",$date);
    switch ($date[1]){
        case "01" : $date[1] = "იანვარს"; break;
        case "02" : $date[1] = "თებერვალს"; break;
        case "03" : $date[1] = "მარტს";break;
        case "04" : $date[1] = "აპრილს";break;
        case "05" : $date[1] = "მაისს";break;
        case "06" : $date[1] = "ივნისს";break;
        case "07" : $date[1] = "ივლისს";break;
        case "08" : $date[1] = "აგვისტოს";break;
        case "09" : $date[1] = "სექტემბერს";break;
        case "10" : $date[1] = "ოქტომბერს";break;
        case "11" : $date[1] = "ნოემბერს";break;
        case "12" : $date[1] = "დეკემბერს";break;
    }
    $date= $date[2]."  წლის  ". $date[0]."  ". $date[1];
    return $date;

}

function DATE_SityvieradNewFormat($date){
    $date=explode("/",$date);
    switch ($date[1]){
        case "01": $date[1] = "Январь"; break;
        case "02": $date[1] = "Февраль"; break;
        case "03": $date[1] = "Март"; break;
        case "04": $date[1] = "Апрель"; break;
        case "05": $date[1] = "Май"; break;
        case "06": $date[1] = "Июнь"; break;
        case "07": $date[1] = "Июль"; break;
        case "08": $date[1] = "Август"; break;
        case "09": $date[1] = "Сентябрь"; break;
        case "10": $date[1] = "Октябрь"; break;
        case "11": $date[1] = "Ноябрь"; break;
        case "12": $date[1] = "Декабрь"; break;
    }
    $date= $date[0]." ". $date[1].", ". $date[2];
    return $date;

}

function DATE_SityvieradNewFormatEng($date){
    $date = explode("/", $date);
    switch ($date[1]){
        case "01" : $date[1] = "January"; break;
        case "02" : $date[1] = "February"; break;
        case "03" : $date[1] = "March"; break;
        case "04" : $date[1] = "April"; break;
        case "05" : $date[1] = "May"; break;
        case "06" : $date[1] = "June"; break;
        case "07" : $date[1] = "July"; break;
        case "08" : $date[1] = "August"; break;
        case "09" : $date[1] = "September"; break;
        case "10" : $date[1] = "October"; break;
        case "11" : $date[1] = "November"; break;
        case "12" : $date[1] = "December"; break;
    }
    $date = $date[0] . " " . $date[1] . ", " . $date[2];
    return $date;
}



$arrLoadProductRows = array();
$renovationPrice=0;
$prodPrice=0;

// --- params ---
try {
    $json = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
$prodExists = true;
if($json["deal_id"]){
    $prodIdsForAdd = $json["arrProds"];
    if(count($prodIdsForAdd)) {
        $prodsForDeal = getProdsFroDeal($prodIdsForAdd);
        $prodExists = count($prodsForDeal);
    }else{
        $prodsForDeal = array();
    }
    $dealId = $json["deal_id"];
    $ASSIGNED_BY_ID = 1;
}
else{
    if(is_numeric($_GET["productId"]) && $_GET["productId"] > 0){
        $productId = $_GET["productId"];
        $prodsForDeal    = getProdsFroDeal(array($productId));
        $prodExists = count($prodsForDeal);
    }else{
        $prodsForDeal = array();
    }
    $dealId = $_GET["deal_id"];
    $ASSIGNED_BY_ID = $_GET["userID"]?:1;
}


//$prodsForDeal = 34;
//$dealId = 46;
// -------

$resArray = array();
$dealExists = false;

if(is_numeric($dealId) && $dealId != 0) {
    $dealData = getDealInfoByID($dealId);
    if($dealData){
        $dealExists = true;
    } else{
        $resArray["status"] = 400;
        $resArray["error"] = "deal was not found";
    }

}
elseif (is_numeric($dealId) && $dealId == 0){

    $CCrmDeal = new CCrmDeal(false);
    $arForDeal = array(
        "CONTACT_ID"	    => 1653,
        "CATEGORY_ID"       => 0,
        "STAGE_ID"		    => "NEW",
        "ASSIGNED_BY_ID"    => $ASSIGNED_BY_ID,
    );
    $dealId = $CCrmDeal->Add($arForDeal, true, array('DISABLE_USER_FIELD_CHECK' => true, 'REGISTER_SONET_EVENT' => true));
    if($dealId && is_numeric($dealId)){
        $dealData = getDealInfoByID($dealId);
        if($dealData){
            $dealExists = true;
            $resArray["dealID"] = $dealId;
            $resArray["LINK"] = "http://146.255.242.182//$dealId/";
        } else{
            $resArray["status"] = 400;
            $resArray["error"] = "deal was not found";
        }
    }else{
        $resArray["status"] = 400;
        $resArray["error"] = "დილი ვერ დარეგისტრირდა";
    }
}
else {
    $resArray["status"] = 400;
    $resArray["error"] = "Bad Request";
}

if($dealExists) {
    $allowed = ["PREPARATION", "PREPAYMENT_INVOICE", "EXECUTING"];

    if (!in_array($dealData["STAGE_ID"], $allowed)) {
        echo json_encode([
            "status" => 403,
            "error"  => "თქვენ არ გაქვთ ბინის დაჯავშნის/წაშლის უფლება",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($prodExists) {
        $stage_group = getStageType($dealData["STAGE_ID"]);
        if ($dealExists) {
            if (is_array($prodsForDeal) && !empty($prodsForDeal)) {
                //დილზე მიბმული პროდუქტები
                $dealsProduct = array_diff(getDealProds($dealId), $prodsForDeal);

                $arrLoadProductRows = array();
                $productData = "";
                $project = "";
                $korp = "";
                $building = "";
                $prodFLOOR = "";
                $prodNumber = "";
                $prodTOTAL_AREA = "";
                $prodPrice = "";
                $LIVING_SPACE = "";
                $balconyArea = "";
                $balconyAreaKvmPrice = "";
                $balconyTotalPrice = "";
                $terasa = "";
                $terasaPrice = "";
                $yardSpace = "";
                $yardKvmPrice = "";
                $yardTotalPrice = "";
                $KVM_PRICE_REPAIR = "";
                $FULL_PRICE_REPAIR = "";
                $KVM_PRICE_FURNITURE = "";
                $FULL_PRICE_FURNITURE = "";
                $PRODUCT_TYPE = "";
                // $sawyisi_girebuleba = "";
                $sawyisi_girebuleba_kv = "";
                $FLAT_PRICE = "";
                $productData = array();
                $opportunity = 0;
                $projectEndDate = "";



                foreach ($prodsForDeal as $related_product) {
                    $productData = getCIBlockElementsByID($related_product);
                    $project ? $project .= " /" . $productData["PROJECT"] : $project = $productData["PROJECT"];
                    $korp ? $korp .= " /" . $productData["KORPUSIS_NOMERI_XE3NX2"] : $korp = $productData["KORPUSIS_NOMERI_XE3NX2"];
                    $building ? $building .= " /" . $productData["BUILDING"] : $building = $productData["BUILDING"];
                    
                    $prodFLOOR ? $prodFLOOR .= " /" . $productData["FLOOR"] : $prodFLOOR = $productData["FLOOR"];
                    $prodNumber ? $prodNumber .= " /" . $productData["Number"] : $prodNumber = $productData["Number"];
                    $prodTOTAL_AREA ? $prodTOTAL_AREA .= " /" . $productData["TOTAL_AREA"] : $prodTOTAL_AREA = $productData["TOTAL_AREA"];
                    $prodPrice ? $prodPrice .= " /" . $productData["PRICE"] : $prodPrice = $productData["PRICE"];
                    $LIVING_SPACE ? $LIVING_SPACE .= " /" . $productData["LIVING_SPACE"] : $LIVING_SPACE = $productData["LIVING_SPACE"];
                    $balconyArea ? $balconyArea .= " /" . $productData["__FVE8A2"] : $balconyArea = $productData["__FVE8A2"];
                    $balconyAreaKvmPrice ? $balconyAreaKvmPrice .= " /" . $productData["balconyAreaKvmPrice"] : $balconyAreaKvmPrice = $productData["balconyAreaKvmPrice"];
                    $balconyTotalPrice ? $balconyTotalPrice .= " /" . $productData["balconyTotalPrice"] : $balconyTotalPrice = $productData["balconyTotalPrice"];
                    $terasa ? $terasa .= " /" . $productData["_EA31IY"] : $terasa = $productData["_EA31IY"];
                    $terasaPrice ? $terasaPrice .= " /" . $productData["terasaPrice"] : $terasaPrice = $productData["terasaPrice"];
                    $yardSpace ? $yardSpace .= " /" . $productData["__PCXGDE"] : $yardSpace = $productData["__PCXGDE"];
                    $yardKvmPrice ? $yardKvmPrice .= " /" . $productData["yardKvmPrice"] : $yardKvmPrice = $productData["yardKvmPrice"];
                    $yardTotalPrice ? $yardTotalPrice .= " /" . $productData["_H8WF0T"] : $yardTotalPrice = $productData["_H8WF0T"];
                    $KVM_PRICE_REPAIR ? $KVM_PRICE_REPAIR .= " /" . $productData["KVM_PRICE_REPAIR"] : $KVM_PRICE_REPAIR = $productData["KVM_PRICE_REPAIR"];
                    $FULL_PRICE_REPAIR ? $FULL_PRICE_REPAIR .= " /" . $productData["FULL_PRICE_REPAIR"] : $FULL_PRICE_REPAIR = $productData["FULL_PRICE_REPAIR"];
                    $KVM_PRICE_FURNITURE ? $KVM_PRICE_FURNITURE .= " /" . $productData["KVM_PRICE_FURNITURE"] : $KVM_PRICE_FURNITURE = $productData["KVM_PRICE_FURNITURE"];
                    $FULL_PRICE_FURNITURE ? $FULL_PRICE_FURNITURE .= " /" . $productData["KVM_PRICE"] : $FULL_PRICE_FURNITURE = $productData["KVM_PRICE"];
                    $PRODUCT_TYPE ? $PRODUCT_TYPE .= " /" . $productData["PRODUCT_TYPE"] : $PRODUCT_TYPE = $productData["PRODUCT_TYPE"];
                    $sawyisi_girebuleba_kv ? $sawyisi_girebuleba_kv .= " /" . $productData["KVM_PRICE"] : $sawyisi_girebuleba_kv = $productData["KVM_PRICE"];
                    $FLAT_PRICE ? $FLAT_PRICE .= " /" . $productData["FLAT_PRICE"] : $FLAT_PRICE = $productData["FLAT_PRICE"];
                    // $sawyisi_girebuleba ? $sawyisi_girebuleba .= " /" . $productData["FLAT_PRICE"] : $sawyisi_girebuleba = $productData["FLAT_PRICE"];
                    $opportunity = round($opportunity + $productData["PRICE"],2);
                    $livingarea_price_per = $productData["livingarea_price_per"];

                    if($productData["projEndDate"]) $projectEndDate = $productData["projEndDate"];


                    if (count($productData)) {
                        $arPush = array('PRODUCT_ID' => $productData["ID"], 'PRICE' => $productData["PRICE"], 'QUANTITY' => 1);
                        array_push($arrLoadProductRows, $arPush);
                        $prodUpdateResult = addQueueToProd($productData["ID"], $dealId);
                    }
                }
                //ახალი პროდუქტის მიბმა

                $saveRes = CCrmDeal::SaveProductRows($dealId, $arrLoadProductRows);


                //თუ ახალი პროდუქტი მიება ძველი პროდუქტებს ვათავისუფლებთ
                if ($saveRes) {
                    if (!empty($dealsProduct)) freeProduct($dealsProduct, $dealId);
                    $arrForAdd ["UF_CRM_1761658516561"] = $project;      //პროექტი
                    $arrForAdd ["UF_CRM_1766560177934"] = $korp;      //კორპუსი
                    $arrForAdd ["UF_CRM_1766736693236"] = $building;      //building
                    $arrForAdd ["UF_CRM_1761658577987"]    = $prodFLOOR;         //სართული
                    $arrForAdd ["UF_CRM_1761658559005"] = $prodNumber;      //ბინის №
                    $arrForAdd ["UF_CRM_1761658608306"] = $prodTOTAL_AREA;      //საერთო ფართი მ²
                    // $arrForAdd ["UF_CRM_1761658642424"]    = $FLAT_PRICE;         //ბინის ღირებულება
                    $arrForAdd ["UF_CRM_1761658765237"] = $LIVING_SPACE;      //საცხოვრებელი ფართი მ²
                    $arrForAdd ["UF_CRM_1702650778297"] = $balconyArea;      //აივნების ფართი მ²
                    $arrForAdd ["UF_CRM_1680533547885"] = $balconyAreaKvmPrice;      //აივნების ფართის ფასი 1მ²
                    $arrForAdd ["UF_CRM_1746181853"] = $balconyTotalPrice;         //აივნების ფართის ჯამური ღირებულება
                    $arrForAdd ["UF_CRM_1746181896"] = $terasa;         //ტერასების ფართი მ²
                    $arrForAdd ["UF_CRM_1746181940"] = $terasaPrice;         // ტერასების ფართის ჯამური ღირებულება
                    $arrForAdd ["UF_CRM_1746181990"] = $yardSpace;         //ეზოს ფართი მ²
                    $arrForAdd ["UF_CRM_1746182087"] = $yardKvmPrice;         //ეზოს ფართის ფასი 1მ²
                    $arrForAdd ["UF_CRM_1761574856574"] = $yardTotalPrice;         //ქონების მდგომარეობა
                    $arrForAdd ["UF_CRM_1745864822"] = $KVM_PRICE_REPAIR;         //რემონტის ფასი 1მ²
                    $arrForAdd ["UF_CRM_1745864938"] = $FULL_PRICE_REPAIR;         //რემონტის ღირებულება
                    $arrForAdd ["UF_CRM_1745864834"] = $KVM_PRICE_FURNITURE;         //ავეჯის ფასი 1მ²
                    $arrForAdd ["UF_CRM_1761658503260"] = $FULL_PRICE_FURNITURE;         //კვადრატული მეტრის ღირებულება
                    $arrForAdd ["UF_CRM_1761658532158"] = $PRODUCT_TYPE;         //ფართის ტიპი
                    $arrForAdd ["UF_CRM_1761658642424"] = $FLAT_PRICE;  // sawyisi girebuleba
                    $arrForAdd ["UF_CRM_1761658662573"] = $sawyisi_girebuleba_kv;  // sawyisi girebuleba kv
                    $arrForAdd ["UF_CRM_1719571190805"] = $projectEndDate;         //პროექტის დასრულების თარიღი
                    $arrForAdd ["UF_CRM_1747130151"] = DATE_Sityvierad($projectEndDate);         //პროექტის დასრულების თარიღი
                    $arrForAdd ["UF_CRM_1747130218266"] = DATE_SityvieradNewFormatEng($projectEndDate);         //პროექტის დასრულების თარიღი
                    $arrForAdd ["UF_CRM_1747130238231"] = DATE_SityvieradNewFormat($projectEndDate);         //პროექტის დასრულების თარიღი
                    $arrForAdd ["UF_CRM_1730904711123"] = $livingarea_price_per;


                    $Deal = new CCrmDeal();
                    $result = $Deal->Update($dealId, $arrForAdd);

                }
                if ($saveRes || $result) {
                    if ($saveRes && $result) {
                        $resArray["status"] = 200;
                        $resArray["message"] = "ბინა წარმატებით დაემატა";
                        $resArray["PROD_ID"] = $productData["ID"];
                        $resArray["DEAL_ID"] = $dealId;
                    } elseif ($saveRes) {
                        $resArray["status"] = 300;
                        $resArray["error"] = "error result";
                        $resArray["result"] = $result;
                        $resArray["saveRes"] = $saveRes;
                    } elseif ($result) {
                        $resArray["status"] = 300;
                        $resArray["error"] = "error saveRes";
                        $resArray["result"] = $result;
                        $resArray["saveRes"] = $saveRes;
                    } else {
                        $resArray["status"] = 300;
                        $resArray["error"] = "error 300";
                        $resArray["result"] = $result;
                        $resArray["saveRes"] = $saveRes;
                    }
                } else {
                    $resArray["status"] = 305;
                    $resArray["error"] = "error 305";
                }
            }
            else {
                $arrLoadProductRows = array();

                //მიბმული პროდუქტები
                $dealsProduct = getDealProds($dealId);

                //თუ ახალი პროდუქტი მიება ძველი პროდუქტებს ვათავისუფლებთ
                $dealsProduct = array_diff($dealsProduct, array($prodsForDeal));
                $saveRes = CCrmDeal::SaveProductRows($dealId, $arrLoadProductRows);

                if (!empty($dealsProduct)) {
                    freeProduct($dealsProduct, $dealId);
                    $resArray["status"] = 200;
                    $resArray["message"] = "პროდუქტი წარამტებით გათავისფლდა";
                } else {
                    $resArray["status"] = 402;
                    $resArray["error"] = "დილზე პროდუქტი არ არის მიბმული";
                }
                $arrForAdd ["UF_CRM_1761658516561"] = "";      //პროექტი
                $arrForAdd ["UF_CRM_1766560177934"] = "";      //კორპუსი
                $arrForAdd ["UF_CRM_1766736693236"] = "";      //building
                $arrForAdd ["UF_CRM_1761658577987"]    = "";         //სართული
                $arrForAdd ["UF_CRM_1761658608306"] = "";      //საერთო ფართი მ²
                $arrForAdd ["UF_CRM_1761658503260"] = "";      //კვ მ² ღირებულება

                $arrForAdd ["UF_CRM_1761658559005"] = "";      //ბინის №
                $arrForAdd ["UF_CRM_1693386021079"] = "";      //საერთო ფართი მ²
                // $arrForAdd ["UF_CRM_1761658642424"]    = "";         //ბინის ღირებულება
                $arrForAdd ["UF_CRM_1761658765237"] = "";      //საცხოვრებელი ფართი მ²
                $arrForAdd ["UF_CRM_1702650778297"] = "";      //აივნების ფართი მ²
                $arrForAdd ["UF_CRM_1680533547885"] = "";      //აივნების ფართის ფასი 1მ²
                $arrForAdd ["UF_CRM_1746181853"]    = "";         //აივნების ფართის ჯამური ღირებულება
                $arrForAdd ["UF_CRM_1746181896"]    = "";         //ტერასების ფართი მ²
                $arrForAdd ["UF_CRM_1746181940"]    = "";         // ტერასების ფართის ჯამური ღირებულება
                $arrForAdd ["UF_CRM_1746181990"]    = "";         //ეზოს ფართი მ²
                $arrForAdd ["UF_CRM_1746182087"]    = "";         //ეზოს ფართის ფასი 1მ²
                $arrForAdd ["UF_CRM_1746182099"]    = "";         //ეზოს ფართის ჯამური ღირებულება
                $arrForAdd ["UF_CRM_1745864822"]    = "";         //რემონტის ფასი 1მ²
                $arrForAdd ["UF_CRM_1745864938"]    = "";         //რემონტის ღირებულება
                $arrForAdd ["UF_CRM_1745864834"]    = "";         //ავეჯის ფასი 1მ²
                $arrForAdd ["UF_CRM_1745864947"]    = "";         //ავეჯის ღირებულება
                $arrForAdd ["UF_CRM_1761658532158"] = "";         //ფართის ტიპი
                $arrForAdd ["UF_CRM_1761658642424"] = "";         // sawyisi girebuleba
                $arrForAdd ["UF_CRM_1761658662573"] = "";         // sawyisi girebuleba kv
                $arrForAdd ["UF_CRM_1747130151"]    = "";
                $arrForAdd ["UF_CRM_1747130218266"] = "";
                $arrForAdd ["UF_CRM_1747130238231"] = "";
                $arrForAdd ["UF_CRM_1730904711123"] = "";
                $arrForAdd ["UF_CRM_1644569105471"] = "";         //დარჩენილი თანხა ხელშეკრულებისთვის
                $arrForAdd ["UF_CRM_1606296430035"] = "";         //პირველადი შენატანი
                $arrForAdd ["UF_CRM_1747646989137"] = "";         //პირველადი შენატანის თარიღი


                $Deal = new CCrmDeal();
                $result = $Deal->Update($dealId, $arrForAdd);
            }
            $arErrorsTmp = array();
            $wfId = CBPDocument::StartWorkflow(
                139,
                array("crm", "CCrmDocumentDeal", "DEAL_$dealId"),
                array("TargetUser" => "user_1"),
                $arErrorsTmp
            );

        }
        else {
            $resArray["status"] = 400;
            $resArray["error"] = "გარიგების ამ ეტაპზე პროდუქის შეცვლა შეზღუდულია!";
        }
    }else{
        $resArray["status"] = 400;
        $resArray["error"] = "პროდუქტი ვერ მოიძებნა!";
    }
}
else{
    $resArray["status"] = 400;
    $resArray["error"] = "გარიგება ვერ მოიძებნა!";
}




?>
<?
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);