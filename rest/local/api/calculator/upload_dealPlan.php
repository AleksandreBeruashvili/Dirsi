<?php
//4:02
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Shuchkin\SimpleXLSX;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);
require_once $_SERVER["DOCUMENT_ROOT"].'/custom/simplexlsx/src/SimpleXLSX.php';

global $USER;

if($USER->GetID()){
    $NotAuthorized=false;
    $user_id=$USER->GetID();
    $USER->Authorize(1);

}
else{
    $NotAuthorized=true;
    $USER->Authorize(1);
}

function randomString($n){
    $characters = '0123456789abcdifghigklmnopqrstuvwxyzABCDIFGHIJKLMNOPQRSTUVWXYZ';
    $str = '';
    for($i=0;$i<$n;$i++){
        $index = rand(0,strlen($characters)-1);
        $str .= $characters["$index"];
    }
    return $str;
}
function getDateTimeForFolderName(){
    $str = date("Ymdhisa");
    return $str;
}


function addCIBlockElement($arForAdd, $arProps = array())
{
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Add($arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
}

function dateFormat($date){
    $timestamp = strtotime($date); // Convert the string to a Unix timestamp
    $newDateFormat = date("d/m/Y", $timestamp);
    return $newDateFormat;

}

function dateNumber($date){
    $timestamp = strtotime($date); // Convert the string to a Unix timestamp
    $newDateFormat = date("Ymd", $timestamp);
    return $newDateFormat;

}


function getDealNBG($dealID) {
    $arDeal = array();
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());
    if($arDeal = $res->Fetch()){
        return $arDeal["UF_CRM_1701786033562"];
    }
    return 0;
}

function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}
$success = array();


$file = isset($_FILES["planFile"]) ? $_FILES["planFile"] : null;
$filePath = '';
$rowN = 1;
$errors = array();
$notification = array();
$notification1 = array();

if (!is_dir('xlsxFiles')) {
    mkdir('xlsxFiles');
}
if ($file && strlen($file["tmp_name"])) {
    $filePath = 'xlsxFiles/' . getDateTimeForFolderName() . '/' . $file["name"];
    mkdir(dirname($filePath));

    move_uploaded_file($file['tmp_name'], $filePath);
}
$xlxsRows = null;
if ($xlsx = SimpleXLSX::parse($filePath)) {
    $xlxsRows = $xlsx->rows();
} else {
    $errors[] = SimpleXLSX::parseError();
}

$fullAmount = 0;
$arrDATA = array();
$arrPlan = array();
if (empty($errors)) {
    if (count($xlxsRows) > 0) {
        $dealID = $_GET["dealid"];
        if (is_numeric($dealID) && $dealID > 0) {
            foreach ($xlxsRows as $row) {
                if (is_numeric($dealID) && $dealID > 0) {
                    $data = array();
                    $tanxa = round(floatval($row[1]), 2);
                    if ($tanxa > 0) {
                        $data["date"] = dateFormat($row[0]);
                        $data["amount"] = $tanxa;
                        $fullAmount += $tanxa;
                        $arrPlan[] = $data;
                    }
                }
            }
            if(count($arrPlan)){
                $count = 1;
                $data["leftToPay"] = $fullAmount;
                foreach ($arrPlan as $plan){
                    $data["payment"] = $count;
                    $data["dateWithFirstDay"] = $plan["date"];
//                                            $data["date"] = dateWorkingDays($data["$lastPayDate"]);
                    $data["date"] = $plan["date"];
                    $data["amount"] = round($plan["amount"], 2);
                    $data["leftToPay"] = round($data["leftToPay"] - $plan["amount"], 2);
                    $arrDATA[] = $data;
                    $count++;
                }
                $result["status"] = 200;
                $result["result"] = $arrDATA;
                $result["PRICE"] = $fullAmount;
            }else{
                $result["status"] = 400;
                $result["errorTXT"] = "ფაილი ცარიელია ან არასწორი ფორმატით არის ატვირთული!";
            }
        }else{
            $result["status"] = 400;
            $result["errorTXT"] = "ხელშეკრულების ნომერი არასწორია!";
        }
    } else {
        $result["status"] = 400;
        $result["errorTXT"] = "ფაილი არასწორია!";
    }
}else{
    $result["status"] = 400;
    $result["errorTXT"] = "ფაილი არასწორია!";
    $result["res"] = $errors;
}

if($NotAuthorized) {
    $USER->Logout();
}
else{
    $USER->Authorize($user_id);
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

echo json_encode($result,JSON_UNESCAPED_UNICODE);