<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    http_response_code(500);
    die(json_encode(['error' => 'iblock module not installed/loaded']));
}

function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();

    return $res["NAME"]." ".$res["LAST_NAME"];
}


function getContactName($id) {
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $id), array("ID", "FULL_NAME"));
    if($arContact = $res->Fetch()){
        return $arContact["FULL_NAME"];
    }
    return "";
}



function getNBG_inventory($date)
{
    $url="https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";

    $seb = file_get_contents($url);

    $seb = json_decode($seb);

    $seb_currency=$seb[0]->currencies[0]->rate;

    return $seb_currency;
}

function getDealProds ($dealID) {

    $prods = CCrmDeal::LoadProductRows($dealID);
    $products = [];
    foreach ($prods as $prod) {
        $arFilter = array(
            "ID" => $prod["PRODUCT_ID"]
        );
        $each = getCIBlockElementsByFilter($arFilter);
        if($each[0]["IBLOCK_SECTION_ID"]!=31) {
            $price = CPrice::GetBasePrice($prod["PRODUCT_ID"]);
            array_push($products, $each[0]);
        }
    }
    return $products;
}




function getCIBlockElementsByFilter($arFilter)
{
    $arElements = array();
    $res = CIBlockElement::GetList(array("ID"=>"ASC"), $arFilter, false, Array("nPageSize" => 99999), array());
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

function getProductProperties() {
    $iblockId = 14;

    $propertiesArr = array();

    $properties = CIBlockProperty::GetList(
        ["SORT" => "ASC"], // Sort by the "SORT" field
        ["IBLOCK_ID" => $iblockId] // Filter by the IBlock ID
    );

    while ($propFields = $properties->Fetch()) {
        $thisArr = array(
            "ID" => $propFields["ID"],
            "NAME" => $propFields["NAME"],
        );

        array_push($propertiesArr, $thisArr);
    }

    return $propertiesArr;
}


function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealByFilter ($arFilter,$arrSelect=array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), $arFilter, $arrSelect);
    
    $resArr = array();
    if($arDeal = $res->Fetch()){
        array_push($resArr,$arDeal);
    }
    return $resArr;
}   

function getDealFields($fieldName){
    $option=array();
    $rsUField = CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $fieldName));
    while($arUField = $rsUField->GetNext()) {
        $option[$arUField["VALUE"]]=$arUField["ID"];
    }

    return $option;
}


global $USER;
$currentUserId = $USER->GetID();


$filters=getCIBlockElementsByFilter(array("IBLOCK_ID"=>17,"PROPERTY_USER"=>intval($currentUserId)));

// product
$resProperties = getProductProperties();

// deal infos
$dealID = $_GET["dealid"];
$arFilter = array("ID" => $dealID);
$deal=getDealByFilter($arFilter);
$stage_ID = $deal[0]["STAGE_ID"];
$projName=$deal[0]["UF_CRM_1761658516561"];

// project name and id
$projField=getDealFields("UF_CRM_1761658516561");
$projId=$projField[$projName];

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.js"
            integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk=" crossorigin="anonymous"></script>
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">

    <!-- <link rel="stylesheet" href="./style.css"> -->
    <style>
        @font-face {
            font-family: 'MyCustomFont';
            src: url('https://81.16.249.113/custom/cutom_font.otf');
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            /* font-family: MyCustomFont; */
        }


        body {
            background: linear-gradient(90deg, rgba(255,255,255,1) 0%, rgba(120,153,186,1) 100%);
            background-attachment: fixed;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .body {
            font-family: Roboto, sans-serif;
            display: flex;
            /*background: rgb(255,255,255);*/
            /*background: linear-gradient(90deg, rgba(255,255,255,1) 0%, rgba(120,153,186,1) 100%);*/

        }

        .container {
            margin: 0 auto;
            position: relative;
            margin-left: 30px;
        }

        #filter-block{
            min-width: 250px;
            max-width: 250px;
            /* transition: all 0.5s ease; */
            display: flex;
        }

        .searchbox-container {
            left: 1px;
            width: 250px;
            height: 100%;
            background-color: #E4EBF1;
            box-shadow: 0 2px 5px 1px rgb(64 60 67 / 16%);
        }

        .searchbox-container .container {

        }
        .filter_button {
            width: 60px; /* Adjust the width as needed */
            height: 100px; /* Adjust the height as needed */
            background-color: #007bff; /* Change the background color as needed */
            text-align: center;
            display: inline-block;
            padding: 10px;
            transform: rotate(-90deg); /* Rotate the button 90 degrees counterclockwise */
            transform-origin: bottom left; /* Rotate around the bottom-left corner */
        }
        .price,
        .area {
            display: flex;
            padding: 3px;
            align-items: center;
            background: #F2F5F8;
            border: none;
            border-radius: 5px 0 0 5px;
            line-height: 22px;
            color: #30475E;
            font-size: 13px;
        }

        .form-controll {
            width: 100%;
            padding: 10px;
            cursor: pointer;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
            transition: all .2s ease;
            margin-top : 10px;
            margin-left : 10px;
            margin-bottom : 10px;
        }

        .form-controll:hover {
            border-color: rgba(81, 158, 243, .5);
        ;

        }

        .form-controll:focus {
            border: 1px solid rgba(81, 158, 243, .5);
        }

        .search-item {
            display: flex;
            width: 90%;
            margin-bottom: 10px;
            margin-left: 10px;
            margin-right: 10px;
            box-shadow: 0 2px 5px 1px rgb(64 60 67 / 16%);
            height:33.6px

        }


        .crm-entity-wrap {
    position: relative;
    padding-bottom: 1600px;
}

        .search-item1 {
            display: flex;
            width: 90%;
            margin-bottom: 10px;
            margin-left: 10px;
            margin-right: 10px;
            /* box-shadow: 0 2px 5px 1px rgb(64 60 67 / 16%); */
        }

        .search-item-plus {
            display: flex;
            width: 90%;
            margin-bottom: 10px;
            margin-left: 10px;
            margin-right: 10px;

        }

        .search-item-none {
            display: flex;
            width: 90%;
            margin-bottom: 10px;
            margin-left: 10px;
            margin-right: 10px;

        }

        .rangeCat {
            width: 100%;
            padding: 10px;
            cursor: pointer;
            border: none;
            background-color: #F2F5F8;
            outline: none;
            transition: all .2s ease;
            border-right: none;
            border-radius: 0;
        }

        .rangeCat--before {
            border-top-right-radius: 5px;
            border-bottom-right-radius: 5px;
        }

        .wrapper {
            padding-top: 20px;
        }

        .floor-block {
            display: flex;
            align-items: center;
            border-top: 1px solid transparent;
            border-bottom: 1px solid transparent;
            /* padding: 5px; */
        }

        .floor-block:hover {
            border-color: #ACBDD3;
            background: #B5C3D7;
        }

        .floor-number {
            font-size: 17px;
            color: #456687;
            width: 20px;
            font-weight: bold;
        }



        /* CSS */
        .button-19{
            appearance: button;
            background-color: #031332;
            border: solid transparent;
            border-radius: 16px;
            border-width: 0 0 4px;
            box-sizing: border-box;
            color: #FFFFFF;
            cursor: pointer;
            display: inline-block;
            font-family: din-round,sans-serif;
            font-size: 6px;
            font-weight: 500;
            letter-spacing: .8px;
            line-height: 20px;
            margin: 0;
            outline: none;
            overflow: visible;
            padding: 6px 16px;
            text-align: center;
            text-transform: uppercase;
            touch-action: manipulation;
            transform: translateZ(0);
            transition: filter .2s;
            user-select: none;
            -webkit-user-select: none;
            vertical-align: middle;
            white-space: nowrap;
            width: 50%;
            height:30px;
            display: flex;
            justify-content: center;
            margin-left: 50px;
   
        }


        #clean {
            appearance: button;
            background-color: #031332;
            border: solid transparent;
            border-radius: 16px;
            border-width: 0 0 4px;
            box-sizing: border-box;
            color: #FFFFFF;
            cursor: pointer;
            display: inline-block;
            font-family: din-round,sans-serif;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .8px;
            line-height: 20px;
            margin: 0;
            outline: none;
            overflow: visible;
            padding: 6px 16px;
            text-align: center;
            text-transform: uppercase;
            touch-action: manipulation;
            transform: translateZ(0);
            transition: filter .2s;
            user-select: none;
            -webkit-user-select: none;
            vertical-align: middle;
            white-space: nowrap;
            width: 50%;
            display: flex;
            justify-content: center;
          
            margin-left: 100px;
            margin-bottom: 6px;
            margin-top: -48px;
                    
        }

        .button-19:after, #clean:after{
            background-clip: padding-box;
            background-color: #031332;
            border: solid transparent;
            border-radius: 16px;
            border-width: 0 0 4px;
            bottom: -4px;
            content: "";
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            z-index: -1;
        }

        .button-19:main,
        .button-19:focus {
            user-select: auto;
        }

        .button-19:hover:not(:disabled) {
            filter: brightness(1.1);
            -webkit-filter: brightness(1.1);
        }

        .button-19:disabled {
            cursor: auto;
        }

        .button-19:active {
            border-width: 4px 0 0;
            background: none;
        }

        .floor-section {
            display: flex;
            align-items: start;
            margin-left: 10px;
        }

        .floor-item {
            padding: 2px;
            /* margin-left: 10px; */
            display: flex;
            justify-content: center;
            align-items: center;
            width: 43px;
            height: 43px;
            transition: all 0.5s ease;

        }


        .floor-item_hidden{
            display:none;
        }

        .floor-item__inner {
            background-color: #0564d0;
            color: #000000;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            cursor: pointer;
            width: 80%;
            height: 80%;
            display: flex;
            justify-content: center;
            align-items: center;
            outline-offset: 2px;
            border-radius: 25%;
            box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;
        }

        .floor-item:hover{
            transform: scale(1.5,1.5);
        }

        .floor-item__inner:hover {
            transform: scale(1.5,1.5);
        }

        .floor-item--active {

            /* transform: scale(1.5,1.5); */

        }

        .page-container__aside {
            width: 400px;
            height: 100%;
            position: fixed;
            background-color: #E4EBF1;
            bottom: 0;
            right: -100%;
            transition: all .5s ease;
            border: 1px solid #d4d6da;
            box-shadow: 0 2px 5px 1px rgb(64 60 67 / 16%);
            padding-left: 10px;
            padding-right: 10px;
            z-index: 1000;
        }

        .aside-content {
            height: 100%;
            overflow-x: scroll;
            position: relative;
            display: block;
        }

        .contact-block {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 5px 10px;
            border-bottom: 1px solid #d4d6da;

        }

        .phone-icon {
            width: 30px;
        }

        .photo-block {
            display: flex;
            justify-content: center;
        }

        .phone-number {
            font-size: 20px;
            font-weight: 600;
            color: #000;
            text-decoration: none;
            transition: .2s;
        }

        .phone-number:hover {
            color: #2e89ec;
        }

        .close-btn {
            display: flex;
            align-items: center;
            cursor: pointer;
            margin-left: 20px;
        }

        .close-btn:hover {
            color: #2e89ec;
        }

        .close-title {
            font-size: 12px;
            transition: .2s;
            margin-right: 5px;

        }

        .close-icon {
            font-size: 13px;
        }

        #apartment{

            background-color: #abd4f3ff;
            border-radius: 10px;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 13px 27px -5px, rgba(0, 0, 0, 0.3) 0px 8px 16px -8px;

        }

        .apartment-info-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0 20px 20px;
        }

        .apartment-info-block__item {
            font-size: 17px;
            font-weight: 700;
            line-height: 24px;
            /* display: none; */
        }

        .apartment-status-info {
            font-size: 13px;
            padding: 5px 30px 5px 20px;
            color: #fff;
            border-radius: 5px 0 0 5px;
            font-weight: bold;
        }

        .cost {
            margin-bottom: 15px;
            margin-top: 20px;
            font-size: 20px;
            white-space: nowrap;
            line-height: 24px;
            /*margin: 20px 0 20px 20px;*/
            background-color: #A3BBCC;
            border-radius: 10px;
            width: 30%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 5px;
            font-weight: bold;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 13px 27px -5px, rgba(0, 0, 0, 0.3) 0px 8px 16px -8px;
        }

        .discount-block {
            display: none;
            padding: 20px;
            border: 1px solid #d4d6da;
            border-left: 3px solid #feaa00;
            border-right: none;
        }

        .discount-label {
            width: 95px;
            height: 27px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #feaa00;
            color: #fff;
        }

        .discount-label:after {
            width: 0;
            height: 0;
            content: "";
            display: inline-block;
            border-top: 13px solid transparent;
            border-left: 12px solid #feaa00;
            border-bottom: 14px solid transparent;
            position: absolute;
            right: calc(100% - 130px);
        }

        .discount-title {
            font-size: 12px;
        }

        .active {
            right: 0%;
            transition: all .5s ease;
        }


        .free {
            background-color: #63cba5;
        }
        .NFSM {
            background-color: #F46A53;
        }
        .NFSJ {
            background-color: #FF9A7B;
        }
        .NFS {
            background-color: #ab04ff;
        }
        .broker {
            background-color: #ab04ff;
        }
        .reservation-form {
            background-color: #fcd93d;
        }
        .queue-form {
            background-color: #ffb400;
        }
        .sold {
            background-color: #ff3b3b;
        }
        .gasacemi {
            background-color: #0564d0;
        }
        .floor-item__inner--status {
            transform: scale(.8, .8);
            opacity: .5;
            width: 25px;
            height: 25px;
            transition: all .2s ease;
            pointer-events: none;

        }
        .apartment-about-ul {
            padding: 15px;
            font-size: 14px;
        }
        .apartment-about-li {
            list-style-type: none;
            font-size: 12px;
            font-weight: bold;
            color: #000;
            margin-bottom: 3px;
        }
        .apartment-about-li span {
            font-weight: normal;
        }
        .aside-hide {
            position: absolute;
            display: flex;
            width: 36px;
            height: 36px;
            border: 1px solid #d4d6da;
            border-radius: 50%;
            background: #fff;
            top: 24%;
            left: -16px;
            cursor: pointer;
            z-index: 12;
        }
        .aside-hide__icon {
            color: #519ef3;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }
        .add-btn-block {
            padding: 3px;
        }
        .btn-primary,
        .add-btn {
            width: 100%;
            font-weight: 400;
            text-align: center;
            cursor: pointer;
            border: 1px solid transparent;
            padding: 6px 12px;
            font-size: 15px;
            line-height: 1.4;
            transition: all .2s ease;
            outline: 0;
            border-radius: 5px;
            color: #fff;
            background-color: #ff3b3b;
            border-color: #ff3b3b;
            opacity: 0.8;
        }
        .btn-primary:hover,
        .add-btn:hover {
            opacity: 1;
        }
        .disabled--btn {
            background-color: lightgray;
            border-color: lightgray;
            cursor: auto;
            opacity: 1;
        }

        .disabled--btn:hover {
            opacity: 1;
        }


        .empty--status {
            background-color: red;
        }


        #newDropdown {
            width: 95%;
            padding: 4px;
        }
        .dropdown-li {
            font-size: 13px;
        }

        .new-drop-down-hide-block {
            display: flex;
            justify-content: center;
        }

        .new-drop-down-hide {
            display: flex;
            width: 36px;
            height: 36px;
            border: 1px solid #d4d6da;
            border-radius: 50%;
            background: #fff;
            top: 24%;
            left: -16px;
            cursor: pointer;
            z-index: 12;
        }

        .new-drop-down-hide__icon {
            color: #519ef3;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            transform: rotate(0deg);
            transition: 0.3s;
        }

        .new-drop-down {
            visibility: hidden;
            opacity: 0;
            height: 0;
            transition: visibility 0s linear 300ms, opacity 300ms, height 0s linear 300ms;
        }

        .new-drop-down-active {
            visibility: visible;
            opacity: 1;
            height: 100%;
            transition: visibility 0s linear 0s, opacity 300ms, height 0s linear 300ms;
        }
        .rotate {
            transform: rotate(180deg);
        }
        .detail-img {
            cursor: pointer;
            transition: 0.2s;
        }
        .detail-img:hover {
            transform: scale(1.1);
            opacity: 0.4;
        }
        /* The Modal (background) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 999; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width ჩბ*/
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgb(0,0,0); /* Fallback color */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        /* Modal Content/Box */
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto; /* 15% from the top and centered */
            padding: 20px;
            border: 1px solid #888;
            width: 80%; /* Could be more or less, depending on screen size */
        }

        /* The Close Button */
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-img {
            width: 100%;
   
        }
        .custom-dropdownCat {
            width: 90%;
            margin-right: 8px;
            position: relative;
            /* display: inline-block; */
            flex-direction: column;
            display: flex;
            margin-left : 10px;
            margin-bottom : 8px;
        }

        .multiple_selectCat {
            display: none;
            width: 100%;
            overflow: auto;
            position: absolute;
            background-color: #fff;
            border: 1px solid #ccc;
            max-height: 500px;
            z-index: 1;
            border-bottom-right-radius: 5px;
            border-bottom-left-radius: 5px;
        }

        ::placeholder {
            color: #30475E;
            opacity: 1; /* Firefox */
        }

        ::-ms-input-placeholder { /* Edge 12-18 */
            color: #30475E;
        }

        .multipleMainLabelCat {
            width: 100%;
            padding: 3px;
            cursor: pointer;
            background-color: #F2F5F8;
            border: 1px solid #F2F5F8;
            color: #30475E;
            border-radius: 5px;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px;
            font-size:13px

        }
        .multipleLabelCat {
            cursor: pointer;
            border: none;
            padding-left: 5px;
            display: flex;
        }

        .multiple_CheckboxCat {
            margin-right: 10px;
        }

        .frozen {
            flex: 0 0 auto;
            position: fixed;
            width: 28px;
            height: 100vh;
            cursor: pointer;
        }

        .frozForFilter{
            flex: 0 0 auto;
            position: fixed;
            /* width: 28px;
            height: 100vh; */
            cursor: pointer;
            z-index: 1000;
        }

        .show-filter{
            position: absolute;
            display: flex;
            width: 41px;
            height: 135px;
            border-radius: 4%;
            border-top-right-radius: 20px;
            border-bottom-right-radius: 20px;
            background: #d60c02;
            top: 24%;
            left: 1px;
            cursor: pointer;
            z-index: 5000;
            border-right: 6px solid #d60c02;

        }

        .excelbutt {
            border: none;
            padding: 5px;
            background-color: #30475E;
            color: #F5F5F5;
            cursor: pointer;
            width: 50px;
        }

        .show-filter a{
            color: white;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .detailbutt {
            border: none;
            padding: 5px;
            background-color: #30475E;
            color: #F5F5F5;
            cursor: pointer;
            width: 50px;
            border-radius: 10px;
        }

        .close-filter{
            display: flex;
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 0 100% 100% 0;
            background: white;
            cursor: pointer;
            z-index: 12;
        }

        .close-filter a{
            color: #519ef3;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .hide{
            /*display: none;*/
            transform: translateX(-350px);
        }

        .hide_butt{

            display: none;

        }

        .hide_prods{

            transform: translateX(-250px);

        }

        #productsContainer{

            transition: all 0.5s ease;

        }

        /*#filterBtn{*/
        /*    background-color: #456687;*/
        /*    border-color: #456687;*/
        /*}*/

        .excelbuttns{
            transition: all 0.5s ease;
        }

        .excelbuttns:hover{
            opacity: 0.8;
        }

        .container_custom {
            display: block;
            position: relative;
            padding-left: 35px;
            margin-bottom: 12px;
            cursor: pointer;
            font-size: 15px;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* Hide the browser's default checkbox */
        .container_custom input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        /* Create a custom checkbox */
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 25px;
            width: 25px;
            background-color: #eee;
        }

        /* On mouse-over, add a grey background color */
        .container_custom:hover input ~ .checkmark {
            background-color: #ccc;
        }

        /* When the checkbox is checked, add a blue background */
        .container_custom input:checked ~ .checkmark {
            background-color: #2196F3;
        }

        /* Create the checkmark/indicator (hidden when not checked) */
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }

        /* Show the checkmark when checked */
        .container_custom input:checked ~ .checkmark:after {
            display: block;
        }

        /* Style the checkmark/indicator */
        .container_custom .checkmark:after {
            left: 9px;
            top: 5px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 3px 3px 0;
            -webkit-transform: rotate(45deg);
            -ms-transform: rotate(45deg);
            transform: rotate(45deg);
        }

        .top_header{
            width: 100%;
            height: 50px;
            padding-top: 10px;
            display: flex;
        }

        .slider {
            -webkit-appearance: none;
            width: 100%;
            height: 15px;
            border-radius: 5px;
            background: #ababab;
            outline: none;
            opacity: 0.7;
            -webkit-transition: .2s;
            transition: opacity .2s;
        }

        .slider:hover {
            opacity: 1;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #8aabf6;
            cursor: pointer;
        }

        .slider::-moz-range-thumb {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #8aabf6;
            cursor: pointer;
        }

        .dropbtn_new {
            background-color: #E4EBF1;
            color: #30475E;
            height: 30px;
            font-size: 12px;
            padding-left: 5px;
            padding-right: 5px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            margin-left: 5px;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            /* display: none !important;
            visibility: hidden; */

        }

        .dropdown_new {
            position: relative;
            display: inline-block;
        }

        .dropdown-content_new {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            margin-left: 5px;
            padding-left: 5px;
            padding-right: 5px;

        }

        .dropdown-content_new div {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            cursor: pointer;
        }

        .dropdown-content_new div:hover {background-color: #f1f1f1}

        .dropdown_new:hover .dropdown-content_new {
            display: block;
        }

        .dropdown_new:hover .dropbtn_new {
            background-color: #bcc3c8;
        }

        .hide-filters .custom-dropdownCat {
            display: none;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f1f1f1;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }

        /* Стили для кнопки дропдауна */
        .dropdown-btn {
            position: relative;
            cursor: pointer;
            border-radius: 50%;
            border:none;
        }

        /* Стили для расположения дропдауна ниже кнопки */
        .dropdown-content.left-aligned {
            left: 0;
            top: calc(100% + 5px); /* 5px - отступ между кнопкой и дропдауном */
        }

        .dropdown-content a {
            color: black;
            padding: 3px 16px;
            text-decoration: none;
            display: block;
            border-bottom: solid black 1px;
        }

        .dropdown-content a:hover {
            background-color: #ddd;
        }

        .show {
            display: block;
        }

        .top_header{
            width: 100%;
            height: 50px;
            padding-top: 10px;
            display: flex;
        }

        .slider {
            -webkit-appearance: none;
            width: 100%;
            height: 15px;
            border-radius: 5px;
            background: #ababab;
            outline: none;
            opacity: 0.7;
            -webkit-transition: .2s;
            transition: opacity .2s;
        }

        .slider:hover {
            opacity: 1;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #031332;
            cursor: pointer;
        }

        .slider::-moz-range-thumb {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #031332;
            cursor: pointer;
        }

        .dropbtn_new {
            background-color: #E4EBF1;
            color: #30475E;
            height: 30px;
            font-size: 12px;
            padding-left: 5px;
            padding-right: 5px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            margin-left: 5px;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);

        }

        .dropdown_new {
            position: relative;
            display: inline-block;
        }

        .dropdown-content_new {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            margin-left: 5px;
            padding-left: 5px;
            padding-right: 5px;

        }

        .dropdown-content_new div {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            cursor: pointer;
        }

        .dropdown-content_new div:hover {background-color: #f1f1f1}

        .dropdown_new:hover .dropdown-content_new {
            display: block;
        }

        .dropdown_new:hover .dropbtn_new {
            background-color: #bcc3c8;
        }

            /* Стили для расположения дропдауна ниже кнопки */
        .dropdown-content.left-aligned {
            left: 0;
            top: calc(100% + 5px); /* 5px - отступ между кнопкой и дропдауном */
            height: 400px;
            overflow-y: auto;
        }

        .input-container {
            display: flex;
            /* gap: 10px; input-ებს შორის სივრცე */
        }

        .multipleMainLabelCat1{
            width: 100%;
            /* padding: 8px; */
            cursor: pointer;
            background-color: #F2F5F8;
            border: 1px solid #F2F5F8;
            color: #30475E;
            border-radius: 5px;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px;

        }

        .new_block{
            border-left:1px solid;
        }


        .sticky-horizontal {
            position: absolute;
            
            background-color: lightgray;
            cursor: pointer;
            z-index: 1000;
         
         }

         .sticky-horizontalDiv {
            /* position: absolute; */

            cursor: pointer;
            z-index: 1000;
            /* left:0; */
           margin-left: 20px;
           background :#e4ebf1;
           
      
         }


         .photo-block {
            display: flex;
            justify-content: center;
        }


        .button-19 {
            display: flex;
            align-items: center;
            gap: 8px; /* დაშორება ტექსტსა და აიქონს შორის */
            padding: 10px 16px;
            border: none;
            /* background-color: #007bff; */
            color: white;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
        }


        .button-19 {
            font-size: 14px;
        }


        .gtranslate_wrapper {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            background: white;
            padding: 5px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }



    </style>
    <script src="https://kit.fontawesome.com/439d7afbb0.js" crossorigin="anonymous"></script>

</head>

<div class="body" style="position:relative">
    <div class="frozen hide_butt" id="frozen" onclick="filterPopup()">
        <div id="show-filter" class="show-filter">
            <a><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>
    <div id="filter-block" class="sticky-horizontalDiv" >
        <div class="searchbox-container " id="filter">

            <h3 class="filter-title"  style="margin: 20px; margin-top: 10px; margin-bottom: -5px; font-size: 12px; font-weight:bold; ">ძირითადი ფილტრი</h3>
            <div class="customfilters" style="display: flex;" id="projectDropdownCatdis_cat">
                <div class="custom-dropdownCat gbButton" style="margin-top: 10px">
                    <select name="projectDropdownCat" id="projectDropdownCat" class="multipleMainLabelCat multipleMainLabelCat1">
                        <option value="">პროექტი</option>
                    </select>
                </div>
            </div>

            <div class="customfilters" style="display: flex;" id="statusDropdownCatdis_cat">
                <div class="custom-dropdownCat" id="CorpsDropdownCat">
                    <div class=" CorpsDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('CorpsDropdownCatSelect')" >ბლოკი</div>
                    <div class="multiple_selectCat" id="CorpsDropdownCatSelect">
                    </div>
                </div>
            </div>

            <!--gbe type-->
            <div class="customfilters" style="display: flex;" id="typeDropdownCatdis_cat">

                <div class="custom-dropdownCat" id="typeDropdownCat">
                    <div class="typeDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('typeDropdownCatSelect')">ფართის ტიპი</div>
                    <div class="multiple_selectCat" id="typeDropdownCatSelect">
                    </div>
                </div>
            </div>

            <div class="custom-dropdownCat" id="conditionDropdownCat">
                <div class="conditionDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('conditionDropdownCatSelect')">კონდიცია</div>
                <div class="multiple_selectCat" id="conditionDropdownCatSelect">
                </div>
            </div>
            <!--gbe status-->
            <div class="customfilters" style="display: flex;" id="statusDropdownCatdis_cat">

                <div class="custom-dropdownCat" id="statusDropdownCat">
                    <div class=" statusDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('statusDropdownCatSelect')" >სტატუსი</div>
                    <div class="multiple_selectCat" id="statusDropdownCatSelect">
                    </div>
                </div>
            </div>
            <!--gbe block-->

            <div class="customfilters" style="display: flex;" id="numer_range">


                <div class="custom-dropdownCat">
                  <div class="area" style="background-color:#031332; color: white;">ბინის ნომერი</div>
                    <div class="input-container" >
                    <input type="text" class="multipleMainLabelCat" id="startNumer" class="rangeCat" placeholder="დან">
                    <input type="text" class="multipleMainLabelCat" id="endNumer" class="rangeCat rangeCat--before" placeholder="მდე">
                </div>
                </div>

            </div>



            <div class="customfilters" style="display: flex;" id="startSqrdis_cat">


                <div class="custom-dropdownCat">
                  <div class="area" style="background-color:#031332; color:white;">მ²</div>
                    <div class="input-container" >
                    <input type="text" class="multipleMainLabelCat" id="startSqr" class="rangeCat" placeholder="დან">
                    <input type="text" class="multipleMainLabelCat" id="endSqr" class="rangeCat rangeCat--before" placeholder="მდე">
                </div>
                </div>
 
            </div>



            <h3 class="filter-title"  style="margin: 20px; margin-top: 10px; margin-bottom: 5px;font-size: 12px; margin-bottom: 5px; font-weight:bold; ">დამატებითი ფილტრი</h3>

            <!--gbe block-->



            <!--gbe side-->
            <div class="customfilters" style="display: flex;" id="sideDropdownCatdis_cat">

                <div class="custom-dropdownCat" id="sideDropdownCat">
                    <div class="sideDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('sideDropdownCatSelect')">ხედი/მხარე</div>
                    <div class="multiple_selectCat" id="sideDropdownCatSelect">
                    </div>
                </div>
                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('sideDropdownCatdis_cat')"></i>
                </div>
            </div>
            <!--gbe bedroom-->
            <div class="customfilters" style="display: flex;" id="bedroomDropdownCatdis_cat">

                <div class="custom-dropdownCat" id="bedroomDropdownCat">
                    <div class="bedroomDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('bedroomDropdownCatSelect')">საძინებლები</div>
                    <div class="multiple_selectCat" id="bedroomDropdownCatSelect">
                    </div>
                </div>
                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('bedroomDropdownCatdis_cat')"></i>
                </div>
            </div>
            <!--gbe floor-->
            <div class="customfilters" style="display: flex;" id="floorDropdownCatdis_cat">

                <div class="custom-dropdownCat" id="floorDropdownCat">
                    <div class="floorDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('floorDropdownCatSelect')">სართული</div>
                    <div class="multiple_selectCat" id="floorDropdownCatSelect">
                    </div>
                </div>
                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('floorDropdownCatdis_cat')"></i>
                </div>
            </div>
            <!--gbe floor-->
            <div class="customfilters" style="display: flex;" id="brokerDropdownCatdis_cat">

                <div class="custom-dropdownCat" id="brokerDropdownCat">
                    <div class="brokerDropdownCatLabel multipleMainLabelCat" onclick="dropdownToogleCat('brokerDropdownCatSelect')">ბროკერი</div>
                    <div class="multiple_selectCat" id="brokerDropdownCatSelect">
                    </div>
                </div>
                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('brokerDropdownCatdis_cat')"></i>
                </div>
            </div>


            <div class="customfilters" style="display: flex;" id="CadastralNumberdis_cat">

                <div class="custom-dropdownCat">
                    <input type="text" class="multipleMainLabelCat" id="CadastralNumber" placeholder="საკადასტრო">
                </div>

                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('CadastralNumberdis_cat')"></i>
                </div>
            </div>

            <div class="customfilters" style="display: flex;" id="startPricedis_cat">

                <div class="custom-dropdownCat">
                  <div class="price" style="background-color:#031332; color: white;">$</div>
                    <div class="input-container" >
                    <input type="text" class="multipleMainLabelCat" id="startPrice" class="rangeCat" placeholder="დან">
                    <input type="text" class="multipleMainLabelCat" id="endPrice" class="rangeCat rangeCat--before" placeholder="მდე">
                </div>
                </div>
 



                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('startPricedis_cat')"></i>
                </div>
            </div>

            <div class="customfilters" style="display: flex;" id="startPriceKvmdis_cat">
                
                <div class="custom-dropdownCat">
                  <div class="area" style="background-color:#031332; color: white;">$მ²</div>
                    <div class="input-container" >
                    <input type="text" class="multipleMainLabelCat" id="startPriceKvm" class="rangeCat" placeholder="დან">
                    <input type="text" class="multipleMainLabelCat" id="endPriceKvm" class="rangeCat rangeCat--before" placeholder="მდე">
                </div>
                </div>


                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('startPriceKvmdis_cat')"></i>
                </div>
            </div>


            <div class="customfilters" style="display: flex;" id="sart_range">


                <div class="custom-dropdownCat">
                  <div class="area" style="background-color:#031332; color: white;">სართული</div>
                    <div class="input-container" >
                    <input type="text" class="multipleMainLabelCat" id="startSartul" class="rangeCat" placeholder="დან">
                    <input type="text" class="multipleMainLabelCat" id="endSartul" class="rangeCat rangeCat--before" placeholder="მდე">
                </div>
                </div>


                <div  style="display: flex;align-items: center;justify-content: center;margin-right: 10px;">
                    <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('sart_range')"></i>
                </div>
            </div>
            <div class="search-item-plus">
                <div style="display: inline-block; position: relative;">
                    <button id="addFiltersBtn" class="dropdown-btn" style="margin-right: 10px;height: 46px;width: 46px; background: #031332;" type="button" onclick="toggleDropdown('filtersDropdown')"><i class="fa-solid fa-plus" style="color:white"></i></button>
                    <div id="filtersDropdown" class="dropdown-content left-aligned">
                        <input type="text" id="filterInput" onkeyup="filterTags()" placeholder="ძიება..." style="width: 100%; padding: 10px;">
                        <a href="#" id="CorpsDropdownCatdis_cat_x" onclick="showFilter('CorpsDropdownCatdis_cat')">ბლოკი</a>
                        <a href="#" id="bedroomDropdownCatdis_cat_x" onclick="showFilter('bedroomDropdownCatdis_cat')">საძინებლები</a>
                        <a href="#" id="floorDropdownCatdis_cat_x" onclick="showFilter('floorDropdownCatdis_cat')">სართული</a>
                        <a href="#" id="CadastralNumberdis_cat_x" onclick="showFilter('CadastralNumberdis_cat')">საკადასტრო</a>
                        <a href="#" id="startPricedis_cat_x" onclick="showFilter('startPricedis_cat')">სრული ფასი</a>
                        <a href="#" id="startPriceKvmdis_cat_x" onclick="showFilter('startPriceKvmdis_cat')">მ² ფასი</a>
                        <a href="#" id="sart_range_x" onclick="showFilter('sart_range')">სართული Range</a>
                    </div>
                </div>
            </div>

            <button type="button" class="btnColors" style="font-size: 14px;color: white;" id="clean">გასუფთავება</button>



            <div class="search-item excelbuttns"  style="height: 35px; display: none;">
                <div style="display: flex;width: 100%;height: 100%;cursor: pointer;" onclick="generateExcel()">
                    <div style="width: 80%;display: flex;background-color: #BDC9DB;border-radius: 5px 0px 0px 5px;">
                        <div style="width: 25%;display: flex;align-items: center;justify-content: center;color: #2A3D51;">
                            <i class="fa-regular fa-file-excel fa-xl"></i>
                        </div>
                        <div style="width: 75%;display: flex;align-items: center;justify-content: center;color: #2A3D51;font-size: 14px;">ექსელის დაგენერირება</div>
                    </div>
                    <div style="width: 20%;display: flex;justify-content: center;align-items: center;color: #F2F5F8;background-color: #031332;border-radius: 0px 5px 5px 0px;">
                        <i class="fa-solid fa-arrow-right fa-xl"></i>
                    </div>
                </div>
            </div>
            <div class="search-item excelbuttns"  style="height: 35px; display: none;">
                <div style="display: flex;width: 100%;height: 100%;cursor: pointer;" onclick="generateExceldetails()">
                    <div style="width: 80%;display: flex;background-color: #BDC9DB;border-radius: 5px 0px 0px 5px;">
                        <div style="width: 25%;display: flex;align-items: center;justify-content: center;color: #2A3D51;">
                            <i class="fa-regular fa-file-excel fa-xl"></i>
                        </div>
                        <div style="width: 75%;display: flex;align-items: center;justify-content: center;color: #2A3D51;font-size: 14px;">დეტალების დაგენერირება</div>
                    </div>
                    <div style="width: 20%;display: flex;justify-content: center;align-items: center;color: #F2F5F8;background-color: #031332;border-radius: 0px 5px 5px 0px;">
                        <i class="fa-solid fa-arrow-right fa-xl"></i>
                    </div>
                </div>
            </div>
            <div class="search-item1">
                <a id="filterBtn" class="button-19" style="margin-left: 50px; color: white;">ძებნა</a>
            
            </div>
        </div>
    </div>
    <div style="height: 100%;display: flex;flex-direction: column;">
        <div style="height: 30%;display: flex;justify-content: center;align-items: center;">
            <div id="close-filter" class="close-filter" style="margin-top: 43px;z-index: 1001;">
                <a><i class="fa-solid fa-chevron-left"></i></a>
            </div>
        </div>
        <div style="height: 70%;"></div>
    </div>
    <div class="container" id="productsContainer" style="margin-left:35px">
        <div class="top_header">
            <!-- display none -->
            <div style="width: 200px;margin-right: 5px; display: none; ">
                <div style="width: 100%">
                    <input type="range" min="1" max="4" value="2" class="slider" id="myRange">
                </div>
                <div style="width: 100%;display: flex;">
                    <div style="width: 25%;display: flex;justify-content: center;">50%</div>
                    <div style="width: 25%;display: flex;justify-content: center;">100%</div>
                    <div style="width: 25%;display: flex;justify-content: center;">125%</div>
                    <div style="width: 25%;display: flex;justify-content: center;">150%</div>
                </div>

            </div>
            <!-- display none -->
            <div style="display:flex;">
                <div style="display: flex;">
                    <div style="background-color: #63cba5;width: 15px;height: 15px;border-radius: 5px;margin-right: 5px;margin-left: 5px;margin-top: 8px;"></div>
                    <div style="font-size: 9px;height: 30px;display: flex;align-items: center;">თავისუფალი</div>
                </div>
                <div style="display: flex;">
                    <div style="background-color: #8e3a7d;width: 15px;height: 15px;border-radius: 5px;margin-right: 5px;margin-left: 5px; margin-top: 8px;"></div>
                    <div style="font-size: 9px;height: 30px;display: flex;align-items: center;">Not for sale</div>
                </div>
                <div style="display: flex;">
                    <div style="background-color: #d60c02;width: 15px;height: 15px;border-radius: 5px;margin-right: 5px;margin-left: 5px; margin-top: 8px;"></div>
                    <div style="font-size: 9px;height: 30px;display: flex;align-items: center;">გაყიდული</div>
                </div>
                <div style="display: flex;">
                    <div style="background-color: #0564d0; width: 15px;height: 15px;border-radius: 5px;margin-right: 5px;margin-left: 5px; margin-top: 8px;"></div>
                    <div style="font-size: 9px;height: 30px;display: flex;align-items: center;">ჯავშნის რიგი</div>
                </div>
                <div style="display: flex;">
                    <div style="background-color: #fcd93d;width: 15px;height: 15px;border-radius: 5px;margin-right: 5px;margin-left: 5px; margin-top: 8px;"></div>
                    <div style="font-size: 9px;height: 30px;display: flex;align-items: center;">დაჯავშნილი</div>
                </div>
            </div>
            <div >
                <div class="dropdown_new">
                    <button class="dropbtn_new" style="display: none;"> ჩვენება </button>
                    <div class="dropdown-content_new">
                        <div onclick="getData('','deafult')" >Default</div>
                        <div onclick="getData(15,'price')" >Price</div>
                        <div onclick="getData(15,'area')" >Area</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="wrapper" id="contentWrapper"></div>
    </div>
    <div class="page-container__aside">
        <div class="aside-hide" id="closeBtn">
            <a class="aside-hide__icon">></a>
        </div>
        <div class="aside-content">
            <div class="apartment-info-block-container" id="apartmentInfoBlockContainer">
                <div class="apartment-info-block" style="margin-top: 0px">
                    <p class="apartment-info-block__item" >
                    <span  > </span>
                </p>
                    <p class="apartment-status-info" id="apartmentStatusInfo"></p>
                </div>


                <div class="photo-block" id="photoBlock" style="    margin-bottom: 10px ; margin-left: -20px; margin-top: -25px;">
                <img src="" alt="img" style="width: 200px;" id="detailImg" class="detail-img" onclick="openModal()">
                <div id="myModal" class="modal">
                    <div class="modal-content" style="width: 80%;">
                        <span class="close">x</span>
                        <div id="demo" class="carousel slide">
                            <div class="carousel-indicators">
                                <button type="button" data-bs-target="#demo" data-bs-slide-to="0" class="active"></button>
                                <button type="button" data-bs-target="#demo" data-bs-slide-to="1"></button>
                                <button type="button" data-bs-target="#demo" data-bs-slide-to="2"></button>
                                <button type="button" data-bs-target="#demo" data-bs-slide-to="3"></button>
                            </div>
                            <div class="carousel-inner">
        
                                <div class="carousel-item active">
                                    <img src="#" alt="alt" id="detailImg_3d_2d_carousel" class="d-block" style="width:100%">
                                </div>
                                <div class="carousel-item">
                                    <img src="#" alt="alt" id="detailImg_3d_carousel" class="d-block" style="width:100%">
                                </div>
                
                                <div class="carousel-item">
                                    <img src="#" alt="alt" id="detailImg_carousel" class="d-block" style="width:100%;">
                                </div>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#demo"  data-bs-slide="prev">
                                <span style="background-color: black;" class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#demo" data-bs-slide="next">
                                <span style="background-color: black;" class="carousel-control-next-icon"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                <div class="apartment-info-cost">
                    <div class="discount-block">
                        <div class="discount-label">
                            <span class="discount-title">% ფასდაკლება</span>
                        </div>
                    </div>
                </div>
            </div>
            <div id="calculator" style="padding-bottom: 6px"></div>
            <div id="offer"></div>
            <div id="offerEng" style="padding-top: 6px;margin-bottom: 6px;"></div>

            <ul class="apartment-about-ul" id="apartment" name="street" style="padding-bottom: 0px;">
            </ul>
            <div class="add-btn-block" >
                <button id="addBtn" class="button-19">დამატება</button>
            </div>

            <div class="add-btn-block" >
                <button id="delBtn" class="button-19">წაშლა</button>
            </div>
        </div>

    </div>
</div>


<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
<script src="https://kit.fontawesome.com/439d7afbb0.js" crossorigin="anonymous"></script>

<link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
        integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
        crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"
        integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"
        integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM"
        crossorigin="anonymous"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">



<script>
    var data;
    var APARTMENTS;
    let FIELDS;
    let FILTER = {};
    let GENERAL_FILTER;
    let dealId = 0;
    let userID_cat = <? echo $USER->GetID(); ?>;
    let HAS_PRODUCT;
    let STAGE;
    let APARTMENT_INFO;
    let user_id=<?echo json_encode($currentUserId);?>;
    let filters_catalog=<?echo json_encode($filters);?>;
    dealProject=<?echo json_encode($dealProject);?>;
    let resProperties = <?echo json_encode($resProperties);?>;
    let stage_ID = <?echo json_encode($stage_ID);?>;
    var slider = document.getElementById("myRange");
  





    pathname = window.location.pathname.split("/");
    if (pathname[2] == "deal") {
        dealId = (pathname[4] == undefined) ? (0) : (pathname[4]);
    }else{
        document.getElementById('addBtn').style.display="none";
        document.getElementById('delBtn').style.display="none";
    }

    

    drawAllFilter();


    // not being used bc slider is hidden
    slider.oninput = function() {
        // console.log(this.value);

        var allfloors=document.getElementsByClassName('floor-item');

        for(var p=0;p<allfloors.length;p++){
            // console.log(allfloors[p]);
            if(this.value==1){
                allfloors[p].style.width='33px';
                allfloors[p].style.height='33px';
            }else if (this.value==2){
                allfloors[p].style.width='43px';
                allfloors[p].style.height='43px';
            }else if (this.value==3){
                allfloors[p].style.width='53px';
                allfloors[p].style.height='53px';
            }else if(this.value==4){
                allfloors[p].style.width='63px';
                allfloors[p].style.height='63px';
            }
        }
    }
    // end not being used bc slider is hidden


    // colors the ones which are not displayed in the filter
    if(filters_catalog){
        
        if(filters_catalog[0]){
            var filterids=filters_catalog[0]["FIELDS"];
        }
        
        if(filterids) {
            var filters_search = document.getElementsByClassName('customfilters');
            // console.log(filters_search);
            for (var k = 0; k < filters_search.length; k++) {

                if (!filterids.includes(filters_search[k].id)) {

                    var elementright = document.getElementById(filters_search[k].id);

                    elementright.style.display = 'none';

                    var elementlink = document.getElementById(filters_search[k].id + '_x')
                    if(elementlink)  elementlink.style.backgroundColor = '#fdd4d4';


                }

            }
        }
    }
    // end colors the ones which are not displayed in the filter


    // ---------------------------FUNCTIONS---------------------------------
    function closeFilter(filterId) {
        var filter = document.getElementById(filterId);
        var filter_link = document.getElementById(filterId+'_x');
        filter_link.style.backgroundColor="#fdd4d4";
        filter.style.display = "none";

        clearInputsById(filterId);
        uncheckCheckboxesById(filterId+'dropSelect');

        savefilter();
    }

    function uncheckCheckboxesById(elementId) {
        // Находим элемент по ID
        const container = document.getElementById(elementId);

        if (container) {
            // Получаем все checkbox внутри найденного элемента
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');

            // Снимаем отметку с каждого checkbox
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        } else {
            console.error(`Элемент с id="${elementId}" не найден.`);
        }
    }

    function clearInputsById(elementId) {
        // Находим элемент по ID
        const container = document.getElementById(elementId);

        if (container) {
            // Получаем все input внутри найденного элемента
            const inputs = container.querySelectorAll('input');

            // Очищаем каждый input
            inputs.forEach(input => {
                input.value = '';
            });

        } else {
            console.error(`Элемент с id="${elementId}" не найден.`);
        }
    }



    // A function to close the dropdown when clicking outside its area.
    window.onclick = function(event) {
        if (!event.target.matches('.dropdown-btn')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    // A function for showing and hiding the dropdown with filters.
    function toggleDropdown(dropdownId) {
        var dropdown = document.getElementById(dropdownId);
        dropdown.classList.toggle("show");
    }


    function showFilter(filterId) {
        var filter = document.getElementById(filterId);
        var filter_link = document.getElementById(filterId+'_x');
        var dropdown = document.getElementById("filtersDropdown");

        filter_link.style.backgroundColor="#f1f1f1"
        filter.style.display = "flex";

        savefilter();

        dropdown.classList.remove("show");
    }


    // not using this at all
    function savefilter(){

        var viziblefilters=[];

        var filters=document.getElementsByClassName('customfilters');

        for (var k=0;k<filters.length;k++){

            if(filters[k].style.display=='flex'){
                viziblefilters.push(filters[k].id);
            }

        }

        var savingJson = {
            "filters": viziblefilters,
            "user_id": user_id,
            "type":"catalog"
        };

        post_fetch(`${location.origin}/rest/local/api/savefilterchanges/index.php`, savingJson)
            .then(data => {
                return data.json();
            })
            .then(data => {

            })
            .catch(error => {
                console.log(error);
            });

    }
    // end not using this at all


    async function post_fetch(url, data = {}) {
        const response = await fetch(url, {
            method: 'POST',
            mode: 'no-cors',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            redirect: 'follow',
            referrerPolicy: 'no-referrer',
            body: JSON.stringify(data)
        });
        return response;
    }

    async function generateExceldetails() {
        var maininfo = document.getElementById("contentWrapper");
        var workbook = new ExcelJS.Workbook();
        var worksheet = workbook.addWorksheet("Лист 1");

        var count_price=0;

        for (var columnIndex = 1; columnIndex <= 15; columnIndex++) {
            worksheet.getColumn(columnIndex).width = 15;
        }

        var projname=document.getElementById('projectDropdownCat').value;

        if(projname){
            if(projname=="15"){
                projname="Anagi";
            }
        }

        var rowDatamain = ["ნომერი","სართული","ბლოკი","ტიპი","შიდა ფართი","სტატუსი","მთლიანი ღირებულება","ხედი"," კვ.მ $","პროექტი","საძინებლების რაოდენობა"
        ];

        worksheet.addRow(rowDatamain);

        var count = 20;

        for (var i = 0; i < maininfo.childElementCount; i++) {
            var row = maininfo.children[i].children[1];

            for (var d = 0; d < row.childElementCount; d++) {
                var cellValue = "";

                var rowData = [];

                var statusi = row.children[d].children[0]?.getAttribute("data-status");

                if (statusi) {
                    if(row.children[d].children[0]?.getAttribute("data-number")){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-number"));
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-floor")){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-floor"));
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-corps")){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-corps"));
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-model")){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-model"));
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-square")){

                        let dataPrice = row.children[d].children[0]?.getAttribute("data-square");
                        let number = parseFloat(dataPrice.replace(',', '.'));

                        rowData.push(number);
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-status")){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-status"));
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-price")){

                        let dataPrice = row.children[d].children[0]?.getAttribute("data-price");
                        let number = parseFloat(dataPrice.replace(',', '.'));

                        rowData.push(number);
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-side")){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-side"));
                    }else {
                        rowData.push(" ");
                    }

                    if(row.children[d].children[0]?.getAttribute("data-kvmprice")){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-kvmprice"));
                    }else {
                        rowData.push(" ");
                    }

                    if(projname){
                        rowData.push(projname);
                    }else {
                        rowData.push(" ");
                    }



                    if(row.children[d].children[0]?.getAttribute("data-bedroom")!="undefined"){
                        rowData.push(row.children[d].children[0]?.getAttribute("data-bedroom"));
                    }else {
                        rowData.push(" ");
                    }

                    worksheet.addRow(rowData);

                }
            }
        }

        worksheet.eachRow({ includeEmpty: false }, (row, rowNumber) => {
            for (var number=1;number<12;number++){
                var cell = row.getCell(number);
                cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };

                cell.border = {
                    top: {style: 'thin'},
                    left: {style: 'thin'},
                    bottom: {style: 'thin'},
                    right: {style: 'thin'}
                };
            }

        });


        await new Promise(resolve => setTimeout(resolve, 100)); // Добавим задержку на 100 мс
        var buffer = await workbook.xlsx.writeBuffer();
        var blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = "catalog_report_details.xlsx";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    async function generateExcel() {
        var maininfo = document.getElementById("contentWrapper");
        var workbook = new ExcelJS.Workbook();
        var worksheet = workbook.addWorksheet("Лист 1");

        var count = 1; // Начальное значение для отсчета в первой колонке

        for (var i = 0; i < maininfo.childElementCount; i++) {
            var row = maininfo.children[i].children[1];
            var rowData = [];

            var text="";

            text+=count.toString();

            rowData.push(text);
            count++;

            for (var d = 0; d < row.childElementCount; d++) {

                var cellValue="";

                var statusi = row.children[d].children[0]?.getAttribute("data-status");

                var saxeli = row.children[d].children[0]?.getAttribute("data-ownername");

                var nomeri = row.children[d].children[0]?.getAttribute("data-number");

                var parti =  row.children[d].children[0]?.getAttribute("data-square");

                if(statusi){

                    cellValue+="\n";
                    cellValue+="N"+nomeri;

                    cellValue+="\n";
                    cellValue+=parti;


                    if(row.children[d].children[0]?.getAttribute("data-status")=="თავისუფალი"){

                        cellValue+="\n";
                        cellValue += "გასაყიდი";

                    }else if(row.children[d].children[0]?.getAttribute("data-status")=="გაყიდული" || row.children[d].children[0]?.getAttribute("data-status")=="დაჯავშნილი"){

                        cellValue+="\n";
                        cellValue += "გაყიდული";

                    } else {

                        cellValue+="\n";
                        cellValue += row.children[d].children[0]?.getAttribute("data-status");

                    }

                    if(saxeli){
                        cellValue+="\n";
                        cellValue+=saxeli;
                    }


                }
                rowData.push(cellValue || "");
            }

            if (rowData.some(cellValue => cellValue !== "")) {
                worksheet.addRow(rowData);

                for (var d = 0; d < row.childElementCount; d++) {
                    var cellValue = row.children[d].children[0]?.getAttribute("data-status");
                    var fillColor;
                    if (cellValue === "თავისუფალი") {
                        fillColor = "FFBAECC6"; // Серый
                    } else if (cellValue === "NFR-ჰაუსარტი") {
                        fillColor = "FFAADBFC"; // Голубой (более светлый)
                    } else if (cellValue === "NFR") {
                        fillColor = "FFb3b3b3"; // Серый
                    } else if (cellValue === "NFR-არენა") {
                        fillColor = "FFFCD5B4"; // Светло-золотистый (более светлый)
                    } else if (cellValue === "გაყიდული") {
                        fillColor = "FFeef2f4"; // Зеленый (более светлый)
                    } else if (cellValue === "უფასო ჯავშანი") {
                        fillColor = "FFFFFF00"; // Темно оранжевый
                    } else {
                        fillColor = "FFFFFFFF"; // Белый (по умолчанию)
                    }

                    var cell = worksheet.getRow(i + 1).getCell(d + 2);

                    cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };


                    var cell = worksheet.getRow(i + 1).getCell(d + 2);



                    cell.border = {
                        top: {style: 'thin'},
                        left: {style: 'thin'},
                        bottom: {style: 'thin'},
                        right: {style: 'thin'}
                    };

                    cell.fill = {
                        type: "pattern",
                        pattern: "solid",
                        fgColor: { argb: fillColor }
                    };

                }
            }
        }

        var columnWidth = 15; // Замените на желаемую ширину колонок (в пикселях)

        for (var d = 0; d < maininfo.children[0].children[1].childElementCount; d++) {
            worksheet.getColumn(d + 2).width = columnWidth;
        }

        worksheet.getColumn(1).width=columnWidth;

        worksheet.eachRow({ includeEmpty: false }, (row, rowNumber) => {
            var cell = row.getCell(1);
            cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };

            cell.border = {
                top: {style: 'thin'},
                left: {style: 'thin'},
                bottom: {style: 'thin'},
                right: {style: 'thin'}
            };
        });

        // Сохраняем книгу в файл с небольшой задержкой
        await new Promise(resolve => setTimeout(resolve, 100)); // Добавим задержку на 100 мс
        var buffer = await workbook.xlsx.writeBuffer();
        var blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = "catalog_report.xlsx";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    
    // fills project dropdown
    getRetriveData = () => {
        fetch('/rest/local/api/projects/retrieve.php?').then(data => {
            return data.json();
        }).then(data => {
            if (data.status === 200) {
                let projects = data.result;
                let dropdown = document.getElementById('projectDropdownCat');

                projects.forEach(el => {
                    dropdown.innerHTML += `
                        <option value="${el.id}" ${el.id === dealProject ? "selected" : ""}>
                            ${el.name}
                        </option>
                    `;
                });

                // if(dealProject){
                //     getData(dealProject);
                // }
            } else {
                console.error('status code error')
            }
        }).catch(err => {
            console.error('error:', err);

        });
    }
    getRetriveData();
    
    getProjectId = () => {
        let projectDropdownCat = document.getElementById('projectDropdownCat');
        projectDropdownCat.addEventListener('change', function () {
            let projectId = projectDropdownCat.options[projectDropdownCat.selectedIndex].value;

            getData(projectId);
        });
    }
    getProjectId();


    function fillFilters(selectedProject) {
        fetch(`/rest/local/api/product/GetDataForFilter.php?projects=${selectedProject}`).then(data => {
            return data.json();
        }).then(data => {
            let sortedObject = [];
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    const sortedArray = data[key].sort((a, b) => {
                        return parseInt(a, 10) - parseInt(b, 10);
                    });
                    sortedObject[key] = sortedArray;
                }
            }
            addDropdownDataCat(sortedObject[71], "statusDropdownCat", "სტატუსი");
            addDropdownDataCat(sortedObject[73], "CorpsDropdownCat", "ბლოკი");
            addDropdownDataCat(sortedObject?.[61], "floorDropdownCat", "სართული");
            addDropdownDataCat(sortedObject[60], "typeDropdownCat", "ფართის ტიპი");
            addDropdownDataCat(sortedObject[72], "conditionDropdownCat", "კონდიცია");
            addDropdownDataCat(sortedObject[86], "bedroomDropdownCat", "საძინებლები");
            addDropdownDataCat(sortedObject[124], "sideDropdownCat", "ხედი/მხარე");
        });
    }


    function onlyUnique(value, index, self) {
        return self.indexOf(value) === index;
    }
    function filter1() {
        FILTER = [...APARTMENTS];
        let filter = {};
        for (let i = 0; i < FILTER.length; i++) {
            for (let item of FILTER[i]) {
                // console.log(item);
                if (filter[item.status] === undefined) {
                    filter[item.status] = [item.sadarbazo];
                }
                else {
                    filter[item.status].push(item.sadarbazo);
                }
                filter[item.status] = filter[item.status].filter(onlyUnique);
            }
        }
        GENERAL_FILTER = filter;
    }

    function initFilter() {
        filter1();
        let filter = GENERAL_FILTER;
        let dropdown = document.querySelector('#dropdown');
        let dropdown2 = document.querySelector('#dropdown2');
        let all = [];
        for (let i = 0; i < Object.keys(filter).length; i++) {
            for (let item of Object.values(filter)[i]) {
                all.push(item);
            }
            dropdown.innerHTML += `
            <option value="${Object.keys(filter)[i]}">${Object.keys(filter)[i]}</option>
        `;
        }
        all = all.filter(onlyUnique);

        for (let item of all) {
            dropdown2.innerHTML += `
            <option value="${item}">${item}</option>
        `;
        }
    }
    function trueFilter() {
        filter1();
        let filter = GENERAL_FILTER;
        let dropdown = document.querySelector('#dropdown');
        let selected1 = dropdown.options[dropdown.selectedIndex].value;
        let dropdown2 = document.querySelector('#dropdown2');
        let selected2 = dropdown2.options[dropdown2.selectedIndex].value;
        let all = [];
        let pirveli;
        let meore = [];
        if (selected1) {
            dropdown2.innerHTML = `<option value=""></option>`;
            pirveli = [...filter[selected1]];

            // console.log(pirveli);
            for (let item of pirveli) {
                dropdown2.innerHTML += `
                <option value="${item}">${item}</option>
            `;
            }
            // console.log(selected2);
            if (selected2) {
                dropdown2.value = selected2;
            }
        }
        if (selected2) {
            dropdown.innerHTML = `<option value=""></option>`;
            for (let i = 0; i < Object.keys(filter).length; i++) {
                if (Object.values(filter)[i].includes(selected2)) {
                    meore.push(Object.keys(filter)[i]);
                }
            }
            for (let item of meore) {
                dropdown.innerHTML += `
                <option value="${item}">${item}</option>
            `;
            }
            if (selected1) {
                dropdown.value = selected1;
            }
        }
    }
    document.getElementById("close-filter").addEventListener("click",filterPopup);

    function filterPopup() {
        document.getElementById("filter-block").classList.toggle("hide");
        document.getElementById("close-filter").classList.toggle("hide");
        document.getElementById("frozen").classList.toggle("hide_butt");
        document.getElementById("productsContainer").classList.toggle("hide_prods");
    }


    

    function openModal () {
        var mainmodal=document.getElementById('myModal');

        mainmodal.style.display="block";
    }

    var modal = document.getElementById("myModal");

    var span = document.getElementsByClassName("close")[0];

    span.onclick = function() {
        modal.style.display = "none";
    }


    function popup(me, i, k, corp) {
        // console.log(PR0DUCT);
        APARTMENT_INFO = me;
        // document.getElementById('newDropdown').innerHTML = '';

        // console.log(APARTMENTS);

        let asideContainer = document.querySelector('.page-container__aside');
        asideContainer.classList.add('active');
        let apartment = APARTMENTS[i][`${corp}`][k];
        // console.log(apartment);

        // console.log(apartment);


        let myApartmentID = apartment["ID"];

        


        // document.getElementById('naxDown').innerHTML = `
        // <button class="button-19" onclick="downloadAllImages()">
        //     ჩამოტვირთვა <i class="fa-solid fa-download"></i>
        // </button>`;
        let containerDiv = document.getElementById('productsContainer');

        // Save the original width only once
        if (!containerDiv.dataset.originalWidth) {
            containerDiv.dataset.originalWidth = containerDiv.offsetWidth;
        }

        // Always calculate based on the original width
        let newMaxWidth = containerDiv.dataset.originalWidth - 400;

        // Apply styles
        containerDiv.style.maxWidth = newMaxWidth + 'px';
        containerDiv.style.overflow = 'auto';


        document.getElementById('offer').innerHTML = `<a href="/crm/deal/offer-catalog.php?prod_ID=${myApartmentID}" target="_blank"><button class="button-19">შეთავაზება</button></a>`;
        document.getElementById('calculator').innerHTML = `<a href="/custom/calculator/entire.php?dealid=${dealId}&ProductID=${myApartmentID}" target="_blank"><button class="button-19">კალკულატორი</button></a>`;
        document.getElementById('offerEng').innerHTML = `<a href="/crm/deal/offer-catalog-eng.php?prod_ID=${myApartmentID}" target="_blank"><button class="button-19">შეთავაზება ENG</button></a>`;
        // document.querySelector('#apartmentInfoBlockContainer').children[0].children[0].children[0].innerHTML =apartment["62"] +" №" + apartment["217"];
        document.querySelector('#apartmentInfoBlockContainer').children[0].children[0].children[0].innerHTML ="";
        document.querySelector('#apartmentInfoBlockContainer').children[0].children[1].innerHTML = apartment["_WJ6N47"];
        // document.querySelector('#apartmentInfoBlockContainer').children[2].children[0].children[0].innerHTML = apartment["PRICE"];
        // document.querySelector('#detailImg').src = apartment["218"];

                
        document.querySelector('#apartmentStatusInfo').innerHTML = apartment["71"];



        fetch(`/custom/getstaticfields.php?prodID=${Number(myApartmentID)}`).then(data => {
            return data.json();
        }).then(data => {

            // console.log(data);


            document.getElementById("detailImg").src = data.image;

            document.querySelector('#detailImg_3d_2d_carousel').src = data.image;
            document.querySelector('#detailImg_3d_carousel').src = data.image2;
            document.querySelector('#detailImg_carousel').src = data.image3;

            let dataBlock = "";
      

            if (data["_51DT4E"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">პროექტი: <span>' + data["_51DT4E"] + '</span></li>';
            }

            if (data["__OY0G7R"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">ქონების ტიპი: <span>' + data["__OY0G7R"] + '</span></li>';
            }

            if (data["_IL24RV"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">სართული: <span>' + data["_IL24RV"] + '</span></li>';
            }

        
            
            
            if (data["KORPUSIS_NOMERI_XE3NX2"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">ბლოკი: <span>' + data["KORPUSIS_NOMERI_XE3NX2"] + '</span></li>';
            }

            if (data["_2RS72M"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">№: <span>' + data["_2RS72M"] + '</span></li>';
            }

    
            
            // if (data["Number"]) {
            //     dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">სართული: <span>' + data["_IL24RV"] + '</span></li>';
            // }

            if (data["Number"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">ქონების ტიპი: <span>' + data["PRODUCT_TYPE"] + '</span></li>';
            }
        

            // if (data["Number"]) {
            //     dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">სადარბაზო: <span>' + data["_15MYD6"] + '</span></li>';
            // }
            if (data["Number"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">№: <span>' + data["Number"] + '</span></li>';
            }

            dataBlock += '<li class="apartment-about-li" style="font-weight: bold;border-bottom: 1px solid black"><span></span></li>';

            var sruli_pasebi = '<div style="display: flex; justify-content: space-between; width: 100%;">';

            if (data["PRICE"]) {
                sruli_pasebi += '<li class="apartment-about-li" style="font-weight: bold; width: 100%;">სრული ფასი $: <span>' + numberFormat(data["PRICE"]) + '</span></li>';
            }

            if (data["M2__8MKGVW"]) {
                sruli_pasebi += '<li class="apartment-about-li" style="font-weight: bold; width: 50%;">ფასი m<sup>2</sup> $: <span>' + numberFormat(parseInt(data["M2__8MKGVW"])) + '</span></li>';
            }

            var sruli_pasebi_gel = '<div style="display: flex; justify-content: space-between; width: 100%;">';
            if (data["__ACHC7B"]) {
                sruli_pasebi_gel += '<li class="apartment-about-li" style="font-weight: bold; width: 100%;">სრული ფასი ₾: <span>' + numberFormat(data["__ACHC7B"]) + '</span></li>';
            }

            if (data["_M2__SUXOA7"]) {
                sruli_pasebi_gel += '<li class="apartment-about-li" style="font-weight: bold; width: 50%;">ფასი m<sup>2</sup> ₾: <span>' + numberFormat(parseInt(data["_M2__SUXOA7"])) + '</span></li>';
            }

            sruli_pasebi += '</div>';
            sruli_pasebi_gel += '</div>';
            dataBlock += sruli_pasebi;
            dataBlock += sruli_pasebi_gel;


            dataBlock += '<li class="apartment-about-li" style="font-weight: bold;border-bottom: 1px solid black"><span></span></li>';

            if (data["__ERWGRY"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">საერთო ფართი: <span>' + data["__ERWGRY"] + 'm<sup>2</span></li>';
            }
            if (data["__I5V4XI"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;"> ფართი (საცხოვრებელი): <span>' + data["__I5V4XI"] + 'm<sup>2</span></li>';
            }

            if (data["terrace_area"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">ტერასის ფართი: <span>' + data["terrace_area"] + '</span></li>';
            }

            if (data["__FVE8A2"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">ფართი (საზაფხულო): <span>' + data["__FVE8A2"] + 'm<sup>2</span></li>';
            }
            if (data["BALCONY_AREA"]) {
                dataBlock += '<li class="apartment-about-li" style="font-weight: bold;">აივნების ჯამური ფართი: <span>' + data["BALCONY_AREA"] + 'm<sup>2</span></li>';
            }


            if (data["QUEUE"]!=='') {

                var linkshtml='';

                var javshaniarr = data["QUEUE"].split('|');

                let counter=0;
                for (var f = 0; f < javshaniarr.length; f++) {
                    if (javshaniarr[f] !== '') {

                        
                        if(counter==0){
                            linkshtml = `<a href="/crm/deal/details/${javshaniarr[f]}/" target="_blank">${javshaniarr[f]}</a>`;

                        }else{
                            linkshtml += `,<a href="/crm/deal/details/${javshaniarr[f]}/" target="_blank">${javshaniarr[f]}</a>`;

                        }
                        counter++
                    }
                }


                linkshtmlContact = `<a href="https://146.255.242.182/crm/contact/details/${data["QUEUE_CONTACT_ID"]}/" target="_blank">${data["QUEUE_CONTACT_NAME"]}</a>`;
                linkshtmlResponsible = `<a href="http://146.255.242.182/company/personal/user/${data["QUEUE_RESPONSIBLE_ID"]}/" target="_blank">${data["QUEUE_RESPONSIBLE_NAME"]}</a>`;



                dataBlock += '<li class="apartment-about-li">რიგი: <span> ' + linkshtml + '</span></li>';
                dataBlock += '<li class="apartment-about-li">კონტაქტი: <span> ' +linkshtmlContact + '</span></li>';
                dataBlock += '<li class="apartment-about-li">პასუხისმგებელი: <span> ' +linkshtmlResponsible + '</span></li>';


                

            }

            dataBlock +='<li class="apartment-about-li" style="visibility: hidden;"><span id="productId">' + data["ID"] + '</span></li>';

            document.getElementById("apartment").innerHTML = dataBlock;




            dataBlock+='<div id="newdDropdownBlock"><div class="new-drop-down-hide-block"> <div class="new-drop-down-hide" id="newDropdownCloseBtn" onclick="closeNewDropdown(this)"> <a class="new-drop-down-hide__icon">&#8595</a></div></div><ul id="newDropdown" class="new-drop-down"></ul></div>';


            document.getElementById("apartment").innerHTML = dataBlock;

            var newdrop=document.getElementById('newDropdown');


            function addApartmentDetail(key, label, unit = '') {
                if (data[key] !== "0" && data[key] !== "") {
                    const formattedValue = Number(data[key]).toLocaleString('en-US');  // Formats with commas
                    newdrop.innerHTML += `<li class="apartment-about-li">${label}: <span>${formattedValue}${unit}</span></li>`;
                }
            }

            addApartmentDetail("__4IOFZC", "ოთახების რაოდენობა");
            addApartmentDetail("Bedrooms", "საძინებლების რაოდენობა");
            if(data["__9GBYAF"]){
                addApartmentDetail("__9GBYAF", "სვ.წერტ რაოდენობა");
                newdrop.innerHTML += '<li class="apartment-about-li" style="font-weight: bold;border-bottom: 1px solid black"><span></span></li>';
            }

            if(data["_H8WF0T"]){
                newdrop.innerHTML += `<li class="apartment-about-li">კონდიცია: <span>${data["_H8WF0T"]}</span></li>`;
            }

            if(data["projEndDate"]){
                newdrop.innerHTML += `<li class="apartment-about-li">პროექტის დასრულების თარიღი: <span>${data["projEndDate"]}</span></li>`;
            }

            
        });








        function numberFormat(num) {
            if (num) {
                return num.toLocaleString('en-US',{minimumFractionDigits: 0});
            }
            else return "";
        }

        let apartmentStatusInfo = document.querySelector('#apartmentStatusInfo').innerHTML;

        if (apartmentStatusInfo !== 'თავისუფალი' && apartmentStatusInfo !== 'ჯავშნის რიგი' && apartmentStatusInfo !== 'დაჯავშნილი') {
            document.querySelector('#addBtn').disabled = true;
            document.querySelector('#addBtn').classList.add('disabled--btn');
        } else {

            if (HAS_PRODUCT  ) {
                document.querySelector('#addBtn').disabled = true;
                document.querySelector('#addBtn').classList.add('disabled--btn');
            } else {
       
                document.querySelector('#addBtn').disabled = false;
                document.querySelector('#addBtn').classList.remove('disabled--btn');
            }
        }

        checkStatusInfo();
    }
    checkStatusInfo = () => {


        let catalogOnDeal = false;
        pathname = window.location.pathname.split("/");
        if (pathname[2] == "deal") {
            catalogOnDeal = true;
        }

        let apartmaentStatus = document.querySelectorAll('.apartment-status-info');
        apartmaentStatus.forEach(element => {

            if (element.innerHTML === 'გაყიდული') {
                document.getElementById('addBtn').style.display="none";
                document.getElementById('delBtn').style.display="none";
            }
            else if (element.innerHTML === 'დაჯავშნილი') {
                if(STAGE==false){
                    if(HAS_PRODUCT==true){
                        document.getElementById('addBtn').style.display="none";
                        document.getElementById('delBtn').style.display="none";
                    }else{
                        // document.getElementById('addBtn').style.display="block";
                        document.getElementById('addBtn').style.display="none";
                        document.getElementById('delBtn').style.display="none";
                    }
                }else{
                    document.getElementById('addBtn').style.display="none";
                    document.getElementById('delBtn').style.display="none";
                }

            }
            else if (element.innerHTML === 'თავისუფალი') {
                if(STAGE==false){
                    if(HAS_PRODUCT==true){
                        document.getElementById('addBtn').style.display="none";
                        document.getElementById('delBtn').style.display="flex";
                    }else{
                        document.getElementById('addBtn').style.display="flex";
                        // document.getElementById('addBtn').style.display="none";
                        document.getElementById('delBtn').style.display="none";
                    }
                }else{
                    if(HAS_PRODUCT==true){
                        document.getElementById('addBtn').style.display="none";
                        document.getElementById('delBtn').style.display="flex";
                    }else{
                        document.getElementById('addBtn').style.display="flex";
                        // document.getElementById('addBtn').style.display="none";
                        document.getElementById('delBtn').style.display="none";
                    }
                }
            }else{
                document.getElementById('addBtn').style.display="none";
                document.getElementById('delBtn').style.display="none";
            }


            if(stage_ID){
                allowedStages = ["NEW", "FINAL_INVOICE", "1", "2", "3", "4", "WON", "LOSE"];
                if (allowedStages.includes(stage_ID)) {
                    document.getElementById('addBtn').style.display="none";
                    document.getElementById('delBtn').style.display="none";
                    if(stage_ID == "FINAL_INVOICE"){
                        document.getElementById('addBtn').style.display="";
                    }
                }
            }
          

            if(!catalogOnDeal){
                document.getElementById('addBtn').style.display="none";
                document.getElementById('delBtn').style.display="none";
            }

            if(element.innerHTML == "თავისუფალი"){
                element.style.backgroundColor = "#168766"
            }
            else if (element.innerHTML === 'გაყიდული') {
                element.style.backgroundColor = "#BA2F2F"
            }
            else if (element.innerHTML === 'ჯავშნის რიგი') {
                element.style.backgroundColor = "#0564d0"
            }
            else if (element.innerHTML === 'NFR' || element.innerHTML === 'NFS') {
                element.style.backgroundColor = "#8e3a7d"
            }
            else if (element.innerHTML === 'დაჯავშნილი') {
                element.style.backgroundColor = "#F5C342"
            }
            else if (element.innerHTML === 'უფასო ჯავშნი') {
                element.style.backgroundColor = "#FF8604"
            }
            else if (element.innerHTML === 'Not For Sale (მერია)') {
                element.style.backgroundColor = "#F46A53"
            }
            else if (element.innerHTML === 'Not For Sale (ჯიმი)') {
                element.style.backgroundColor = "#FF9A7B"
            }
            else if (element.innerHTML === 'Not For Sale') {
                element.style.backgroundColor = "#BABABA"
            }
            else {
                element.style.backgroundColor = "#9d9a9a"
            }


        });
    }

    function getData(projectId, mode) {
        if (!projectId) {
            let projName = <?php echo json_encode($projName); ?>;
            let dealProject = null;

            if (projName === "Anagi") {
                dealProject = 15;
            }

            // Default to 15 if nothing else found
            projectId = dealProject || '';
        }


        fillFilters(projectId);
        document.querySelector('#contentWrapper').innerHTML = '<p>loading...</p>';

        fetch(`/rest/local/api/projects/get.php?project=${projectId}&DEAL_ID=${dealId}`).then(data => {
            return data.json();
        }).then(data => {


            if (data.status === 200) {

                HAS_PRODUCT = data.hasProduct;
                STAGE = data.stage;
                

                document.querySelector('#contentWrapper').innerHTML = '';

           
                products = data.products["products"];

             
                const grouped = {};

                products.forEach(item => {
                    const floor = item?.[61]; // Get floor value
                    let block = item?.[73] || "-"; // If block doesn't exist, assign "-"

                    if (!grouped[floor]) {
                        grouped[floor] = {}; // Create object for floor if doesn't exist
                    }

                    if (!grouped[floor][block]) {
                        grouped[floor][block] = []; // Create array for block if doesn't exist
                    }

                    grouped[floor][block].push(item); // Add item to the corresponding block
                });

                // Step 2: Find all unique blocks across all floors
                let allBlocks = new Set();

                // Collect all unique block names
                Object.values(grouped).forEach(floorData => {
                    Object.keys(floorData).forEach(block => {
                        allBlocks.add(block);
                    });
                });

                // Convert set to sorted array
                allBlocks = Array.from(allBlocks).sort((a, b) => a.localeCompare(b));
                // console.log("All unique blocks:", allBlocks);

                // Step 3: Create the final structure with consistent blocks on all floors
                // First sort the floors
                const sortedFloors = Object.keys(grouped).sort((a, b) => {
                    // -1 and -2 floors should be at the end, other floors in numerical order
                    if (a === "-1" || a === "-2") return 1;
                    if (b === "-1" || b === "-2") return -1;
                    return b - a; // Sort floors numerically
                });

                // Create the final array of floor data
                const apartments = sortedFloors.map(floor => {
                    // For each floor, create an object with all blocks
                    const floorData = {};
                    
                    // Add each block from our master list to this floor
                    allBlocks.forEach(block => {
                        // Use existing data or empty array if this block doesn't exist on this floor
                        floorData[block] = grouped[floor][block] || [];
                    });
                    
                    return floorData;
                });

        
                APARTMENTS = apartments;
                // console.log("apartments");
                // console.log(apartments);

                for (let i = 0; i < apartments.length; i++) {
                    let index1 = 0;
                    let floorsString = '';
                    let btn = '';

                    let keys = Object.keys(apartments[i]);
                    for (let j = 0; j < keys.length; j++) {
                        let keysJ = keys[j];  
                        // console.log(keysJ);       
                        let aps = apartments[i][keysJ];
                        keysJ = (keys[j] === "-" || keys[j] == null) ? "" : keys[j];
                        if (keysJ) {
                            keysJ += ":"; 
                        }
                        // console.log(aps);

                        let corpsDiv = '<div style="display: flex;align-items: center;" >';
                        
                        corpsDiv += `<span class="blockName" style="margin-left: 30px; margin-right: 10px;">${keysJ}</span>`;
                        
                        let width = 1000;
                        
                        // if (projectId == 15) {
                        //     width = 350;
                        // } 

                        let innerDiv = `<div class="apartmentsDiv" style="display:flex; flex-wrap: wrap; width:${width}px;">`;

                 
                        if (aps && aps.length > 0) {
                            let index = 0;
                            for (let apartment of aps) {
                                // console.log(apartment["71"]);
                                if (apartment.PRICE === null) {
                                    apartment.PRICE = 1;
                                }
                                let statusClass = "sold";

                                switch (apartment["71"]) {
                                    case "გაყიდული": statusClass = "sold"; break;
                                    case "დაჯავშნილი": statusClass = 'reservation-form'; break;
                                    case "თავისუფალი": {
                                        if(apartment["261"] == "YES"){
                                            statusClass = 'broker';
                                        } else {
                                            statusClass = 'free';
                                        }
                                    } break;
                                    case "უფასო ჯავშანი": statusClass = 'queue-form'; break;
                                    case "გასაცემი": statusClass = 'gasacemi'; break;
                                    case "NFS": statusClass = 'NFS'; break;

                                    
                                    case '': statusClass = 'empty--status'; break;
                                    default: statusClass = 'gasacemi'; break;
                                }

                                dataBedroom = apartment['86'];

                                innerDiv += `
                                    <div class="floor-item gray">
                                        <div class="floor-item__inner ${statusClass}" data-sacadastro="${apartment["215"]}" data-price="${apartment.PRICE}" data-kvmprice="${apartment["67"]}" data-KVMprice-withwhite="${apartment["247"]}" data-price-withwhite="${apartment["246"]}" data-number='${apartment["65"]}' data-product_id="${apartment.ID}" data-square="${apartment['62']}" data-status="${apartment["71"]}" data-sadarbazo="${apartment['78']}" data-broker="${apartment['261']}" data-side='${apartment["124"]}' data-model='${apartment["60"]}' data-floor='${apartment?.["61"]}' data-bedroom="${dataBedroom}" data-Corps='${apartment["73"]}' onclick="popup(this, ${i}, ${index}, '${keys[j]}')" style="transform: scale(${Number(data.PRODUCT_ID) === Number(apartment.ID) ? '1.3' : '1'}); ${Number(data.PRODUCT_ID) === Number(apartment.ID) ? 'outline: 2px solid #ff343a' : 'outline: none'}"><div><div>${apartment["65"]}</div><div>${apartment['62']}</div></div></div>
                                    </div>
                                `;
                                
                                index++;
                            }
                        } else {
                            
                            innerDiv += `<div class="empty-block-message" style="color: #999; font-style: italic; padding: 5px 10px;"> </div>`;
                        }
                        
                        innerDiv += `</div>`;
                        corpsDiv += innerDiv;
                        corpsDiv += '</div>';
                        floorsString += corpsDiv;
                        index1++;
                    }

                
                        let floorNumber = null;
                        const blocks = Object.values(apartments[i]);
                        
                        for (const block of blocks) {
                            if (block && block.length > 0 && block[0]?.[61]) {
                                floorNumber = block[0][61];
                                break;
                            }
                        }
                
                        if (floorNumber === null) {
                
                            floorNumber = sortedFloors ? sortedFloors[i] : i + 1;
                        }
                
                        let contentWrapper = document.querySelector('#contentWrapper');
                        contentWrapper.innerHTML += `
                            <div class="floor-block floor)">
                                <p class="floor-number sticky-horizontal" style="height: 30px;width: 30px; align-items: center;display: flex;justify-content: center; margin-top: 18px;">${floorNumber}</p>
                                <div class="floor-section">
                                    ${floorsString}
                                </div>
                            </div>
                        `;
                }
                activeItem();
           
            }
            else {
                document.getElementById("contentWrapper").innerHTML = "<a style='font-size: 16px;color: red;'>ვერ მოიძებნა</>";
            }
        }).catch((err) => {
            console.error('error:', err);
        });
    }


    activeItem = () => {
        let floorItem = document.querySelectorAll('.floor-item__inner');
        for (let item of floorItem) {
            item.addEventListener('click', function (e) {
                let elems = document.querySelector(".floor-item--active");
                if (elems != null) {
                    elems.classList.remove("floor-item--active");
                }
                e.target.parentNode.className += " floor-item--active";
            });
        }
    }
    closeMinimiserLeftSidebar = () => {
        let closeBtn = document.querySelector('#closeBtn');
        closeBtn.addEventListener('click', function () {
            this.parentNode.classList.remove('active');
            let containerDiv = document.getElementById('productsContainer');
            // Get current width
            let currentWidth = containerDiv.offsetWidth;

            // Subtract 400px
            let newMaxWidth = currentWidth + 400;

            // Set max-width
            containerDiv.style.maxWidth = newMaxWidth + 'px';
            containerDiv.style.overflow = 'none';
        });
    }
    closeMinimiserLeftSidebar();

    function setInputFilter(textbox, inputFilter) {
        ["input", "keydown", "keyup", "mousedown", "mouseup", "select", "contextmenu", "drop"].forEach(function (event) {
            textbox.addEventListener(event, function () {
                if (inputFilter(this.value)) {
                    this.oldValue = this.value;
                    this.oldSelectionStart = this.selectionStart;
                    this.oldSelectionEnd = this.selectionEnd;
                } else if (this.hasOwnProperty("oldValue")) {
                    this.value = this.oldValue;
                    this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
                }
            });
        });
    }

    setInputFilter(document.getElementById("startPrice"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("endPrice"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("startPriceKvm"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("endPriceKvm"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("startSqr"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("endSqr"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("startNumer"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("endNumer"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("startSartul"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });
    setInputFilter(document.getElementById("endSartul"), function (value) {
        return /^\d*\.?\d*$/.test(value);
    });










    function func() {
        let projectDropdownCat = document.getElementById('projectDropdownCat');
        // console.log(projectDropdownCat.options);
        let projectId = projectDropdownCat.options[projectDropdownCat.selectedIndex].value;
        let priceArr = document.querySelectorAll('[data-price]');

        let startPrice = parseFloat(document.getElementById('startPrice').value);
        let endPrice = parseFloat(document.getElementById('endPrice').value);
        let startPriceKvm = parseFloat(document.getElementById('startPriceKvm').value);
        let endPriceKvm = parseFloat(document.getElementById('endPriceKvm').value);
        let startSquare = parseFloat(document.getElementById('startSqr').value);
        let endSquare = parseFloat(document.getElementById('endSqr').value);
        let startnumber=parseFloat(document.getElementById('startNumer').value);
        let endnumber=parseFloat(document.getElementById('endNumer').value);
        // let apartmentNumberValue = document.getElementById('apartmentNumber').value;
        let CadastralNumberValue = document.getElementById('CadastralNumber').value;
        let startSartuli=parseFloat(document.getElementById('startSartul').value);
        let endSartuli=parseFloat(document.getElementById('endSartul').value);

        if (!startPrice) startPrice = 0;
        if (!endPrice) endPrice = Number.MAX_SAFE_INTEGER + 1;
        if (!startPriceKvm) startPriceKvm = 0;
        if (!endPriceKvm) endPriceKvm = Number.MAX_SAFE_INTEGER + 1;
        if (!startSquare) startSquare = 0;
        if (!endSquare) endSquare = Number.MAX_SAFE_INTEGER + 1;
        if (!startnumber) startnumber = 0;
        if (!endnumber) endnumber = Number.MAX_SAFE_INTEGER + 1;
        if (!startSartuli) startSartuli = 0;
        if (!endSartuli) endSartuli = Number.MAX_SAFE_INTEGER + 1;
        // if (!apartmentNumberValue) apartmentNumberValue = null;
        if (!CadastralNumberValue) CadastralNumberValue = null;

        let selectedStatus = getSelectedOptionsGBCat("statusDropdownCat");

        let selectedCorps = getSelectedOptionsGBCat("CorpsDropdownCat");

        let selectedType = getSelectedOptionsGBCat("typeDropdownCat");

        let selectedCondition = getSelectedOptionsGBCat("conditionDropdownCat");

        let selectedSide = getSelectedOptionsGBCat("sideDropdownCat");

        let selectedBedroom = getSelectedOptionsGBCat("bedroomDropdownCat");

        let selectedFloor = getSelectedOptionsGBCat("floorDropdownCat");

        let selectedBroker = getSelectedOptionsGBCat("brokerDropdownCat");


        const newTextFilters = document.querySelectorAll(".new-text-filter");
        const newRangeFilters = document.querySelectorAll(".new-range-filter");

        //gbe searchString
        found = [];
        for (let prod of products) {
            found.push(prod);
        }

        // new filter
        for (let i = 0; i < newTextFilters.length; i++) {
            const el = newTextFilters[i];

            if(el.value) {
                let fieldId = el.id;

                fieldId = fieldId.split("input_")[1];

                if(fieldId) {
                    found = found.filter(each => {
                        if (each[fieldId] == el.value) return each;
                    });
                }
            }
        }

        for (let i = 0; i < newRangeFilters.length; i++) {
            const rangeDiv = newRangeFilters[i];

            // Получаем оба инпута в пределах данного rangeDiv
            const inputFrom = rangeDiv.querySelector('input[id^="input_"][id$="_dan"]');
            const inputTo = rangeDiv.querySelector('input[id^="input_"][id$="_mde"]');

            if ((inputFrom && inputFrom.value) || (inputTo && inputTo.value)) {
                let fieldId = inputFrom ? inputFrom.id.split("input_")[1].split("_dan")[0] : inputTo.id.split("input_")[1].split("_mde")[0];

                let minValue = inputFrom && inputFrom.value ? parseFloat(inputFrom.value) : -Infinity;
                let maxValue = inputTo && inputTo.value ? parseFloat(inputTo.value) : Infinity;

                if (fieldId) {
                    found = found.filter(each => {
                        let fieldValue = parseFloat(each[fieldId]);

                        return fieldValue >= minValue && fieldValue <= maxValue;
                    });
                }
            }
        }


        priceArr.forEach(el => {
            // console.log(el.dataset);

            const result = found.some(item => item.ID === el.dataset.product_id ) ? "Yes" : "No";

            if (
                (result == "Yes" )
                && 
                ((parseInt(el.dataset.price) >= startPrice && parseFloat(el.dataset.price) <= endPrice) || (startPrice === 0 && endPrice == Number.MAX_SAFE_INTEGER + 1))
                &&((parseInt(el.dataset.kvmprice) >= startPriceKvm && parseFloat(el.dataset.kvmprice) <= endPriceKvm) || (startPriceKvm === 0 && endPriceKvm == Number.MAX_SAFE_INTEGER + 1))
                && (selectedStatus.length === 0 || selectedStatus.includes(el.dataset.status))
                && (selectedBroker.length === 0 || selectedBroker.includes(el.dataset.broker))
                && (selectedCorps.length === 0 || selectedCorps.includes(el.dataset.corps))
                && (selectedType.length === 0 || selectedType.includes(el.dataset.model))
                && (selectedSide.length === 0 || selectedSide.includes(el.dataset.side))
                && (selectedBedroom.length === 0 || selectedBedroom.includes(el.dataset.bedroom))
                && (selectedFloor.length === 0 || selectedFloor.includes(el.dataset.floor))
                // && (apartmentNumberValue === el.dataset.number || extractNumber_catalog(el.dataset.number) == apartmentNumberValue || apartmentNumberValue=== null)
                && (CadastralNumberValue === el.dataset.sacadastro || CadastralNumberValue=== null)
                && ((parseFloat(el.dataset.square) >= startSquare && parseFloat(el.dataset.square) <= endSquare) || (startSquare === 0 && endSquare == Number.MAX_SAFE_INTEGER + 1))
                && ((parseFloat(el.dataset.number) >= startnumber && parseFloat(el.dataset.number) <= endnumber) || (startnumber === 0 && endnumber == Number.MAX_SAFE_INTEGER + 1))
                && ((parseFloat(el.dataset.floor) >= startSartuli && parseFloat(el.dataset.floor) <= endSartuli) || (startSartuli === 0 && endSartuli == Number.MAX_SAFE_INTEGER + 1))
            ) {
                el.classList.remove('floor-item__inner--status');
                el.parentElement.classList.remove('floor-item_hidden');
            } else {
                el.classList.add('floor-item__inner--status');
                el.parentElement.classList.add('floor-item_hidden');
            }



        });

    }

    document.getElementById('filterBtn').addEventListener('click', func);


    function isNumeric(value) {
        return !isNaN(value) && !isNaN(parseFloat(value));
    }


    function extractNumberFromHTML(input) {
        // HTML ტეგების წაშლა
        const withoutTags = input.replace(/<[^>]*>/g, '');

        // თუ დარჩენილი მხოლოდ რიცხვია, აბრუნებს როგორც რიცხვს
        if (!isNaN(withoutTags.trim())) {
            return Number(withoutTags.trim());
        }

        return null; // ან დააბრუნე false/'' როგორც გინდა
    }



 


    document.getElementById('addBtn').addEventListener('click', function () {
        // console.log("tets");
        // let productId = this.parentNode.previousElementSibling.children[5].children[0].innerHTML;
        let productId = document.getElementById('productId').innerHTML;


    


        if(isNumeric(productId)){
            productId = productId;
        }else{
            productId = extractNumberFromHTML(productId);

        }


        fetch(`/rest/local/api/projects/addProductOnDeal.php?deal_id=${dealId}&productId=${productId}&userID_cat=${userID_cat}`).then(data => {
            return data.json();
        }).then(data => {
            if(data.status == 200){
                alert(data.message);
                location.reload();


                document.getElementById('addBtn').style.display = "none";
                document.getElementById('delBtn').style.display = "block";
         
            }
            else{
                alert(data.error);

                APARTMENT_INFO.style.transform = 'scale(1.3)';
                APARTMENT_INFO.style.outline = '2px solid #ff343a';
                HAS_PRODUCT = true;
                document.getElementById('addBtn').disabled = true;
                document.getElementById('addBtn').classList.add('disabled--btn');
            }
        }).catch((err) => {
            console.log('error:', err);
        });
    });





    function extractNumber_catalog(str) {
        let match = str.match(/\d+/);
        return match ? parseInt(match[0], 10) : null;
    }
    function closeNewDropdown(me) {
        document.getElementById('newDropdown').classList.toggle('new-drop-down-active');
        me.children[0].classList.toggle('rotate');
    }



    function addDropdownDataCat(data,id,name) {
        if(data){
            let selectID = id + "Select";
            let checkbox = id + "Checkbox";
            let select = document.getElementById(selectID);
            let htmlForDropdown = "";
            let label = "." + id + "Label";
            var customDropdown = document.getElementById(id);
            var customDropdownLabel = customDropdown.querySelector(label);
            customDropdownLabel.textContent = name;
            for (let id of Object.keys(data)) {
                var value = data[id];
                htmlForDropdown += `
                                <label class="multipleLabelCat"><input type="checkbox" class="${checkbox} multiple_CheckboxCat" value="${id}"> ${value}</label>
                            `;
            }
            select.innerHTML = htmlForDropdown;
            multipleDropdownGBCat(id,name);
        }
    }

    function multipleDropdownCleanGBCat(id,Name) {
        let label = "." + id + "Label";
        let select = id + "Select";
        let checkbox = "." + id + "Checkbox";
        var customDropdown = document.getElementById(id);
        var customDropdownSelect = document.getElementById(select);

        var customDropdownLabel = customDropdown.querySelector(label);

        var customDropdownCheckboxes = customDropdown.querySelectorAll(checkbox);

        customDropdownCheckboxes.forEach(function (checkbox) {
            checkbox.checked=false;
        });
        customDropdownLabel.textContent = Name;
        fillFilters();
    }


    function multipleDropdownGBCat(id,Name) {
        let label = "." + id + "Label";
        let select = id + "Select";
        let checkbox = "." + id + "Checkbox";
        var customDropdown = document.getElementById(id);
        var customDropdownSelect = document.getElementById(select);

        var customDropdownLabel = customDropdown.querySelector(label);

        var customDropdownCheckboxes = customDropdown.querySelectorAll(checkbox);


        document.addEventListener("click", function (event) {
            if (!customDropdown.contains(event.target)) {
                customDropdownSelect.style.display = "none";
            }
        });

        customDropdownCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener("change", function () {
                updateLabelCat(id);
            });
        });

        function updateLabelCat(id) {
            var selectedOptions = Array.from(customDropdownCheckboxes)
                .filter(function (checkbox) {
                    return checkbox.checked;
                })
                .map(function (checkbox) {
                    return checkbox.nextSibling.textContent.trim();
                });

            let count = selectedOptions.length;
            if(count == 0){
                customDropdownLabel.textContent = Name;
            }
            else if (count > 0 && count <= 2) {
                customDropdownLabel.textContent = Name +": " + selectedOptions.join(", ");
            }
            else if(count > 2){
                customDropdownLabel.textContent = "არჩეულია " + count+ " " + Name;
            }
            if (id === "projectDropdownCat"){
                fillFilters();
            }


        }
    }

    function dropdownToogleCat(select){
        let customDropdownSelect = document.getElementById(select);
        customDropdownSelect.style.display = customDropdownSelect.style.display === "block" ? "none" : "block";
    }

    function getSelectedOptionsGBCat(id){
        let checkbox = "." + id + "Checkbox";
        let customDropdown = document.getElementById(id);

        let customDropdownCheckboxes = customDropdown.querySelectorAll(checkbox);

        let selectedOptions = Array.from(customDropdownCheckboxes)
            .filter(function (checkbox) {
                return checkbox.checked;
            })
            .map(function (checkbox) {
                return checkbox.nextSibling.textContent.trim();
            });
        return selectedOptions;
    }


    document.getElementById('filtersDropdown').addEventListener('click', function(event) {
    event.stopPropagation();
});

    function filterTags() {
        // Get the input value
        var input = document.getElementById('filterInput');
        var filter = input.value.toLowerCase();

        // Get all <a> tags in the div
        var links = document.querySelectorAll('#filtersDropdown a');

        // Loop through the links and hide or show based on the filter
        links.forEach(function(link) {
            var text = link.textContent || link.innerText;
            if (text.toLowerCase().indexOf(filter) > -1) {
                link.style.display = "";
            } else {
                link.style.display = "none";
            }
        });


 }




    function drawAllFilter() {
        const propertyFields = document.getElementById("filter");

        for (let i = 0; i < resProperties.length; i++) {
            const element = resProperties[i];

            let id = element["ID"];
            let name = element["NAME"];

            var range_filters = [
                'სართული', 'საერთო ფართი', 'საცხოვრებელი ფართი', 'ფასი m2 $', '№', 'ბლოკი', 'სადარბაზო', 'საზაფხულო ფართი', 'ტერასის ფართი',
                'აივნის ფართი 1', 'აივნის ფართი 2', 'სვ.წერტ.რაოდენობა', 'სვ.წერტ.ფართი 1', 'სვ.წერტ.ფართი 2', 'საძინებლის რაოდენობა', 'საძინებლის ფართი 1',
                'საძინებლის ფართი 2', 'ოთახების რაოდენობა', 'ნუმერაცია', 'ფასი m2 ₾', 'სრული ფასი ₾', 'ეზოს ფართი'
            ]

            if (!['73', '74', '61', '60', '220', '218', '219', '67', '204', '202', '217', '203', '341', '342', 
                '343', '208', '87', '89', '206', '205', '228', '86', '359', '60', '130', '131', '226', '309', '85', '80', 
                '357', '124', '307', '358', '356', '380'].includes(id)) {

                if (range_filters.includes(name)) {
                    propertyFields.querySelector("#addFiltersBtn").insertAdjacentHTML('beforebegin', `
                        <div class="customfilters" style="display: flex; margin-left: -11px; width: 250px;" id="block_${id}">
                            <div class="custom-dropdownCat">
                                <div class="area" style="background-color:#031332; color:white;">${name}</div>
                                <div class="input-container new-range-filter" >
                                    <input type="text" class="multipleMainLabelCat" id="input_${id}_dan" name="${id}_dan" placeholder="დან">
                                    <input type="text" class="multipleMainLabelCat" id="input_${id}_mde" name="${id}_mde" placeholder="მდე">
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                                <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('block_${id}')"></i>
                            </div>
                        </div>
                    `);
                } else {
                    propertyFields.querySelector("#addFiltersBtn").insertAdjacentHTML('beforebegin', `
                        <div class="customfilters" style="display: flex; margin-left: -11px; width: 250px;" id="block_${id}">
                            <div class="custom-dropdownCat">
                                <input type="text" class="multipleMainLabelCat new-text-filter" id="input_${id}" name="${id}" placeholder="${name}">
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                                <i class="fa-solid fa-x" style="cursor: pointer;" onclick="closeFilter('block_${id}')"></i>
                            </div>
                        </div>
                    `);
                }
            }
        }

        const dropdown = document.getElementById("filtersDropdown");

        for (let i = 0; i < resProperties.length; i++) {
            const element = resProperties[i];

            let id = element["ID"];
            let name = element["NAME"];

            if (!['71', '73', '74', '61', '62', '220', '218', '219', '67', '204', '202', '217', '87', '203', '341', '342', 
                '343', '208', '87', '89', '206', '205', '228', '86', '359', '60', '130', '131', '226', '309', '85', '80', '357', 
                '124', '307', '358', '356', '380', '90', '91', '92', '93', '72'].includes(id)) {
                dropdown.innerHTML += `
                    <a href="#" id="block_${id}_x" onclick="showFilter('block_${id}')"  style="background-color: rgb(253, 212, 212);" >${name}</a>
                `;

            }
        }
    }





document.getElementById('delBtn').addEventListener('click', function () {
        // let productId = this.parentNode.previousElementSibling.children[5].children[0].innerHTML;
        let productId = document.getElementById('productId').innerHTML;
        fetch(`/rest/local/api/projects/addProductOnDeal.php?deal_id=${dealId}`).then(data => {
            return data.json();
        }).then(data => {
            if(data.status == 200){
                alert(data.message);
                // if(data["LINK"]){
                //     window.location.replace(data["LINK"]);
                // }
                location.reload();

            }
            else{
                alert(data.error);
                document.getElementById('delBtn').disabled = true;
                document.getElementById('delBtn').classList.add('disabled--btn');
            }
        }).catch((err) => {
            console.log('error:', err);
        });
    });








    document.getElementById('clean').addEventListener('click', function (e) {

        document.getElementById("startNumer").value="";
        document.getElementById("endNumer").value="";
        
        document.getElementById("startPrice").value="";
        document.getElementById("endPrice").value="";
        document.getElementById("startPriceKvm").value="";
        document.getElementById("endPriceKvm").value="";
        document.getElementById("startSqr").value="";
        document.getElementById("endSqr").value="";
        // document.getElementById("apartmentNumber").value="";
        document.getElementById("CadastralNumber").value="";
        document.getElementById("startSartul").value="";
        document.getElementById("endSartul").value="";
        // document.getElementById("projectDropdownCat").value = "15";

 
        clearAllFilters();
        // clearDropdownSelection();

        fartisType=document.getElementById('typeDropdownCatSelect');
        clearDropdownSelection(fartisType);

        condition =document.getElementById('conditionDropdownCatSelect');
        clearDropdownSelection(condition);


        statusType=document.getElementById('statusDropdownCatSelect');
        // console.log(statusType);
        clearDropdownSelection(statusType);
        CorpsDropdownCatSelect=document.getElementById('CorpsDropdownCatSelect');
        clearDropdownSelection(CorpsDropdownCatSelect);

        brokerDropdownCatSelect=document.getElementById('brokerDropdownCatSelect');
        clearDropdownSelection(brokerDropdownCatSelect);

        floorDropdownCatSelect=document.getElementById('floorDropdownCatSelect');
        clearDropdownSelection(floorDropdownCatSelect);

        bedroomDropdownCatSelect=document.getElementById('bedroomDropdownCatSelect');
        clearDropdownSelection(bedroomDropdownCatSelect);


        sideDropdownCatSelect=document.getElementById('sideDropdownCatSelect');
        clearDropdownSelection(sideDropdownCatSelect);

        setTimeout(() => {
            clickSearchButton();
        }, 500);
       
       
    });


 

    function clearDropdownSelection(dropdown) {
        

        if (!dropdown) {
            console.error("Dropdown element not found!");
            return;
        }

        // console.log("Dropdown found, changing display to 'block' temporarily...");
        
        // დროებით ვაჩვენებთ dropdown-ს
        dropdown.style.display = "block"; 

        setTimeout(() => {
            const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');
            // console.log("Found checkboxes:", checkboxes);

            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                checkbox.dispatchEvent(new Event('change')); // **ხელით გამოვიძახოთ change event**
                // console.log("Unchecked and change event dispatched:", checkbox);
            });

            setTimeout(() => {
                dropdown.style.display = "none";
                // console.log("Dropdown hidden again.");
            }, 100);

        }, 50);
    }





    function clearAllFilters() {
        // გასუფთავება new-range-filter კლასის მქონე input-ების
        document.querySelectorAll(".new-range-filter input").forEach(input => {
            input.value = "";
        });

        // გასუფთავება new-text-filter კლასის მქონე input-ების
        document.querySelectorAll(".new-text-filter").forEach(input => {
            input.value = "";
        });
    }

    function clickSearchButton() {
        // პოულობს ღილაკს id="filterBtn" არგუმენტით
        var searchBtn = document.getElementById("filterBtn");
        if (searchBtn) {
          searchBtn.click();
        } else {
        console.error("ღილაკი ვერ მოიძებნა");
        }
    }








        document.addEventListener("DOMContentLoaded", function () {
 
        });






    
    document.getElementById("projectDropdownCat").addEventListener("click", function() {
        let blockNameElements = document.querySelectorAll(".blockName");

        blockNameElements.forEach(block => {

            
            // const blockName = document.querySelector(".blockName");
            const parent = block.parentElement;

            const secChild=parent.children[1].children;

            const allHidden = Array.from(secChild).every(item => item.classList.contains("floor-item_hidden"));

            // console.log(allHidden);
            if (allHidden) {
                block.style.visibility = "hidden";
            }else{
                block.style.visibility = "visible";
            }
        });
    });


    window.addEventListener("scroll", function () {
        document.querySelectorAll(".sticky-horizontal").forEach(function (div) {
            div.style.left = window.scrollX + "px"; // ჰორიზონტალურად გაყინვა
        });
        div= document.querySelector(".sticky-horizontalDiv")
        div.style.left = (window.scrollX-20) + "px"; 
      
    
    });
    

document.addEventListener("DOMContentLoaded", function() {
    // 1. ვქმნით gtranslate_wrapper კონტეინერს და ვამატებთ body-ის თავში
    const translateContainer = document.createElement("div");
    translateContainer.className = "gtranslate_wrapper";
    document.body.insertBefore(translateContainer, document.body.firstChild);

    // 2. ვამატებთ gtranslateSettings-ს (Google Translate-ის პარამეტრები)
    const settingsScript = document.createElement('script');
    settingsScript.textContent = `
        window.gtranslateSettings = {
            "default_language": "ka",
            "languages": ["ka", "en"],
            "wrapper_selector": ".gtranslate_wrapper",
            "flag_size": 24
        };
    `;
    document.body.appendChild(settingsScript);

    // 3. ვამატებთ Google-ის ფლაგების ვიჯეტის სკრიპტს
    const gtranslateScript = document.createElement('script');
    gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
    gtranslateScript.defer = true;
    document.body.appendChild(gtranslateScript);
});




</script>


</body>

</html>