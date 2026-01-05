<?
ob_start();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/element.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");

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

function validateDate($date, $format = 'd/m/Y')
{
    $d = DateTime::createFromFormat($format, $date);
    // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
    return $d && $d->format($format) === $date;
}

function dateCompare($date1,$date2)
{
    $startDate_dateTime = DateTime::createFromFormat('d/m/Y', $date1);
    $endDate_dateTime = DateTime::createFromFormat('d/m/Y', $date2);
    if ($startDate_dateTime < $endDate_dateTime) {
        return true;
    }
    else return false;
}

function prepareToArchive($daricxva) {
    $archive = array();
    foreach($daricxva as $value){
        $tanxa = str_replace("|USD", "", $value["TANXA"]);
        $graph["amount"] = $tanxa;
        $graph["date"] = $value["TARIGI"];
        $graph["PLAN_TYPE"] = $value["PLAN_TYPE"];

        array_push($archive,$graph);
        CIBlockElement::Delete($value['ID']);
    }
    return $archive;
}

if(!function_exists('getDealByIDForPrice')){
    function getDealByIDForPrice($id, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
        $arDeals = array();
        $res = CCrmDeal::GetList($arSort, array("ID"=>$id), array());
        if($arDeal = $res->Fetch()){
            return $arDeal;
        } else{
            return array();
        }
    }  
}

function archiveAndDeletePaymentPlan($arrFilter,$dealID){
    $arGrafiki = getCIBlockElementsByFilter($arrFilter,$arSelect=Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"),$arSort=array("ID"=>"ASC"));
    if (sizeof($arGrafiki) > 0) {
        $archive = prepareToArchive($arGrafiki);
        $arForAdd = array(
            'IBLOCK_ID' => 25,
            'NAME' => "განვადება $dealID",
            'ACTIVE' => 'Y',
        );
        $arPropsOld = array();
        $arPropsOld["DEAL_ID"] = $dealID;
        $arPropsOld["JSON"] = json_encode($archive);
        $res = addCIBlockElement($arForAdd, $arPropsOld);
        if($res){
            $propertyValues = array();
            $propertyValues['HTML'] = '<a href="/custom/calculator/oldGraphPage.php?docID='.$res.'" target="_blank">გრაფიკი</a>';
            $element = new CIBlockElement();
            $updateResult = $element->SetPropertyValuesEx($res, 25, $propertyValues);
        }

    }
    return $arGrafiki;

}

function putCommasInNum($price){
    return number_format($price, 2, '.', ',');
}


// $docId=	"1953";


if($_GET["docID"]){
    $element = getElementByID($_GET["docID"]);
    $json= str_replace("&quot;", "\"", $element["JSON"]);
    $json= json_decode($json, true);
}else {
    try {
        $json = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}


// printArr($json);


if(is_numeric($json["dealId"])) {
    $DealData = getDealInfoByID(array("ID" => $json["dealId"]));
    $exchangeRate = $DealData["UF_CRM_1701786033562"];

    if($DealData["UF_CRM_1702019032102"] == 322){
        $transactionCurrency = "GEL";
        $prodPriceUSD = round($json["PRICE"] / $exchangeRate,2);
        $principal = round($json["PRICE"] / $exchangeRate,2);
        $prodPriceGEL = $json["PRICE"];
    }else{
        $transactionCurrency = "USD";
        $prodPriceUSD = $json["PRICE"] ;
        $principal = $json["PRICE"];
        $prodPriceGEL = round($json["PRICE"] * $exchangeRate,2);
    }


    $filterForAldGraph = array('IBLOCK_ID' => 20, "PROPERTY_DEAL" => $json["dealId"]);
    $archiveResult = archiveAndDeletePaymentPlan($filterForAldGraph, $json["dealId"]);

    $comment = "თარიღი\t-\t თანხა<br>";

    foreach ($json["data"] as $data) {
        $arForAdd = array(
            'IBLOCK_ID' => 20,
            'NAME' => 'განვადება',
            'ACTIVE' => 'Y',
        );

        if($transactionCurrency == "GEL"){
            $amount_GEL = $data["amount"];
            if($exchangeRate && is_numeric($exchangeRate)) {
                $amount_USD = round($data["amount"] / $exchangeRate, 2);
            }else{
                $amount_USD = 0;
            }
        }else{
            $amount_GEL = round($data["amount"]*$exchangeRate,2);
            $amount_USD = $data["amount"];
        }

        $principal = round($principal - $amount_USD,2);

        $arPropss = array();
        $arPropss['DEAL'] = $json["dealId"];
        $arPropss['CONTRACT_ID'] = $json["dealId"];
        $arPropss['PLAN_TYPE'] = $data["payment"];
        $arPropss['TARIGI'] = $data["date"];
        $arPropss['TANXA'] = $amount_USD . "|USD";
        $arPropss['TANXA_NUMBR'] = $amount_USD;
        $arPropss['TARIGI_NUMBR'] = intval(dateToNumbr($data["date"]));
        $arPropss['amount_GEL'] = $amount_GEL;
        $arPropss['remainingrincipal'] = $principal;
        $arPropss['PROJECT'] = $DealData["UF_CRM_1693385948133"];
        $arPropss['KORPUSI'] = $DealData["UF_CRM_1718097224965"];
        $arPropss['BINIS_NOMERI'] = $DealData["UF_CRM_1693385964548"];
        $arPropss['floor'] = $DealData["UF_CRM_1709803989"];
        $arPropss['FULL_NAME'] = $DealData["CONTACT_FULL_NAME"];
        $arPropss['ZETIPI'] = $DealData["UF_CRM_1693385992603"];
        $arPropss['KONTRAKT_DATE'] = $DealData["UF_CRM_1693398443196"];
        $arPropss['NBG'] = $DealData["UF_CRM_1701786033562"];


        $comment .= $data["date"] . "\t-\t" . $data["amount"] . "<br>";

        $elementID = addCIBlockElement($arForAdd, $arPropss);
        if (is_numeric($elementID)) {
            $result["status"] = 200;
            $result["txt"] = "გრაფიკი წარმატებით დარეგისტრირდა";
            $result["test"] = $json;
        } else {
            $result["status"] = 400;
            $result["txt"] = "გრაფიკი ვერ დარეგისტრირდა";
        }
    }

    $arrLoadProductRows = array();
    $arPush = array('PRODUCT_ID' => $json["PROD_ID"], 'PRICE' => $prodPriceUSD, 'QUANTITY' => 1);
    array_push($arrLoadProductRows, $arPush);
    $saveRes = CCrmDeal::SaveProductRows($json["dealId"], $arrLoadProductRows);
    if($json["selected_type"] == "customType"){
        $arrForAdd ["UF_CRM_1709821842972"] = "არასტანდარტული გრაფიკი";  //გადახდის ტიპი
    }else{
        $payType = getCIBlockElementsByFilter(array("ID" => $json["selected_type"]));
        $arrForAdd ["UF_CRM_1709821842972"] = $payType[0]["NAME"];      //გადახდის ტიპი
    }


    if($json["chabarebatype"]){
        $arrForAdd ["UF_CRM_1755192171036"] = $json["chabarebatype"];      //ჩაბარების ტიპი

    }


    $arrForAdd["COMMENTS"] = $comment;
    $arrForAdd ["OPPORTUNITY"] = $prodPriceUSD;      //დილის ფასი

    // პირველი შენატანის დამატება
    if(isset($json["data"][0])){
        $firstPaymentAmount = $json["data"][0]["amount"];

        // if($transactionCurrency == "GEL" && $exchangeRate && is_numeric($exchangeRate)){
        //     $firstPaymentAmount = round($firstPaymentAmount / $exchangeRate, 2);
        // }
        $arrForAdd["UF_CRM_1767011506"] = $firstPaymentAmount;        // პირველი შენატანი
        $arrForAdd["UF_CRM_1767011536"] = $json["data"][0]["date"];   // პირველი შენატანის თარიღი
    }
    else{
        $arrForAdd["UF_CRM_1767011506"] = 0;
        $arrForAdd["UF_CRM_1767011536"] = "";
    }
    $arrForAdd ["UF_CRM_1625580191451"] = $prodPriceUSD;      // დარჩენილი თანხა
    $arrForAdd ["UF_CRM_1735030244"] = putCommasInNum($prodPriceUSD);      //დილის ფასი მძიმეებით
    $arrForAdd ["UF_CRM_1709821902793"] = $prodPriceGEL;      //ფასი ლარი
    $arrForAdd ["UF_CRM_1693385814530"] = round($prodPriceUSD/$DealData["UF_CRM_1761658503260"],2);      //კვ/მ ფასი $


    if($DealData["UF_CRM_1733492965560"]>$prodPriceUSD){
        $arrForAdd ["UF_CRM_1733488113"] = $DealData["UF_CRM_1733492965560"];
    }else{
        $arrForAdd ["UF_CRM_1733488113"] = '[-------------------------]';
    }

    $Deal = new CCrmDeal();
    $resDealUpdate = $Deal->Update($json["dealId"], $arrForAdd);
    var_dump($resDealUpdate);
}
else{
    $result["status"] = 400;
    $result["txt"] = "დილი ვერ მოიძებნა";
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
