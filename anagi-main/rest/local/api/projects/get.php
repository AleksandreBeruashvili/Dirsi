<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
ob_end_clean();
$APPLICATION->SetTitle("Title");

function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}
function getCIBlockElementsByFilter($arFilter = array()) {
    $arElements = array();
//    $arSelect = Array("ID","IBLOCK_ID","NAME","DATE_ACTIVE_FROM","PROPERTY_*");
    $arSelect = Array();
    $res = CIBlockElement::GetList(Array("_ZTMQU4"=>"ASC"), $arFilter, false, Array("nPageSize"=>9999999), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}


function getContactNameByID ($id) {
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $id), array());

    if($arContact = $res->Fetch()){
        // do something
        return $arContact["FULL_NAME"];
    }
}

function getDealTitle ($dealID) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array("TITLE"));
    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal["TITLE"];
    }

}


function getApartments($project,$dealData) {
    $arFilter = array(
        "IBLOCK_ID" => 14,
        "IBLOCK_SECTION_ID" => $project
    );
    $res = getCIBlockElementsByFilter($arFilter);
    $floors = array();
    $apartments = array();
    $products = array();
    $etazhebi = array();
    $sadarbazo=array();

    if (sizeof($res) > 0) {
        foreach ($res as $element) {
            if ($element["_PZZ695"] != "დაშლილი") {
                $price = CPrice::GetBasePrice($element["ID"]);
                $image = CFile::GetPath($element['binis_naxazi']);
                if ($image) {
                    $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image;
                } else {
                    $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
                }

                $image2 = CFile::GetPath($element['binis_gegmareba']);
                if ($image2) {
                    $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image2;
                } else {
                    $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
                }

                $image3 = CFile::GetPath($element['render_3D']);
                if ($image3) {
                    $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image3;
                } else {
                    $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
                }

                if (!(in_array($element["_0D6DJ2"], $sadarbazo))) {
                    array_push($sadarbazo, $element["_0D6DJ2"]);
                }

                $owner = "";
                if ($element["OWNER_ID_J7R134"]) {
                    $asistentID = $element["OWNER_ID_J7R134"];
                    $asistentName = getContactNameByID($element["OWNER_ID_J7R134"]);
                    $owner = "<a href='/company/personal/user/$asistentID/'>$asistentName</a>";
                }

                $apartment = array(
                    "sadarbazo" => $element["_0D6DJ2"],         //სადარბაზო-->
                    "rooms" => $element["__6DS6KB"],         //ოთახების რაოდენობა-->
                    "balcony" => $element["BALCONY_KVM"],         //საზაფხულო ფართი ჯამში-->
                    "prodType" => $element["PRODUCT_TYPE"]." N ",
                    "apartmentID" => $element["ID"],
                    "price" => $price["PRICE"],
                    "totalArea" => $element["AREA"],
                    "CATEGORY" => $element["CATEGORY"],
                    "pricePerSqm" => $element["KVM_PRICE"],
                    "projectName" => $element["_51DT4E"],
                    "status" => $element["_PZZ695"] ?: "თავისუფალი",
                    "model" => $element["__6PG2EC"],
                    "floor" => $element["FLOOR"],
                    "blockNumber" => $element["_FHXUWH"],
                    "apartmentNumber" => $element["NUMBER"],
                    "image" => $image,
                    "image2" => $image2,
                    "image3" => $image3,
                    "ReservationPeriod" => $element["ReservationPeriod"],
                    
                );
                $apartment["additional"] = array(
                    "სველი წერტილების რაოდენობა" => $element["__URKKQ6"],         //სველი წერტილების რაოდენობა-->
                    "საცხოვრებელი ფართი" => $element["_2_SXFPZ8"] . " მ²",        //საცხოვრებელი ფართი (მ2)-->
                    "აივანი 1" => $element["_1_8XQEI4"] . " მ²",        //აივანი 1-->
                    "აივანი 2" => $element["_2_716NVG"] . " მ²",        //აივანი 2-->
                    "აივანი 3" => $element["_3_LQ4EYF"] . " მ²",        //აივანი 3-->
                    "ტერასა 1" => $element["_1_REO2GQ"] . " მ²",        //ტერასა 1-->
                    "ტერასა 2" => $element["_2_ISLYDB"] . " მ²",        //ტერასა 2-->
                    "ხედი / მხარე" => $element["__MRIPWZ"],               //ხედი / მხარე-->
                    "იზოლირებული სამზარეულო" => $element["_2_YQTJGB"] . " მ2",        //იზოლირებული სამზარეულო (მ2)-->
                    "მისაღები ოთახის ფართი" => $element["_2_IAK72W"] . " მ2",        //მისაღები ოთახის ფართი (მ2)-->
                    "სტუდიოს ფართი" => $element["__HIY5LX"] . " მ2",         //სტუდიოს ფართი-->
                    "ჩაბარების პირობა" => $element["__I9ET1C"],               //ჩაბარების პირობა-->
                    "ჰოლი 1" => $element["_1_XNTQF7"] . " მ2",              //ჰოლი 1-->
                    "ჰოლი 2" => $element["_2_W5SU8Y"] . " მ2",         //ჰოლი 2-->
                    "საძინებელი 1" => $element["_1_0MT88R"] . " მ2",         //საძინებელი 1-->
                    "საძინებელი 2" => $element["_2_9KFFLP"] . " მ2",         //საძინებელი 2-->
                    "საძინებელი 3" => $element["_3_2O92T8"] . " მ2",         //საძინებელი 3-->
                    "საძინებელი 4" => $element["_4_8OFUKJ"] . " მ2",         //საძინებელი 4-->
                    "საძინებელი 5" => $element["_5_FV14WH"] . " მ2",         //საძინებელი 5-->
                    "საძინებელი 6" => $element["_6_MNZ5TK"] . " მ2",         //საძინებელი 6-->
                    "საძინებელი 7" => $element["_7_3LQP1Z"] . " მ2",         //საძინებელი 7-->
                    "საძინებლების ჯამური კვადრატულობა" => $element["__ZMTN7Y"] . " მ2",         //საძინებლების ჯამური კვადრატულობა-->
                    "WC 1" => $element["WC_1_EB3ZXO"] . " მ2",         //WC 1-->
                    "WC 2" => $element["WC_2_FWSNKJ"] . " მ2",         //WC 2-->
                    "WC 3" => $element["WC_3_8O8C41"] . " მ2",         //WC 3-->
                    "WC 4" => $element["WC_4_YG2U67"] . " მ2",         //WC 4-->
                    "WC 5" => $element["WC_5_L3956K"] . " მ2",         //WC 5-->
                    "სველი წერტილების ჯამური კვადრატულობა" => $element["__U8V1IL"] . " მ2",    //სველი წერტილების ჯამური კვადრატულობა
                    "სამრეცხაო ოთახი" => $element["__29KYZN"] . " მ2",          //სამრეცხაო ოთახი
                    "გარდერობი 1" => $element["_1_CGD5GC"] . " მ2",         //გარდერობი 1
                    "გარდერობი 2" => $element["_2_OHATU8"] . " მ2",         //გარდერობი 2
                    "გარდერობი 3" => $element["_3_TUIZMK"] . " მ2",         //გარდერობი 3
                    "გარდერობი 4" => $element["_4_6APOYH"] . " მ2",         //გარდერობი 4
                    "გარდერობი 5" => $element["_5_EWJU1J"] . " მ2",         //გარდერობი 5
                    "მისამართი" => $element["_X06V85"],                //მისამართი
                    "მესაკუთრე" => $owner,                //მესაკუთრე
                );
                if ($element["QUEUE"]){
                    $queueArr = explode("|" , $element["QUEUE"]);
                    $apartment["additional"]["რიგი"] = "";
                    foreach ($queueArr as $queue){
                        $daelId = str_replace("|","",$queue);
                        $daelId = str_replace("!","",$daelId);
                        $daelId = str_replace("?","",$daelId);
                        $dealTittle=getDealTitle($daelId);
                        $apartment["additional"]["რიგი"] .= "<a href='/crm/deal/details/$daelId/'>$dealTittle</a><br>";
                    }
                }

                array_push($apartments, $apartment);
            }
        }
        foreach ($apartments as $apartment) {
            if (!array_key_exists("floor_" . $apartment["floor"], $floors)) {
                $floors["floor_" . $apartment["floor"]] = array();
            }
            array_push($floors["floor_" . $apartment["floor"]], $apartment);
        }
        foreach ($floors as $key => $floor) {
            $etazhebi[floatval(str_replace("floor_","",$key))]= $floor;
        }
    }
    ksort($etazhebi);
    $result["apartments"]=$etazhebi;



    $result["sadarbazo"]=$sadarbazo;
    sort($result["sadarbazo"]);
    return $result;
}


function getDealInfoByID ($dealID) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());

    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
}


function getDealProd($dealData){

    $result["hasProduct"]=false;
    $result["PRODUCT_ID"]=Null;
    $result["stage"]=false;

    if(!empty($dealData)) {
        $stageId = $dealData["STAGE_ID"];
        if ($stageId == "NEW") {
        //    $stageId == "NEW" ||
            $result["hasProduct"]=true;
            $result["PRODUCT_ID"]=Null;
            $result["stage"] = false;
        }



        $prods = CCrmDeal::LoadProductRows($dealData["ID"]);

        foreach ($prods as $prod) {
            
            $result["hasProduct"] = true;
            
            $result["PRODUCT_ID"] = $prod["PRODUCT_ID"];

            $result["stage"] = true;

        }



        // if($stageId == "NEW" || $stageId == "UC_H1SIAS" ){
        //     // $result["hasProduct"]=false;
        //     $result["stage"] = true;
        //     // $result["PRODUCT_ID"]=Null;
        // }else{
        //     $result["stage"] = false;
        // }


        if($stageId == "NEW"){
            // $result["hasProduct"]=false;
            $result["stage"] = false;
            // $result["PRODUCT_ID"]=Null;
        }else{
           
            $result["stage"] = true;
        }

    }

    return $result;
}

function groupeByFloor($array){
    $outputArray = array_reduce($array, function ($result, $item) {
        $floor = $item["61"];
        $result[$floor][] = $item;
        return $result;
    }, array());
    return $outputArray;
}

function getCurlContents($url){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Use only for testing; enable in production
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Set timeout

    $response = curl_exec($ch);

    if ($response === false) {
        return "cURL Error: " . curl_error($ch);
    }

    curl_close($ch);
    return $response;
}

$domain = "http://localhost";

$get = $_GET;
$resArray = array();
if (!empty($get["project"])) {
    $projectId = $get["project"];
    $dealData = getDealInfoByID($_GET["DEAL_ID"]);
    // $apartments = json_decode(file_get_contents("$domain/rest/local/api/product/getProducts.php?project=$projectId"), true);
    $products = json_decode(file_get_contents("$domain/rest/local/api/projects/get2.php?project=$projectId"), true);
    $fields = json_decode(file_get_contents("$domain/rest/local/api/product/getInventoryHeader.php"), true);
    $hasProduct = getDealProd($dealData);
    if (sizeof($products) > 0) {
        $resArray["status"] = 200;
        // $resArray["apartments"] = groupeByFloor($apartments);
        $resArray["products"] = $products;
        $resArray["fields"] = $fields;
        $resArray["sadarbazo"] = array(1);
        $resArray["hasProduct"] = $hasProduct["hasProduct"];
        $resArray["PRODUCT_ID"] = $hasProduct["PRODUCT_ID"];
        $resArray["stage"] = $hasProduct["stage"];

    }
    else {
        $resArray["status"] = 404;
        $resArray["error"] = "Not Found";
    }
}
else {
    $resArray["status"] = 400;
    $resArray["error"] = "Bad Request";
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);





// Function to create or find contact
// function createOrFindContact($name, $email, $phone) {
//     // Try to find existing contact
//     $arFilter = array();
//     if (!empty($email)) {
//         $arFilter["EMAIL"] = $email;
//     }
//     if (!empty($phone)) {
//         $arFilter["PHONE"] = $phone;
//     }
    
//     if (!empty($arFilter)) {
//         $existingContact = getContactByFilter($arFilter, $phone, $email);
//         if ($existingContact) {
//             return $existingContact["ID"];
//         }
//     }
    
//     // Create new contact
//     $arFields = array(
//         "NAME" => $name,
//         "OPENED" => "Y",
//         "ASSIGNED_BY_ID" => 1,
//     );
    
//     $contact = new CCrmContact(false);
//     $contactId = $contact->Add($arFields, true, array("CURRENT_USER" => 1));
    
//     if ($contactId) {


                
//         if($phone){
//             $fieldMulti = new \CCrmFieldMulti();

//             $dbFieldMulti = \CCrmFieldMulti::GetList(
//                 [],
//                 [
//                     'ENTITY_ID' => 'CONTACT',
//                     'TYPE_ID' => 'PHONE',
//                     'ELEMENT_ID' => $contactId,
//                 ]
//             );

//             while ($field = $dbFieldMulti->Fetch()) {
//                 $fieldMulti->Delete($field['ID']);
//             }

//             $newPhoneData = [
//                 'ENTITY_ID' => 'CONTACT',
//                 'ELEMENT_ID' => $contactId,
//                 'TYPE_ID' => 'PHONE',
//                 'VALUE_TYPE' => 'WORK',
//                 'VALUE' => $phone,
//             ];

//             $result2 = $fieldMulti->Add($newPhoneData);
//         }


//         if($email){
//             $fieldMulti = new \CCrmFieldMulti();

//             $dbFieldMulti = \CCrmFieldMulti::GetList(
//                 [],
//                 [
//                     'ENTITY_ID' => 'CONTACT',
//                     'TYPE_ID' => 'EMAIL',
//                     'ELEMENT_ID' => $contactId,
//                 ]
//             );

//             while ($field = $dbFieldMulti->Fetch()) {
//                 $fieldMulti->Delete($field['ID']);
//             }

//             $newPhoneData = [
//                 'ENTITY_ID' => 'CONTACT',
//                 'ELEMENT_ID' => $contactId,
//                 'TYPE_ID' => 'EMAIL',
//                 'VALUE_TYPE' => 'WORK',
//                 'VALUE' => $email,
//             ];

//             $result3 = $fieldMulti->Add($newPhoneData);

//         }
//         return $contactId;
//     }
    
//     return false;
// }