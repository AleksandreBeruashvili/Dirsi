<?php

function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getCIBlockElementByID($ID){
    $arElements = array();
    $res = CIBlockElement::GetList(array("ID"=>"DESC"), array("ID"=>$ID), false, Array("nPageSize" => 1), Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"));
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        return $arPushs;
    }
    return $arElements;
}


function sendNotificationToQueue ($queue,$notification) {
    $queueAr = explode("|",$queue);

    $count = 1;
    foreach ($queueAr as $QueuedealID) {
        if($QueuedealID>0 && is_numeric($QueuedealID)) {
            $dealData = getDealInfoByID($QueuedealID);
            $responsible = $dealData["ASSIGNED_BY_ID"];

            $arFields = array(
                "MESSAGE_TYPE" => "S", # P - private chat, G - group chat, S - notification
                "TO_USER_ID" => $responsible,
                "FROM_USER_ID" => 1,
                "MESSAGE" => $notification." თქვენ ხართ რიგში N$count \n <a href='" . $_SERVER['HTTP_HOST'] ."/crm/deal/details/$QueuedealID/'>".$dealData["TITLE"] ."</a>",
                "AUTHOR_ID" => 1,
                "EMAIL_TEMPLATE" => "some",

                "NOTIFY_TYPE" => 4,  # 1 - confirm, 2 - notify single from, 4 - notify single
                "NOTIFY_MODULE" => "main", # module id sender (ex: xmpp, main, etc)
                "NOTIFY_EVENT" => "IM_GROUP_INVITE", # module event id for search (ex, IM_GROUP_INVITE)
                "NOTIFY_TITLE" => "title to send email", # notify title to send email
            );
            CModule::IncludeModule('im');
            CIMMessenger::Add($arFields);

            $count++;
        }
    }
}

function sendNotificationToResponsible ($dealID,$notification) {

    if($dealID>0 && is_numeric($dealID)) {
        $dealData = getDealInfoByID($dealID);
        $responsible = $dealData["ASSIGNED_BY_ID"];

        $arFields = array(
            "MESSAGE_TYPE" => "S", # P - private chat, G - group chat, S - notification
            "TO_USER_ID" => 1,
            "FROM_USER_ID" => 1,
            "MESSAGE" => $notification." \n <a href='" . $_SERVER['HTTP_HOST'] ."/crm/deal/details/$dealID/'>".$dealData["TITLE"] ."</a>",
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

function getDealInfoByID ($dealID) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID,"CHECK_PERMISSIONS" => "N"), array());

    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
}

function dealUpdate_163($dealId,$productData)
{

    $prodNumber = $productData["Number"];
    $prodFLOOR = $productData["FLOOR"];
    $prodPRODUCT_TYPE = $productData["PRODUCT_TYPE"];
    $prodTOTAL_AREA = $productData["TOTAL_AREA"];
    $arrForAdd ["UF_CRM_1761658559005"] = $prodNumber;     //ბინის N
    $arrForAdd ["UF_CRM_1761658577987"] =$prodFLOOR; //სართული
    $arrForAdd ["UF_CRM_1761658516561"] = $productData["PROJECT"];    //პროექტი
    $arrForAdd ["UF_CRM_1766560177934"] = $productData["KORPUSIS_NOMERI_XE3NX2"];//ბლოკი
    $arrForAdd ["UF_CRM_1766736693236"] = $productData["BUILDING"];//ბლოკი
    $arrForAdd ["UF_CRM_1761658503260"] = $productData["KVM_PRICE"];  //კვ/მ ფასი
    $arrForAdd ["UF_CRM_1761658532158"] = $prodPRODUCT_TYPE;      //ფართის ტიპი
    $arrForAdd ["UF_CRM_1761658608306"] = $prodTOTAL_AREA;    //საერთო ფართი
    $arrForAdd ["UF_CRM_1762867479699"] = $productData["_15MYD6"];     //სადარბაზო   
    $arrForAdd ["UF_CRM_1761658765237"]  = $productData["LIVING_SPACE"];    //საცხოვრებელი ფართი მ²   
    $arrForAdd ["UF_CRM_1761658642424"] = $productData["PRICE"];     //სადარბაზო   
    $arrForAdd ["UF_CRM_1761658662573"]  = $productData["KVM_PRICE"];    //საცხოვრებელი ფართი მ²   



    $Deal = new CCrmDeal();
    $result = $Deal->Update($dealId, $arrForAdd);
}


function getProductDataByID($ID)
{
    $arElements=array();

    if(is_numeric($ID)) {
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
    return $arElements;
}

function reservation_163($deal,$element){

    $dealID = $deal["ID"];
    $element["STATUS"] = "დაჯავშნილი";
    $element["OWNER_DEAL"] = $dealID;
    $element["DEAL_RESPONSIBLE"] = $deal["ASSIGNED_BY_ID"];
    $element["OWNER_CONTACT"] = $deal["CONTACT_ID"];
    $element["OWNER_COMPANY"] = $deal["COMPANY_ID"];
    $element["QUEUE"] = str_replace("|$dealID", "", $element["QUEUE"]);

    $el = new CIBlockElement;
    $arLoadProductArray = array(
        "PROPERTY_VALUES" => $element,
        "NAME" => $element["NAME"],
        "ACTIVE" => "Y",            // активен
    );
    $res = $el->Update($element["ID"], $arLoadProductArray);

    $arrLoadProductRows = array();
    $arPush = array('PRODUCT_ID' => $element["ID"], 'PRICE' => $element["PRICE"], 'QUANTITY' => 1);
    array_push($arrLoadProductRows, $arPush);
    $saveRes = CCrmDeal::SaveProductRows($dealID, $arrLoadProductRows);

    dealUpdate_163($deal["ID"],$element);
}
