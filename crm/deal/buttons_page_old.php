<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

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

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
};

global $DB;

function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();

    return $res["NAME"]." ".$res["LAST_NAME"];
}
function formatNumber($value) {
    return number_format($value, 2, '.', ',');
}

function getGeorgianDate() {
    $months = [
        1 => "იანვარი",
        2 => "თებერვალი",
        3 => "მარტი",
        4 => "აპრილი",
        5 => "მაისი",
        6 => "ივნისი",
        7 => "ივლისი",
        8 => "აგვისტო",
        9 => "სექტემბერი",
        10 => "ოქტომბერი",
        11 => "ნოემბერი",
        12 => "დეკემბერი"
    ];

    $day = date("d");
    $month = date("n");
    $year = date("Y");

    return "$day " . $months[$month] . " $year";
}

function grafikisGeneracia1($danarti_content, $fasdaklebuli) {

    $grafiki = "
    <table style='border-collapse: collapse;align-items: center;margin: 0 auto; table-layout: fixed; width: 60%;'> 
        <tHead>
            <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen;width:40px;'><b>№</b></th>
            <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen;width:calc((60% - 40px)/3);'><b>" . htmlspecialchars(mb_convert_encoding("გადახდის თარიღი", 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</b></th>
            <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen;width:calc((60% - 40px)/3);'><b>" . htmlspecialchars(mb_convert_encoding("თანხა", 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</b></th>
            <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen;width:calc((60% - 40px)/3);'><b>" . htmlspecialchars(mb_convert_encoding("ნაშთი", 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</b></th>
        </tHead>";

    $n = 0;

    foreach ($danarti_content as $story) {
        $n++;

        $TARIGI = $story["TARIGI"];
        $TANXA_NUMBR = $story["TANXA_NUMBR"];

        $darchenilitanxa = $fasdaklebuli - $TANXA_NUMBR;
        $fasdaklebuli = $darchenilitanxa;

        if ($n === count($danarti_content)) {
            $darchenilitanxa = 0;
        }

        $TANXA_NUMBR = formatNumber($TANXA_NUMBR);
        $darchenilitanxa = formatNumber(round($darchenilitanxa, 2));

        $grafiki .= "<tr>
                        <td style='border: 1px solid black;font-size:13.5px;text-align: center;font-family: sylfaen;'>$n</td>       
                        <td style='border: 1px solid black;font-size:13.5px;text-align: center;font-family: sylfaen;'>$TARIGI</td>
                        <td style='border: 1px solid black;font-size:13.5px;text-align: center;font-family: sylfaen;'>$TANXA_NUMBR</td>
                        <td style='border: 1px solid black;font-size:13.5px;text-align: center;font-family: sylfaen;'>$darchenilitanxa</td>
                     </tr>";
    }

    $grafiki .= "</table>";
    return $grafiki;
}



function grafikisGeneracia1_eng($danarti_content, $fasdaklebuli) {

    $grafiki = "
    <table style='border-collapse: collapse; table-layout: fixed; width:60%; margin:0 auto;'>
        <thead>
            <tr>
                <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:50px;'><b>№</b></th>
                <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>Payment Date</b></th>
                <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>Amount Due</b></th>
                <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>Remaining principal $</b></th>
            </tr>
        </thead>";

    $n = 0;

    foreach ($danarti_content as $story) {
        if (!isset($story["TARIGI"]) || !isset($story["TANXA_NUMBR"])) {
            continue;
        }

        $n++;

        $TARIGI = htmlspecialchars($story["TARIGI"]);
        $TANXA_NUMBR = $story["TANXA_NUMBR"];

        $darchenilitanxa = round($fasdaklebuli - $TANXA_NUMBR, 2);
        $fasdaklebuli = $darchenilitanxa;

        if ($n === count($danarti_content)) {
            $darchenilitanxa = 0;
        }

        $TANXA_NUMBR = formatNumber($TANXA_NUMBR);
        $darchenilitanxa = formatNumber($darchenilitanxa);

        $grafiki .= "<tr>
                        <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$n</td>
                        <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$TARIGI</td>
                        <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$TANXA_NUMBR</td>
                        <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$darchenilitanxa</td>
                     </tr>";
    }

    $grafiki .= "</table>";
    return $grafiki;
}

function grafikisGeneracia($danarti_content){

    $grafiki = "
      <table style='border-collapse: collapse; table-layout: fixed; width:60%; margin:0 auto;'> 
          <tHead>
              <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:50px;'><b>№</b></th>
              <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>გადახდის თარიღი</b></th>
              <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>თანხა</b></th>
              <th style='border: 1px solid black;font-size:13.5px;font-family: sylfaen; width:calc((100% - 50px)/3);'><b>ნაშთი</b></th>
          </tHead>";

    $count=0;

    foreach ($danarti_content["data"] as $story){

        if ($story["amount"] != 0) {

            $count++;
            $n=$story["payment"];
            $TARIGI=$story["date"];
            $TANXA_NUMBR=$story["amount"];
            $leftToPay=$story["leftToPay"];
            $grafiki .=  "<tr>
                            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$count</td>       
                            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$TARIGI</td>
                            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$TANXA_NUMBR</td>
                            <td style='border: 1px solid black;font-size:13.5px;text-align:center;font-family: sylfaen;'>$leftToPay</td>
                         </tr>";

        }

    }

    $grafiki .= "</table>";
    return $grafiki;
}


function grafikisGeneracia_eng($danarti_content) {
    $grafiki = "
      <table style='border-collapse: collapse; table-layout: fixed; width:60%; margin:0 auto;'> 
          <thead>
              <tr>
                  <th style='border: 1px solid black; font-size:13.5px; font-family: sylfaen; width:50px;'><b>№</b></th>
                  <th style='border: 1px solid black; font-size:13.5px; font-family: sylfaen; width:calc((100% - 50px)/3);'><b>Payment Date</b></th>
                  <th style='border: 1px solid black; font-size:13.5px; font-family: sylfaen; width:calc((100% - 50px)/3);'><b>Amount Due</b></th>
                  <th style='border: 1px solid black; font-size:13.5px; font-family: sylfaen; width:calc((100% - 50px)/3);'><b>Left To Pay</b></th>
              </tr>
          </thead>
          <tbody>";

    $count=0;

    foreach ($danarti_content["data"] as $story) {
        if ($story["amount"] != 0) {
            $count++;
            $n = $story["payment"];
            $TARIGI = $story["date"];
            $TANXA_NUMBR = $story["amount"];
            $leftToPay = $story["leftToPay"];

            $grafiki .= "
                <tr>
                    <td style='border: 1px solid black; text-align: center;'>$count</td>
                    <td style='border: 1px solid black; text-align: center;'>$TARIGI</td>
                    <td style='border: 1px solid black; text-align: center;'>$TANXA_NUMBR</td>
                    <td style='border: 1px solid black; text-align: center;'>$leftToPay</td>
                </tr>";
        }
    }

    $grafiki .= "</tbody></table>";
    return $grafiki;
}




function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        $arContact["EMAIL"] = $MAIL["VALUE"];
        return $arContact;
    }
    return $arContact;
}

function getCompanyInfo($contactId) {
    $arContact = array();
    $res = CCrmCompany::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'COMPANY','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'COMPANY','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        $arContact["EMAIL"] = $MAIL["VALUE"];
        return $arContact;
    }
    return $arContact;
}

function getDealInfo($dealID) {
    $arDeal = array();
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
    return $arDeal;
}


function getDealProds ($dealID) {
    $prods = CCrmDeal::LoadProductRows($dealID);
    $products = [];
    foreach ($prods as $prod) {
        $arFilter = array(
            "ID" => $prod["PRODUCT_ID"]
        );
        $each = getCIBlockElementsByFilter($arFilter);
        $price = CPrice::GetBasePrice($prod["PRODUCT_ID"]);
        $each[0]["PRICE"] = $price["PRICE"];
        $each[0]["RAODENOBA"] = $prod["QUANTITY"];

        array_push($products, $each);
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

function getDealInfoByContact1($contactID) {
    $resContract = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactID), array());

    $phone=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'WORK', "ELEMENT_ID" => $contactID))->Fetch();

    $resArray = array();
    if($arContact = $resContract->Fetch()){
        $arContact["PHONE_NUM"] = $phone["VALUE"];
        return $arContact;
    }
}

function getSpaInfo($arFilter, $typeId) {
    $factory = Service\Container::getInstance()->getFactory($typeId);
    if (!$factory) die("Factory not found for type ID $typeId");
    $spaInfo = [];
    $allItems = $factory->getItems([
        'select' => ['*'],
        'filter' => $arFilter
    ]);
    foreach ($allItems as $item) $spaInfo[] = $item->getData();
    return $spaInfo;
}


$filesarr=array();

$dbRes = $DB->query('select * from b_disk_object where PARENT_ID=37');

while($object = $dbRes->Fetch()) {
    $file_struct["NAME"]=$object["NAME"];
    $file_struct["ID"]=$object["ID"];
    array_push($filesarr,$file_struct);

}


$empty_get=false;
$error_code="";

$popup_mode='nopop';

$dealid=$_GET["dealid"];

$spaID=$_GET["spaID"];

$filtered_files_full = array();

function processFileArray($filesarr) {
    $result = array();
    foreach ($filesarr as $key => $value) {
        if(!isset($value["NAME"])) continue;
        
        $explode_array = explode("$", $value["NAME"]);
        
        if(count($explode_array) >= 3) {
            $value["PIPE"] = $explode_array[0];
            $value["LANG"] = $explode_array[1];
            $value["NAME"] = $explode_array[2];
            $result[] = $value;
        }
    }
    return $result;
}

if($dealid){
    $Product=getDealProds($dealid);
    $deal = getDealInfo($dealid);
    $full_arr = processFileArray($filesarr);
}else if(isset($_GET["popup"])){
    // ...
    $full_arr = processFileArray($filesarr);
}else{
    $full_arr = processFileArray($filesarr);
}




function addCIBlockElement($arForAdd, $arProps = array()) {
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Add($arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
}

if(!empty($_POST)){
    $popup_mode=$_POST['popup'];
    if($popup_mode=='ispop'){

        $today = date("d/m/Y");

        $status = "";

        if (!empty($_POST["docs"])) {

            $doc_id = $_POST["docs"];

            $file_type = $_POST["type"];


            if ($file_type == "pdf") {

                $status = true;

            } elseif ($file_type == "docx") {

                $status = false;

            }

            $tech_contact = getContactInfo(4521);
            $tech_company = getCompanyInfo(23);
            $deal = getDealInfo(3764);

            foreach ($tech_contact as $key => $value) {

                $tech_contact[$key] = "";

            }

            foreach ($tech_company as $key => $value) {

                $tech_company[$key] = "";

            }

            foreach ($deal as $key => $value) {

                $deal[$key] = "";

            }

                $deal["CONTACT_ARR"] = $tech_contact;

                $deal["COMPANY_ARR"] = $tech_company;

                $deal["COMPANY_ARR_OLD"] = $tech_company;

                $deal["CONTACT_ARR_OLD"] = $tech_contact;

            $outputArray_COMPANY = array();

            foreach ($deal["COMPANY_ARR"] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {

                        if ($value !== "0") {
                            $value = "";
                        }

                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_COM$',
                        'VarValue' => $value
                    );

                    $outputArray_COMPANY[] = $subArray;
                }
            }

            $outputArray_CONTACT = array();

            foreach ($deal["CONTACT_ARR"] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_USER$',
                        'VarValue' => $value
                    );

                    $outputArray_CONTACT[] = $subArray;
                }
            }

            $outputArray_CONTACT_old = array();

            foreach ($deal["CONTACT_ARR_OLD"] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_OLD_CON$',
                        'VarValue' => $value
                    );

                    $outputArray_CONTACT_old[] = $subArray;
                }
            }

            $outputArray_COMPANY_old = array();

            foreach ($deal["COMPANY_ARR_OLD"] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_OLD_COM$',
                        'VarValue' => $value
                    );

                    $outputArray_COMPANY_old[] = $subArray;
                }
            }

            $outputArray = array();

            foreach ($deal as $key => $value) {

                if (is_array($value)) {

                } else {

                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '$',
                        'VarValue' => $value
                    );

                    $outputArray[] = $subArray;
                }
            }

            $fullarr = $outputArray;

            $fullarr = array_merge($fullarr, $outputArray_CONTACT);

            $fullarr = array_merge($fullarr, $outputArray_COMPANY);

            $fullarr = array_merge($fullarr, $outputArray_CONTACT_old);

            $fullarr = array_merge($fullarr, $outputArray_COMPANY_old);

            $date_arr_table = array(
                "VarName" => 'table_var',
                "VarValue" => '',
                "VarType" => 'T'
            );

            array_push($fullarr, $date_arr_table);

            $date_arr_table = array(
                "VarName" => 'english_table',
                "VarValue" => '',
                "VarType" => 'T'
            );

            array_push($fullarr, $date_arr_table);

            $date_arr = array(
                "VarName" => '$TODAY_DATE$',
                "VarValue" => $today
            );

            array_push($fullarr, $date_arr);

            $date_arr_table = array(
                "VarName" => 'grapik_geo',
                "VarValue" => '',
                "VarType" => 'T'
            );

            array_push($fullarr, $date_arr_table);

            $dbRes = $DB->query('SELECT * FROM b_disk_object WHERE PARENT_ID = 37 AND ID = ' . $doc_id);

            while ($object = $dbRes->Fetch()) {

                $fileId = $object["FILE_ID"];

                $name = explode('$', $object["NAME"])[5];

                $name = explode('.', $name)[0];

                $id = $object["ID"];

                $filePath = CFile::GetPath($fileId);

                $filePath = $_SERVER["DOCUMENT_ROOT"] . $filePath;

                $fileData = file_get_contents($filePath);

                $base64Data = base64_encode($fileData);

                if ($id == $doc_id) {
                    $jsonarray = array(
                        "FileData" => $base64Data,
                        "FileName" => "ხელშეკრულება.docx",
                        "Convert" => $status,
                        "jsonVars" => $fullarr,
                    );
                }


                $jsonstring = json_encode($jsonarray);

                $url = 'http://tfs.fmgsoft.ge:7799/API/FMGSoft/Admin/GetPDFFromWord';

                $ch = curl_init($url);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonstring);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonstring),
                ));

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    echo 'cURL error: ' . curl_error($ch);
                } else {
                    curl_close($ch);


                    ob_end_clean();
                    if ($file_type == "pdf") {
                        header('Content-Disposition: attachment; filename="' . $name . '.pdf" ');
                    } elseif ($file_type == "docx") {
                        header('Content-Disposition: attachment; filename="' . $name . '.docx" ');
                    }

                    echo $response;
                }

            }

        }

    }else {
        $today = date("d/m/Y");
        $status = "";

        if (!empty($_POST["docs"])) {

            $doc_id = $_POST["docs"];

            $file_type = $_POST["type"];


            if ($file_type == "pdf") {

                $status = true;

            } elseif ($file_type == "docx") {

                $status = false;

            }

        }

        $deal_id = $_POST["deal_id"];

        $products = getDealProds($deal_id);


        $deal = getDealInfo($deal_id);

        $new_owner_contact = array();

        $old_owner_contact = array();

        $old_owner_company = array();


        if (!empty($deal["UF_CRM_1745316640"])) {
            $mergedealId = $deal["UF_CRM_1745316640"][0];

            $mergedeal = getDealInfo($mergedealId);

        }


        if (!empty($deal["UF_CRM_1657805299"])) {

            foreach ($deal["UF_CRM_1657805299"] as $key => $value) {

                array_push($new_owner_contact, getContactInfo($value));


            }

        }

        if (!empty($deal["UF_CRM_1710484037"])) {

            foreach ($deal["UF_CRM_1710484037"] as $key => $value) {

                $explode_arr = explode("_", $value);

                if ($explode_arr[0] == "CO") {

                    array_push($old_owner_company, getCompanyInfo($explode_arr[1]));

                } elseif ($explode_arr[0] == "C") {

                    array_push($old_owner_contact, getContactInfo($explode_arr[1]));

                }

            }

        }

        $contactIds = \Bitrix\Crm\Binding\DealContactTable::getDealContactIDs($deal["ID"]);

        $resContractArrIDInfo = array();

        foreach ($contactIds as $thisContactID) {
            $resContractArrIDInfo[] = getContactInfo($thisContactID);
        }

        $company = getCompanyInfo($deal["COMPANY_ID"]);

        $tech_contact = getContactInfo(4521);
        $tech_company = getCompanyInfo(23);

        foreach ($tech_contact as $key => $value) {

            $tech_contact[$key] = "";

        }

        foreach ($tech_company as $key => $value) {

            $tech_company[$key] = "";

        }

        $combinedArray = [];
        foreach ($resContractArrIDInfo as $infoArray) {
            foreach ($infoArray as $key => $value) {
                $value = ($value === '' || $value === null) ? str_repeat(' ', 11) : $value;

                if (!isset($combinedArray[$key])) {
                    // Если первое значение не пустое, записываем его
                    if ($value !== '') {
                        $combinedArray[$key] = $value;
                    }
                } else {
                    // Если первое значение уже есть, но текущее пустое — добавляем пробелы
                    if ($value === str_repeat(' ', 11) && trim($combinedArray[$key]) === '') {
                        continue; // Оба пустые — пропускаем
                    }

                    $combinedArray[$key] .= "/" . $value;
                }
            }
        }


        $deal["CONTACT_ARR"] = $combinedArray;

        if (empty($deal["CONTACT_ARR"])) {

            $deal["CONTACT_ARR"] = $tech_contact;

        }

        $deal["COMPANY_ARR"] = $company;

        if (empty($deal["COMPANY_ARR"])) {

            $deal["COMPANY_ARR"] = $tech_company;

        }

        if (count($resContractArrIDInfo) == 2) {

            $deal["DOUBLE_CONTACT_INFO"] = $resContractArrIDInfo;

        } elseif (count($resContractArrIDInfo) == 3) {

            $deal["TRIPLE_CONTACT_INFO"] = $resContractArrIDInfo;

        }

        $combinedArray = [];
            foreach ($resContractArrIDInfo as $infoArray) {
                foreach ($infoArray as $key => $value) {
                    if (!isset($combinedArray[$key])) {
                        $combinedArray[$key] = $value;
                    } else {
                        $combinedArray[$key] .= "," . $value;
                    }
                }
            }

        $combinedArray_old_company = [];
        foreach ($old_owner_company as $infoArray) {
            foreach ($infoArray as $key => $value) {
                if (!isset($combinedArray_old_company[$key])) {
                    $combinedArray_old_company[$key] = $value;
                } else {
                    $combinedArray_old_company[$key] .= "," . $value;
                }
            }
        }

        $combinedArray_old_contact = [];
        foreach ($old_owner_contact as $infoArray) {
            foreach ($infoArray as $key => $value) {
                if (!isset($combinedArray_old_contact[$key])) {
                    $combinedArray_old_contact[$key] = $value;
                } else {
                    $combinedArray_old_contact[$key] .= "," . $value;
                }
            }
        }

        $combinedArray_new_contact = [];
        foreach ($new_owner_contact as $infoArray) {
            foreach ($infoArray as $key => $value) {
                if (!isset($combinedArray_new_contact[$key])) {
                    $combinedArray_new_contact[$key] = $value;
                } else {
                    $combinedArray_new_contact[$key] .= "," . $value;
                }
            }
        }

        $deal["COMPANY_ARR_OLD"] = $combinedArray_old_company;

        if (empty($deal["COMPANY_ARR_OLD"])) {

            $deal["COMPANY_ARR_OLD"] = $tech_company;

        }

        $deal["CONTACT_ARR_OLD"] = $combinedArray_old_contact;

        if (empty($deal["CONTACT_ARR_OLD"])) {

            $deal["CONTACT_ARR_OLD"] = $tech_contact;

        }

        $deal["CONTACT_ARR_NEW"] = $combinedArray_new_contact;

        if (empty($deal["CONTACT_ARR_NEW"])) {

            $deal["CONTACT_ARR_NEW"] = $tech_contact;

        }

        $proj = $deal["UF_CRM_1693375490453"];

        $codes = getCIBlockElementsByFilter(array("IBLOCK_ID" => 33, "PROPERTY_PROJECT_NAME" => "ბოტანიკო"));

        $outputArray_COMPANY = array();

        foreach ($deal["COMPANY_ARR"] as $key => $value) {
            if (is_array($value)) {

            } else {
                if (empty($value)) {

                    if ($value !== "0") {
                        $value = "";
                    }

                }

                $subArray = array(
                    'VarName' => '$' . $key . '_COM$',
                    'VarValue' => $value
                );

                $outputArray_COMPANY[] = $subArray;
            }
        }

        $outputArray_CONTACT = array();

        foreach ($deal["CONTACT_ARR"] as $key => $value) {
            if (is_array($value)) {

            } else {
                if (empty($value)) {
                    if ($value !== "0") {
                        $value = "";
                    }
                }

                $subArray = array(
                    'VarName' => '$' . $key . '_USER$',
                    'VarValue' => $value
                );

                $outputArray_CONTACT[] = $subArray;
            }
        }

        $outputArray_CONTACT_old = array();

        foreach ($deal["CONTACT_ARR_OLD"] as $key => $value) {
            if (is_array($value)) {

            } else {
                if (empty($value)) {
                    if ($value !== "0") {
                        $value = "";
                    }
                }

                $subArray = array(
                    'VarName' => '$' . $key . '_OLD_CON$',
                    'VarValue' => $value
                );

                $outputArray_CONTACT_old[] = $subArray;
            }
        }

        $outputArray_CONTACT_new = array();

        foreach ($deal["CONTACT_ARR_NEW"] as $key => $value) {
            if (is_array($value)) {

            } else {
                if (empty($value)) {
                    if ($value !== "0") {
                        $value = "";
                    }
                }

                $subArray = array(
                    'VarName' => '$' . $key . '_NEW_CON$',
                    'VarValue' => $value
                );

                $outputArray_CONTACT_new[] = $subArray;
            }
        }

        $outputArray_COMPANY_old = array();

        foreach ($deal["COMPANY_ARR_OLD"] as $key => $value) {
            if (is_array($value)) {

            } else {
                if (empty($value)) {
                    if ($value !== "0") {
                        $value = "";
                    }
                }

                $subArray = array(
                    'VarName' => '$' . $key . '_OLD_COM$',
                    'VarValue' => $value
                );

                $outputArray_COMPANY_old[] = $subArray;
            }
        }

        $outputArray_CODES = array();

        foreach ($codes as $key => $value) {

            if (empty($value["TEXT"])) {
                if ($value !== "0") {
                    $value["TEXT"] = "";
                }
            }

            $subArray = array(
                'VarName' => '$' . $value["NAME"] . '$',
                'VarValue' => htmlspecialchars_decode($value["TEXT"])
            );

            $outputArray_CODES[] = $subArray;
        }

        if (count($resContractArrIDInfo) == 2) {

            $double_contact = array();

            foreach ($deal["DOUBLE_CONTACT_INFO"][0] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_USER_1$',
                        'VarValue' => $value
                    );

                    $double_contact[] = $subArray;
                }
            }

            foreach ($deal["DOUBLE_CONTACT_INFO"][1] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_USER_2$',
                        'VarValue' => $value
                    );

                    $double_contact[] = $subArray;
                }
            }

        } elseif (count($resContractArrIDInfo) == 3) {

            $triple_contact = array();

            foreach ($deal["DOUBLE_CONTACT_INFO"][0] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_USER_1$',
                        'VarValue' => $value
                    );

                    $triple_contact[] = $subArray;
                }
            }

            foreach ($deal["DOUBLE_CONTACT_INFO"][1] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_USER_2$',
                        'VarValue' => $value
                    );

                    $triple_contact[] = $subArray;
                }
            }

            foreach ($deal["DOUBLE_CONTACT_INFO"][2] as $key => $value) {
                if (is_array($value)) {

                } else {
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }

                    $subArray = array(
                        'VarName' => '$' . $key . '_USER_3$',
                        'VarValue' => $value
                    );

                    $triple_contact[] = $subArray;
                }
            }

        }



        $outputArray = array();

        foreach ($deal as $key => $value) {

            if (is_array($value)) {

            } else {

                if (empty($value)) {
                    if ($value !== "0") {
                        $value = "";
                    }
                }

                $subArray = array(
                    'VarName' => '$' . $key . '$',
                    'VarValue' => $value
                );

                $outputArray[] = $subArray;
            }
        }


        $outputArray2 = array();

        if(!empty($deal["UF_CRM_1745316640"])){
            foreach ($mergedeal as $key => $value) {

                if (is_array($value)) {
    
                } else {
    
                    if (empty($value)) {
                        if ($value !== "0") {
                            $value = "";
                        }
                    }
    
                    $subArray = array(
                        'VarName' => '$' . $key . '_2$',
                        'VarValue' => $value
                    );
    
                    $outputArray2[] = $subArray;
                }
            }
    

        }



    

        $fullarr = array_merge($outputArray, $outputArray_CODES);

        $fullarr = array_merge($fullarr, $outputArray_CONTACT);

        $fullarr = array_merge($fullarr, $outputArray_COMPANY);

        $fullarr = array_merge($fullarr, $outputArray_CONTACT_old);

        $fullarr = array_merge($fullarr, $outputArray_CONTACT_new);//axali mesakutre

        $fullarr = array_merge($fullarr, $outputArray_COMPANY_old);

        $fullarr = array_merge($fullarr, $outputArray2);



        if (count($resContractArrIDInfo) == 2) {

            $fullarr = array_merge($fullarr, $double_contact);

        } elseif (count($resContractArrIDInfo) == 3) {

            $fullarr = array_merge($fullarr, $triple_contact);


        }

        foreach($fullarr as $key => $value){

            if($value["VarName"]=='$UF_CRM_1733485628918$'){
                if($value["VarValue"]== "3210"){
                    $fullarr[$key]["VarValue"]="საქართველოს ბანკი";
                } elseif ($value["VarValue"] == "3211") {
                    $fullarr[$key]["VarValue"] = "თი-ბი-სი ბანკი";
                }
            }

        }

    

        // printArr($fullarr);

        $date_arr = array(
            "VarName" => '$TODAY_DATE$',
            "VarValue" => $today
        );

        array_push($fullarr, $date_arr);

        $date_arr_table = array(
            "VarName" => '$DATE_WORD$',
            "VarValue" => getGeorgianDate(),
        );

        array_push($fullarr, $date_arr_table);

        $dbRes = $DB->query('SELECT * FROM b_disk_object WHERE PARENT_ID = 37 AND ID = ' . $doc_id);

        while ($object = $dbRes->Fetch()) {

            $fasdaklebuli = $deal['OPPORTUNITY'];


        //     $arFilter = array("PROPERTY_DEAL" => $deal_id);
        //     $grafikiJson = getCIBlockElementsByFilter($arFilter);

        //     $arFilter = array(
        //         "IBLOCK_ID" => 42, 
        //         "PROPERTY_DEAL" => $deal_id,
        //         "NAME" => "გრაფიკის ცვლილება"
        //     );
        //     $grafikisElements = getCIBlockElementsByFilter($arFilter);

        //     $arFilter1 = array(
        //         "IBLOCK_ID" => 42, 
        //         "PROPERTY_DEAL" => $deal_id,
        //     );
        //     $grafikisElements1 = getCIBlockElementsByFilter($arFilter1);


            
        // if (!empty($grafikisElements1)) {
        //     usort($grafikisElements1, function($a, $b) {
        //         $dateA = DateTime::createFromFormat('d/m/Y H:i:s', $a["DATE_CREATE"]);
        //         $dateB = DateTime::createFromFormat('d/m/Y H:i:s', $b["DATE_CREATE"]);
        //         return $dateB->getTimestamp() - $dateA->getTimestamp();
        //     });

        //     $json1 = str_replace("&quot;", "\"", $grafikisElements1[0]["JSON"]);
        //     $grafikiArr1 = json_decode($json1, true);


        //     $priceJson=$grafikiArr1["PRICE"];
        //     $formattedPriceJson = number_format($priceJson, 2, '.', ',');

        //     $priceJsonOld=$grafikiArr1["oldPrice"];
        //     $formattedPriceJsonOld = number_format($priceJsonOld, 2, '.', ',');


        //     if (!empty($formattedPriceJson)) {

        //         $date_arr_table = array(
        //             "VarName" => 'priceJson',
        //             "VarValue" => $formattedPriceJson,
            
        //         );

        //         array_push($fullarr, $date_arr_table);

        //     } else {
        //         $date_arr_table = array(
        //             "VarName" => 'priceJson',
        //             "VarValue" => '',
            
        //         );

        //         array_push($fullarr, $date_arr_table);
        //     }

        //     if (!empty($formattedPriceJsonOld)) {

        //         $date_arr_table = array(
        //             "VarName" => 'priceOldJson',
        //             "VarValue" => $formattedPriceJsonOld,
        
        //         );

        //         array_push($fullarr, $date_arr_table);

        //     } else {
        //         $date_arr_table = array(
        //             "VarName" => 'priceOldJson',
        //             "VarValue" => '',

        //         );

        //         array_push($fullarr, $date_arr_table);
        //     }


        // }

        // if (!empty($grafikisElements)) {

        //     // usort($grafikisElements, function($a, $b) {
        //     //     return strtotime($b["DATE_CREATE"]) - strtotime($a["DATE_CREATE"]);
        //     // });

        //     usort($grafikisElements, function($a, $b) {
        //         $dateA = DateTime::createFromFormat('d/m/Y H:i:s', $a["DATE_CREATE"]);
        //         $dateB = DateTime::createFromFormat('d/m/Y H:i:s', $b["DATE_CREATE"]);
        //         return $dateB->getTimestamp() - $dateA->getTimestamp();
        //     });


        //     // ყველაზე ახალი ელემენტი
        //     $dadasturebuligrafiki2 = $grafikisElements;
        // } else {
        //     $dadasturebuligrafiki2 = null; // ან სხვა ლოგიკა თუ არ არსებობს ჩანაწერები
        // }


        //     $arFilter = array("IBLOCK_ID" => 24, "PROPERTY_DEAL" => $deal_id);
        //     $dadasturebuligrafiki = getCIBlockElementsByFilter($arFilter);



        //     if ($doc_id == 6328 ) {
        //         if ($dadasturebuligrafiki2) {

        //             $json = str_replace("&quot;", "\"", $dadasturebuligrafiki2[0]["JSON"]);
        //             $grafikiArr = json_decode($json, true);

        //             $HEADER_JSON = str_replace("&quot;", "\"", $dadasturebuligrafiki2[0]["HEADER_JSON"]);
        //             $grafikiArrprice = json_decode($HEADER_JSON, true);
        //             $price = $grafikiArrprice["price"];
        //             // printArr($grafikiArr);
    
        //             $grafikiTable = grafikisGeneracia_eng($grafikiArr);
        //             $grafikiTableGEO = grafikisGeneracia($grafikiArr);
    
        //         }


        //         if (!empty($price)) {

        //             $date_arr_table = array(
        //                 "VarName" => 'price',
        //                 "VarValue" => $price,
        //                 "VarType" => 'T'
        //             );

        //             array_push($fullarr, $date_arr_table);

        //         } else {
        //             $date_arr_table = array(
        //                 "VarName" => 'price',
        //                 "VarValue" => '',
        //                 "VarType" => 'T'
        //             );

        //             array_push($fullarr, $date_arr_table);
        //         }



        //         if (!empty($dadasturebuligrafiki2)) {

        //             $date_arr_table = array(
        //                 "VarName" => 'grapik_geo',
        //                 "VarValue" => $grafikiTableGEO,
        //                 "VarType" => 'T'
        //             );

        //             array_push($fullarr, $date_arr_table);

        //         } else {
        //             $date_arr_table = array(
        //                 "VarName" => 'grapik_geo',
        //                 "VarValue" => '',
        //                 "VarType" => 'T'
        //             );

        //             array_push($fullarr, $date_arr_table);
        //         }

        //     }else{
        //         if ($dadasturebuligrafiki) {
        //             $danarti_content = array();
        //             foreach ($dadasturebuligrafiki as $singleGrafik) {
    
        //                 $tanxaExploded = explode("|", $singleGrafik["TANXA"]);
    
        //                 $tanxaNum = $tanxaExploded[0];
    
        //                 $darchTanxa = $darchTanxa - $tanxaNum;
    
        //                 $darchTanxa = round($darchTanxa, 2);
    
        //                 $arPush["N"] = $nomeri;
        //                 $arPush["TARIGI"] = $singleGrafik["TARIGI"];
        //                 $arPush["TANXA_NUMBR"] = $tanxaNum;
    
        //                 $nomeri++;
        //                 array_push($danarti_content, $arPush);
        //             }
        //             $grafikiTable = grafikisGeneracia1_eng($danarti_content, $fasdaklebuli);
        //             $grafikiTableGEO = grafikisGeneracia1($danarti_content, $fasdaklebuli);
        //         } elseif ($grafikiJson) {
    
        //             $grafikicount = count($grafikiJson) - 1;
        //             $json = str_replace("&quot;", "\"", $grafikiJson[$grafikicount]["JSON"]);
        //             $grafikiArr = json_decode($json, true);
    
        //             $grafikiTable = grafikisGeneracia_eng($grafikiArr);
        //             $grafikiTableGEO = grafikisGeneracia($grafikiArr);
    
        //         }
                


        //     }

            $fileId = $object["FILE_ID"];

            $name = explode('$', $object["NAME"])[5];

            $name = explode('.', $name)[0];

            $id = $object["ID"];

            $filePath = CFile::GetPath($fileId);

            $filePath = $_SERVER["DOCUMENT_ROOT"] . $filePath;

            $fileData = file_get_contents($filePath);

            $base64Data = base64_encode($fileData);

            if ($id == $doc_id) {
                $jsonarray = array(
                    "FileData" => $base64Data,
                    "FileName" => "ხელშეკრულება.docx",
                    "Convert" => $status,
                    "jsonVars" => $fullarr,
                );
            }

            $jsonstring = json_encode($jsonarray);

            $client_name = $deal["CONTACT_ARR"]["FULL_NAME"];

            if (empty($client_name)) {
                $client_name = $deal["COMPANY_ARR"]["TITLE"];
            }

            // $url = 'http://tfs.fmgsoft.ge:7799/API/FMGSoft/Admin/GetPDFFromWordS';

            // $ch = curl_init($url);

            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($ch, CURLOPT_POST, true);
            // curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonstring);
            // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            //     'Content-Type: application/json',
            //     'Content-Length: ' . strlen($jsonstring),
            // ));

            // $response = curl_exec($ch);

            // $fileData = base64_decode($response);

            // if ($fileData === false) {
            //     die("Error Base64");
            // }

            // if (curl_errno($ch)) {
            //     echo 'cURL error: ' . curl_error($ch);
            // } else {
            //     curl_close($ch);

            //     $arForAdd = array(
            //         'IBLOCK_ID' => 51,
            //         'NAME' => 'ლოგი',
            //         'ACTIVE' => 'Y',
            //     );

            //     $today_log = date('d/m/Y H:i:s');

            //     $arPropsOld = array();
            //     $arPropsOld["USER"] = $user_id;
            //     $arPropsOld["DATE"] = $today_log;
            //     $arPropsOld["DOC"] = $name;

            //     $res = addCIBlockElement($arForAdd, $arPropsOld);
            //     ob_end_clean();

            //     if ($file_type == "pdf") {
            //         header('Content-Type: application/pdf');
            //         header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '.pdf"; filename*=UTF-8\'\'' . rawurlencode($name) . '.pdf');
            //         header("Content-Length: " . strlen($fileData));
            //     } elseif ($file_type == "docx") {
            //         header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            //         header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '.docx"; filename*=UTF-8\'\'' . rawurlencode($name) . '.docx');
            //         header("Content-Length: " . strlen($fileData));
            //     }

            //     echo $fileData;
            // }

            $url = 'http://tfs.fmgsoft.ge:7799/API/FMGSoft/Admin/GetPDFFromWordS';

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n" .
                                "Content-Length: " . strlen($jsonstring) . "\r\n",
                    'content' => $jsonstring,
                    'ignore_errors' => true
                ]
            ];

            $context  = stream_context_create($options);
            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                die("Error connecting to API");
            }

            $fileData = base64_decode($response);
            if ($fileData === false) {
                die("Error Base64");
            }

            // $arForAdd = [
            //     'IBLOCK_ID' => 51,
            //     'NAME' => 'ლოგი',
            //     'ACTIVE' => 'Y',
            // ];

            // $today_log = date('d/m/Y H:i:s');

            // $arPropsOld = [];
            // $arPropsOld["USER"] = $user_id;
            // $arPropsOld["DATE"] = $today_log;
            // $arPropsOld["DOC"]  = $name;

            // $res = addCIBlockElement($arForAdd, $arPropsOld);

            ob_end_clean();

            // ფაილის დაბრუნება
            if ($file_type == "pdf") {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '.pdf"; filename*=UTF-8\'\'' . rawurlencode($name) . '.pdf');
                header("Content-Length: " . strlen($fileData));
            } elseif ($file_type == "docx") {
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '.docx"; filename*=UTF-8\'\'' . rawurlencode($name) . '.docx');
                header("Content-Length: " . strlen($fileData));
            }


        }

    } 
}

if($NotAuthorized) {
    $USER->Logout();
}
else{
    $USER->Authorize($user_id);
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>დოკუმენტების ჩამოტვირთვა</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #f8f9fa;
    }
    #maincontent {
      margin-top: 100px;
    }
    .form-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      padding: 30px;
      max-width: 800px;
      /* margin: auto; */
      margin: 50px;
    }
    .divider {
      width: 100%;
      height: 1px;
      background-color: #dee2e6;
      margin: 25px 0;
    }
    .form-select, button {
      border-radius: 10px;
    }



    .buttonDiv {
            
        display: flex;
        justify-content: center;
        /* margin-top: 20px; */
    }

    .buttonDoc {
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: white;
        font-size: 16px;
        font-weight: 600;
        border: none;
        border-radius: 12px;
        padding: 12px 28px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: 150px;
        margin-top: 40px;
    }

    .buttonDoc:hover {
    background: linear-gradient(135deg, #45a049, #3d8b40);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
    }

    .buttonDoc:active {
    transform: translateY(0);
    box-shadow: 0 3px 8px rgba(76, 175, 80, 0.2);
    }

        .gtranslate_wrapper{
            margin-left: 450px;
        }
  </style>
</head>
<body>

<div id="maincontent" class="maincontent">
  <div class="form-card">
    <h4 class="mb-4 text-center fw-semibold">📄 დოკუმენტების ჩამოტვირთვა</h4>

    <form method="post" class="d-flex flex-column gap-4">

      <!-- Pipeline select -->
      <div style="display: none;">
        <label for="pipeline" class="form-label fw-semibold">Pipeline</label>
        <select onchange="change_pipe(this)" id="pipeline" class="form-select w-auto">
          <option value="SALE">გაყიდვები</option>
          <option value="AFTER_SALE">After Sale</option>
          <option value="დიზაინერი">დიზაინერი</option>
        </select>
      </div>

      <div class="divider"></div>

      <!-- Main row -->
      <div class="d-flex flex-wrap align-items-center gap-3">
        <input name="deal_id" id="deal_id" hidden>
        <input name="popup" id="popup" hidden>

        <div>
          <label for="language" class="form-label fw-semibold">ენა</label>
          <select id="language" required class="form-select" onchange="filter_documents()">
            <option value="geo">GEO</option>
            <option value="eng">ENG</option>
          </select>
        </div>

        <div class="flex-grow-1">
          <label for="docs" class="form-label fw-semibold">დოკუმენტი</label>
          <select required name="docs" id="docs" class="form-select">
            <option value="" disabled selected>აირჩიეთ დოკუმენტი</option>
          </select>
        </div>

        <div>
          <label for="format" class="form-label fw-semibold">ფორმატი</label>
          <select name="type" id="format" required class="form-select">
            <option value="docx">Word</option>
            <option value="pdf">PDF</option>
          </select>
        </div>

        <div class="buttonDiv">
          <button type="submit" class="buttonDoc">
            📥 ჩამოტვირთვა
          </button>
        </div>
      </div>

    </form>
  </div>
</div>

</body>
<script>



    let full_arr = <?php echo json_encode($full_arr); ?>;
    let deal_id = <?php echo json_encode($dealid); ?>;

    let get = <?php echo json_encode($empty_get); ?>;
    let code = <?php echo json_encode($error_code); ?>;
    let Product = <?php echo json_encode($Product[0]); ?>;
    let pop_up = <?php echo json_encode($popup_mode); ?>;
    let products = <?php echo json_encode($products); ?>;
    let deal = <?php echo json_encode($deal); ?>;

    full_arr.push(full_arr.splice(1, 1)[0]);



    function change_pipe(select) {
        var selectedPipeline = select.value;
        filter_documents();
    }

    function filter_documents() {
        var selectedLanguage = document.getElementById('language').value;
        var selectedPipeline = document.getElementById('pipeline').value;
        var docsSelect = document.getElementById('docs');

        // Clear current options
        docsSelect.innerHTML = '<option value="" disabled selected>დოკუმენტი</option>';

        console.log(full_arr);
        console.log(selectedLanguage);
        console.log(selectedPipeline);
        var filtered = full_arr.filter(function(item) {
            // შეამოწმეთ რომ item არ არის null და აქვს საჭირო properties
            if (!item || item === null) {
                return false;
            }
            
            if (!item["LANG"] || !item["PIPE"]) {
                return false;
            }
            
            return (
                item["LANG"].toLowerCase() === selectedLanguage &&
                (item["PIPE"] === selectedPipeline || item["PIPE"] === "ყველა")
            );
        });

        console.log(filtered);
        filtered.sort(function(a, b) {
            var nameA = (a && a["NAME"]) ? a["NAME"] : "";
            var nameB = (b && b["NAME"]) ? b["NAME"] : "";
            return nameA.localeCompare(nameB);
        });

        // Append options
        filtered.forEach(function(item) {
            if (item && item["ID"] && item["NAME"]) {
                docsSelect.innerHTML += `<option value="${item["ID"]}">${item["NAME"]}</option>`;
            }
        });

        // If no options were added
        if (docsSelect.options.length <= 1) {
            docsSelect.innerHTML += '<option disabled>No documents available</option>';
        }
    }

    if (get) {
        alert(code);
        var content = document.getElementById('maincontent');
        content.innerHTML = '';
    } else {
        var deal_inp = document.getElementById('deal_id');
        if (deal_id) {
            deal_inp.value = deal_id;
        } else {
            deal_inp.value = ''; 
        }

        var pop_inp = document.getElementById('popup');
        pop_inp.value = pop_up;

        filter_documents();
    }


    

    
        setTimeout(() => {
            // დავამატოთ GTranslate-ის პარამეტრები
            const settingsScript = document.createElement('script');
            settingsScript.textContent = `
                window.gtranslateSettings = {
                    "default_language": "ka",
                    "languages": ["ka", "en", "ru"],
                    "wrapper_selector": ".gtranslate_wrapper",
                    "flag_size": 24
                };
            `;
            document.body.appendChild(settingsScript);

            // დავამატოთ თვითონ თარგმანის სკრიპტი
            const gtranslateScript = document.createElement('script');
            gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
            gtranslateScript.defer = true;
            document.body.appendChild(gtranslateScript);

            // ვიპოვოთ რეზერვაციის ფორმის ელემენტი
            const reservationForm = document.querySelector('.maincontent');
            if (reservationForm) {
                // შევქმნათ თარგმანის HTML
                const translateHtml = document.createElement('div');
                translateHtml.className = 'gtranslate_wrapper';

                // ჩავსვათ რეზერვაციის ფორმის ზემოთ
                reservationForm.parentNode.insertBefore(translateHtml, reservationForm);
            }
        }, 3000);

</script>
</html>