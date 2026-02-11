<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("ამონაწერის გენერაცია(თიბისი)");

//require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/product.php");
//require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getPaymentPlan($arFilter = array())
{
    $arrEl = array();
    $arElements = array();
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $arPushs["ID"] = $arFilds["ID"];
        $arPushs["DATE"] = $arProps["TARIGI"]["VALUE"];
        $arPushs["PAYMENT"] = "";
        $arPushs["PLAN"] = str_replace("|USD","",$arProps["TANXA"]["VALUE"]);
        $arPushs["TYPE"] = "PLAN";
        $arPushs["tanxa_gel"] = "";
        array_push($arElements,$arPushs);

    }

    return $arElements;
}

function getPayments($arFilter = array())
{
    $arrEl = array();
    $arElements = array();
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $arPushs = array();
        $arPushs["ID"] = $arFilds["ID"];
        $arPushs["DATE"] = $arProps["date"]["VALUE"];
        $arPushs["PAYMENT"] = str_replace("|USD","",$arProps["TANXA"]["VALUE"]);
        $arPushs["PLAN"] = "";
        $arPushs["TYPE"] = "PAYMENT";
        $arPushs["tanxa_gel"] = $arProps["tanxa_gel"]["VALUE"];

        array_push($arElements,$arPushs);

    }

    return $arElements;
}

function getDealInfoByID ($dealID,$arrSelect=array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), $arrSelect);

    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
}

function sortByDate($a, $b) {
    $dateA = DateTime::createFromFormat('d/m/Y', $a['DATE']);
    $dateB = DateTime::createFromFormat('d/m/Y', $b['DATE']);
    return $dateA <=> $dateB;
}

function value_to_name($val){

    if($val="75"){
        return "GEL";
    }elseif ($val="74"){
        return "USD";
    }else{
        return " ";
    }

}

function getCIBlockElementsByFilter($arFilter,$arSelect=Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"),$arSort=array("ID"=>"DESC"))
{
    $arElements = array();
    $res = CIBlockElement::GetList($arSort, $arFilter, false, Array("nPageSize" => 99999), $arSelect);
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

function addCIBlockElement($arForAdd, $arProps = array()) {
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Add($arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
}

function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"ASC")) {
    $arDeals = array();
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function getContactsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"ASC")) {
    $arDeals = array();
    $res = CCrmContact::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function getCompanysByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"ASC")) {
    $arDeals = array();
    $res = CCrmCompany::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

if(!empty($_POST)) {

    $post_count=count($_POST)/3;

    for($h=0;$h<$post_count;$h++) {

        $value=$_POST["VALUE_" . $h];
        $deal_id=$_POST["DEAL_" . $h];
        $payment=$_POST["PAYMENT_" . $h];

        if (!empty($value)) {

            if ($value!=="0") {

                if (!empty($deal_id)) {

                    $list_element = getCIBlockElementsByFilter(array("IBLOCK_ID" => 65, "ID" => $_POST["PAYMENT_" . $h]));

                    $arForAdd = array(
                        'IBLOCK_ID' => 25,
                        'NAME' => $list_element[0]["partnerName"] . " " . $list_element[0]["valueDate"],
                        'ACTIVE' => 'Y',
                    );

                    $gvari = ' ';

                    $true_date = explode("T", $list_element[0]["valueDate"])[0];

                    $deal = getDealsByFilter(array("ID" => $deal_id));

                    $formdatearr = explode("-", $true_date);

                    $form_tarigi = $formdatearr[2] . "/" . $formdatearr[1] . "/" . $formdatearr[0];

                    $nbg_rate = floatval($list_element[0]["NBG_RATE"]);

                    $tanxa_larshi = number_format(floatval(floatval($_POST["VALUE_" . $h]) * $nbg_rate), 2, ".", "");

                    $arPropsOld["DEAL"] = $_POST["DEAL_" . $h];
                    $arPropsOld["date"] = $form_tarigi;
                    $arPropsOld["comment"] = $list_element[0]["description"];
                    $arPropsOld["TANXA"] = $_POST["VALUE_" . $h];
                    $arPropsOld["BANK_PAYMENT_ID"] = $_POST["PAYMENT_" . $h];
                    $arPropsOld["tanxa_gel"] = $tanxa_larshi;
                    $arPropsOld["PROJECT"] = $deal[0]["UF_CRM_1693385948133"];
                    $arPropsOld["KORPUSI"] = $deal[0]["UF_CRM_1702018321416"];
                    $arPropsOld["BINIS_NOMERI"] = $deal[0]["UF_CRM_1693385964548"];
                    $arPropsOld["ZETIPI"] = $deal[0]["UF_CRM_1693385992603"];
                    $arPropsOld["ANGARISHIS_TIPI"] = $deal[0]["UF_CRM_1705413820965"];
                    $arPropsOld["KONTRAKT_DATE"] = $deal[0]["UF_CRM_1693398443196"];
                    $arPropsOld["TRANZ_TYPE"] = $deal[0]["UF_CRM_1702038078127"];
                    $arPropsOld["CURRENCY"] = value_to_name($deal[0]["UF_CRM_1705395356366"]);
                    $arPropsOld["NBG"] = $list_element[0]["NBG_RATE"];
                    $arPropsOld["FULL_NAME"] = $deal[0]["CONTACT_FULL_NAME"];
                    $arPropsOld["xelshNum"] = $deal[0]["UF_CRM_1715329001809"];

                    $res = addCIBlockElement($arForAdd, $arPropsOld);

                    if(!empty($res) && is_numeric($res)){
                        $arErrorsTmp = array();
                        $wfId = CBPDocument::StartWorkflow(
                            57,
                            array("bizproc", "CBPVirtualDocument", $res),
                            array_merge(array(), array("TargetUser" => "user_1")),
                            $arErrorsTmp
                        );

                        $wfId2 = CBPDocument::StartWorkflow(
                            200,
                            array("bizproc", "CBPVirtualDocument", $res),
                            array_merge(array(), array("TargetUser" => "user_1")),
                            $arErrorsTmp
                        );
                    }

                    ///////////// გადახდების დაჯამება /////////

                    $arFilter = array("ID" => $_POST["DEAL_" . $h]);
                    $deals = getDealsByFilter($arFilter);

                    $arFilter = array("PROPERTY_DEAL" => $_POST["DEAL_" . $h],
                        "IBLOCK_ID" => 25);
                    $payments = getCIBlockElementsByFilter($arFilter);

                    $moneyToPay = $deals[0]['OPPORTUNITY'];
                    $payedMoney = 0;

                    foreach ($payments as $singlePayment) {
                        $payedMoney = $payedMoney + $singlePayment['TANXA'];
                    }

                    $moneyLeft = $moneyToPay - $payedMoney;

                    $CCrmDeal = new CCrmDeal();
                    $upd = array(
                        "UF_CRM_1720447878" => $moneyLeft,
                        "UF_CRM_1711115555932" => $payedMoney,

                    );

                    $CCrmDeal->Update($_POST["DEAL_" . $h], $upd);

                    $sales_dealid = "D_" . $_POST["DEAL_" . $h];

                    $arFilter = array("UF_CRM_1693398065" => $sales_dealid);
                    $otherDeals = getDealsByFilter($arFilter);

                    foreach ($otherDeals as $deal) {

                        $otherDeal_ID = $deal["ID"];


                        $CCrmDeal = new CCrmDeal();
                        $upd = array(
                            "UF_CRM_1720447878" => $moneyLeft,
                            "UF_CRM_1711115555932" => $payedMoney,

                        );
                        $CCrmDeal->Update($otherDeal_ID, $upd);
                    }
                }
            }
        }
    }
}

$stage_arr=array("WON");

$lists=getCIBlockElementsByFilter(array("IBLOCK_ID"=>65));

$merge_deals=array();

$list_model=array();

$error_deals=array();

foreach ($lists as $list){
    $check_if=getCIBlockElementsByFilter(array("IBLOCK_ID"=>25,"PROPERTY_BANK_PAYMENT_ID"=>$list["ID"]));
    if(empty($check_if)) {
            $contact = "";
            $bank_amount = "";
            $inn = "";
            $name = "";
            $date = "";
            $buyer_status = "";

                if (!empty($list["taxpayerCode"])) {
                    $contact = getContactsByFilter(array("UF_CRM_1693399408936" => $list["taxpayerCode"]));
                    if (empty($contact)) {
                        $contact = getContactsByFilter(array("UF_CRM_1725356833" => $list["taxpayerCode"]));
                    }
                    $buyer_status = "contact";
                    if (empty($contact)) {
                        $contact = getCompanysByFilter(array("UF_CRM_1710889005" => $list["taxpayerCode"]));
                        $buyer_status = "company";
                    }
                }
                if ($list["ACCOUNT_CURRENCY"] == "GEL") {
                    $bank_amount_gel = $list["amount"];
                    $bank_amount_usd = $list["AMOUNT_USD"];

                } elseif ($list["ACCOUNT_CURRENCY"] == "USD") {
                    $bank_amount_gel = $list["AMOUNT_GEL"];
                    $bank_amount_usd = $list["amount"];
                }
                $inn = $list["taxpayerCode"];
                $name = $list["partnerName"];
                $date = $list["valueDate"];
                $date = explode("T", $date)[0];
            if(!empty($list["taxpayerCode"])) {

                $deals = getDealsByFilter(array("UF_CRM_1704891774" => $list["taxpayerCode"],"STAGE_ID" => $stage_arr));

                $modeled_deals = array();

                $dealmodel = array();

                foreach ($deals as $deal) {

                    $dealmodel["NAME"] = $deal["TITLE"];
                    $dealmodel["ID"] = $deal["ID"];
                    $dealmodel["OPPORTUNITY"] = $deal["OPPORTUNITY"];
                    $dealmodel["kontraktor"] = $deal["UF_CRM_1699907477758"];
                    $dealmodel["binisNom1"] = $deal["UF_CRM_1693385964548"];
                    $dealmodel["PROJECT"] = $deal["UF_CRM_1693385948133"];

                    $deal_ID =  $deal["ID"];
                    $dealData    = getDealInfoByID($deal_ID);

                    $paymentPlans = getPaymentPlan(array("IBLOCK_ID" => 29,"PROPERTY_DEAL"=>$deal_ID));
                    $payments = getPayments(array("IBLOCK_ID" => 25,"PROPERTY_DEAL"=>$deal_ID));

                    $financeArr = array_merge($paymentPlans, $payments);

                    usort($financeArr, 'sortByDate');

                    for ($i = 0; $i<count($financeArr);$i++){
                        if($i==0){
                            if($financeArr[$i]["TYPE"]=="PLAN") {
                                $financeArr[$i]["leftToPay"] = $financeArr[$i]["PLAN"];
                            }
                            else{
                                $financeArr[$i]["leftToPay"] = -$financeArr[$i]["PAYMENT"];
                            }
                        }
                 else {
                    // წინამდებარე დარჩენილი გადასახდელის მნიშვნელობა
                    $leftToPayPrevious = floatval(str_replace([' ', ','], '', $financeArr[$i-1]["leftToPay"]));

                    // თუ ჩანაწერის ტიპი არის PLAN, ვამატებთ
                    if ($financeArr[$i]["TYPE"] == "PLAN") {
                        $planCurrent = floatval(str_replace([' ', ','], '', $financeArr[$i]["PLAN"]));
                        $financeArr[$i]["leftToPay"] = round($leftToPayPrevious + $planCurrent, 2);
                    } 
                    // თუ არა, მაშინ PAYMENT-ს ვაკლებთ
                    else {
                        $paymentCurrent = floatval(str_replace([' ', ','], '', $financeArr[$i]["PAYMENT"]));
                        $financeArr[$i]["leftToPay"] = round($leftToPayPrevious - $paymentCurrent, 2);
                    }
                }
                    }
                    $data = $financeArr;
                    $today = date('m/d/Y');

                    $filteredData = array_filter($data, function($item) use ($today) {
                        $date_arr=explode("/",$item['DATE']);
                        $trueformdate=$date_arr[1]."/".$date_arr[0]."/".$date_arr[2];
                        return strtotime($trueformdate) < strtotime($today);
                    });
                    $dealmodel["LEFT_TO_PAY"] = $filteredData[count($filteredData)-1]["leftToPay"];
                    array_push($modeled_deals, $dealmodel);
                }

                if(!empty($modeled_deals)){
                    // && $list["description"] !=="სესხი" 
                    $chanaweri=array();
                    $chanaweri["CLIENT_NAME"]=$name;
                    $chanaweri["CLIENT_ID"]=$contact[0]["ID"];
                    $chanaweri["STATUS"]=$buyer_status;
                    $chanaweri["MERGE_DEALS"] = $modeled_deals;
                    $chanaweri["BANK_AMOUNT_GEL"] = $bank_amount_gel;
                    $chanaweri["BANK_AMOUNT_USD"] = $bank_amount_usd;
                    $chanaweri["NBG_RATE"] = $list["NBG_RATE"];
                    $chanaweri["CURRENCY"] = $list["ACCOUNT_CURRENCY"];
                    $chanaweri["NOMINATION"] = $list["description"];
                    $chanaweri["BENEFICIARY"] = $list["accountName"];
                    $chanaweri["DATE"] = $date;
                    $chanaweri["list_id"] = $list["ID"];
                    array_push($list_model,$chanaweri);

                }else{
                    $errordeal_model["INN"] = $inn;
                    $chanaweri["CLIENT_ID"]=$contact[0]["ID"];
                    $chanaweri["STATUS"]=$buyer_status;
                    $errordeal_model["NAME"] = $name;
                    $errordeal_model["AMOUNT_GEL"] = $bank_amount_gel;
                    $errordeal_model["AMOUNT_USD"] = $bank_amount_usd;
                    $errordeal_model["NBG_RATE"] = $list["NBG_RATE"];
                    $errordeal_model["NOMINATION"] = $list["description"];
                    $errordeal_model["BENEFICIARY"] = $list["accountName"];
                    $errordeal_model["DATE"] = $date;
                    $errordeal_model["PAYMENT"] = $list["ID"];
                    $errordeal_model["CURRENCY"] = $list["ACCOUNT_CURRENCY"];
                    array_push($error_deals, $errordeal_model);
                }
            }else{
                $errordeal_model["INN"] = $inn;
//                $chanaweri["CLIENT_ID"]=$contact[0]["ID"];
//                $chanaweri["STATUS"]=$buyer_status;
                $errordeal_model["NAME"] = $name;
                $errordeal_model["AMOUNT_GEL"] = $bank_amount_gel;
                $errordeal_model["AMOUNT_USD"] = $bank_amount_usd;
                $errordeal_model["NBG_RATE"] = $list["NBG_RATE"];
                $errordeal_model["NOMINATION"] = $list["description"];
                $errordeal_model["BENEFICIARY"] = $list["accountName"];
                $errordeal_model["DATE"] = $date;
                $errordeal_model["PAYMENT"] = $list["ID"];
                $errordeal_model["CURRENCY"] = $list["ACCOUNT_CURRENCY"];
                array_push($error_deals, $errordeal_model);
            }
    }
}

ob_end_clean();
?>
<html>
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        #container{
            margin: 10px;
        }

        .custom-dropdown {
            width: 15%;
            margin-right: 8px;
            position: relative;
            display: inline-block;
        }

        .multipleMainLabel {
            width: 100%;
            padding: 10px;
            cursor: pointer;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .multiple_select {
            width: 100%;
            display: none;
            overflow: auto;
            position: absolute;
            background-color: #fff;
            border: 1px solid #ccc;
            max-height: 500px;
            z-index: 10000;
        }
    </style>
</head>
<body>
<div style="width: 100%;display: flex;align-items: center;justify-content: center;"><div style="display: flex;align-items: center;justify-content: center;"><img width="20%" src='https://crm.otium.ge/crm/deal/logo_tbcnew.png'></div></div>
<div id="container">
    <div style="width: 20%;margin-bottom: 15px;" class="custom-dropdown" id="projectDropdown">
        <div class="projectDropdownLabel multipleMainLabel" onclick="dropdownToogle('projectDropdownSelect')">პროექტი</div>
        <div class="multiple_select" id="projectDropdownSelect">
        </div>
    </div>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="myForm">
        <table class="table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Date</th>
                <th>Nomination</th>
                <th>Beneficiary</th>
                <th>Currency</th>
                <th>Sum ₾</th>
                <th>Sum $</th>
                <th>NBG Rate</th>
                <th>Project</th>
                <th>Left to Pay</th>
                <th>Contract Number</th>
                <th>App.Number</th>
                <th>Deals</th>
                <th>Payment Value</th>
            </tr>
            </thead>
            <tbody id="tbody_data">

            </tbody>
        </table>
        <button disabled id="main_button" class="btn btn-dark" type="submit">შენახვა</button>
    </form>
    <form action="https://crm.otium.ge/crm/deal/error_deals_tbc.php" method="post" id="myForm_er">
        <table class="table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Date</th>
                <th>Nomination</th>
                <th>Beneficiary</th>
                <th>Inn</th>
                <th>Currency</th>
                <th>NBG Rate</th>
                <th>Sum ₾</th>
                <th>Sum $</th>
                <th>Amount</th>
                <th>Deal</th>
                <th></th>
            </tr>
            </thead>
            <tbody id="tbody_data_errors">

            </tbody>
        </table>
        <button disabled id="errors_button" class="btn btn-dark" type="submit">შენახვა</button>
    </form>
</div>

</body>
<script>
    let data=<?echo json_encode($list_model);?>;
    let errors=<?echo json_encode($error_deals);?>;

    var dropdowndata={
        OTIUMI:"ოტიუმი",
        OTIUM_BATUMI:"რევერანსი - ბათუმი"
    };

    console.log(data);

    function dropdownToogle(select){
        let customDropdownSelect = document.getElementById(select);
        customDropdownSelect.style.display = customDropdownSelect.style.display === "block" ? "none" : "block";
    }

    function multipleDropdownGB(id,Name) {
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
                updateLabel(id);
            });
        });

        function updateLabel(id) {

            var selectedOptions = Array.from(customDropdownCheckboxes)
                .filter(function (checkbox) {
                    return checkbox.checked;
                })
                .map(function (checkbox) {
                    return checkbox.nextSibling.textContent.trim();
                });

            var alltds = document.getElementsByClassName('hidedeals');

            for (var i = 0; i < alltds.length; i++) {

                alltds[i].style.display = "";

            }

            var alltds = document.getElementsByClassName('filtertr');

            for (var d = 0; d < alltds.length; d++) {

                    alltds[d].style.display='';
            }

            if(selectedOptions.length!==0) {
                var allowedacc = [];
                var alloweproj = [];

                for (var y = 0; y < selectedOptions.length; y++) {

                    if (selectedOptions[y] == "დეკა დიდი დიღომი") {
                        alloweproj.push('Digomi');
                    }else if(selectedOptions[y] == "დეკა ვარკეთილი"){
                        alloweproj.push('Varketili');
                    }else if(selectedOptions[y] == "დეკა გარემო"){
                        alloweproj.push('Garemo');
                    }else if(selectedOptions[y] == "დეკა ვერონა"){
                        alloweproj.push('Verona');
                    }else if(selectedOptions[y] == "დეკა ლისი"){
                        alloweproj.push('Lisi');
                    }

                }

                var alltds = document.getElementsByClassName('hidedeals');

                // console.log(alltds);

                for (var i = 0; i < alltds.length; i++) {

                    // console.log(alltds[i]);
                    // console.log(alltds[i].dataset.proj);

                    // var ben_name=alltds[i].children[0].innerText;
                    //
                    // console.log(ben_name);

                    var is_allowed=false;

                    for(var d=0;d<alloweproj.length;d++){
                        if(alltds[i].dataset.proj.includes(alloweproj[d])){
                            is_allowed=true;
                        }
                    }

                    if(is_allowed==false){
                        alltds[i].style.display='none';
                    }

                }

                var alltds = document.getElementsByClassName('filtertr');

                for (var d = 0; d < alltds.length; d++) {
                    var count_children = alltds[d].children[8].children[0]; // Получаем вложенный элемент
                    var allow_to_be = false;

                    console.log(count_children);

                    if (count_children) {
                        for (var f = 0; f < count_children.childElementCount; f++) {
                            if (count_children.children[f] && count_children.children[f].style.display !== 'none') {
                                allow_to_be = true;
                            }
                        }
                    }

                    if(allow_to_be==false){
                        alltds[d].style.display='none';
                    }

                }

            }

            let count = selectedOptions.length;
            if(count == 0){
                customDropdownLabel.textContent = Name;
            }
            else if (count > 0 && count <= 1) {
                customDropdownLabel.textContent = Name + ": " + selectedOptions.join(", ");
            }
            else if(count > 1){
                customDropdownLabel.textContent = count+ " " + Name;
            }

        }
    }

    let select = document.getElementById('projectDropdownSelect');
    for (let id of Object.keys(dropdowndata)) {
        var value = dropdowndata[id];
        select.insertAdjacentHTML('beforeend', `
                <label class="multipleLabel"><input type="checkbox" class="projectDropdownCheckbox multiple_Checkbox" value="${id}"> ${value}</label><br>`);
    }

    multipleDropdownGB("projectDropdown", "პროექტი");

    document.addEventListener('DOMContentLoaded', function () {
        const button = document.getElementById('main_button');
        const form = document.getElementById('myForm');

        // Enter-ის დაჭერისას ფორმა არ გაიგზავნოს input ველებიდან
        form.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                e.preventDefault();
            }
        });

        form.addEventListener('submit', function (e) {
            if (button.disabled) {
                e.preventDefault();
                return;
            }
            button.disabled = true;
        });

        const button_er = document.getElementById('errors_button');
        const form_er = document.getElementById('myForm_er');

        // Enter-ის დაჭერისას ფორმა არ გაიგზავნოს input ველებიდან
        form_er.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                e.preventDefault();
            }
        });

        form_er.addEventListener('submit', function (e) {
            if (button_er.disabled) {
                e.preventDefault();
                return;
            }
            button_er.disabled = true;
        });

    });

    var table=document.getElementById("tbody_data");

    var index_deals=0;

    for (const key in data) {
        if (data.hasOwnProperty(key)) {

            var back_color="#ffffff";

            if(data[key]["MERGE_DEALS"].length>1){

                var amount_go=false;
                back_color="#ecdf94"

            }else {

                var amount_go=true;
                back_color="#beedaa"

            }

            var deals_names=`<div>`;

            var paymenthtml=`<div>`;

            var dealshtml=`<div>`;

            for (a=0;a<data[key]["MERGE_DEALS"].length;a++){

                deals_names+=`<div data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" class="hidedeals" style="height: 38px;display: flex;align-items: center;"><input class="form-control" name="DEAL_${index_deals}" value="${data[key]["MERGE_DEALS"][a]["ID"]}"></div>`;

                paymenthtml+=`<input data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" hidden name="PAYMENT_${index_deals}" value="${data[key]["list_id"]}" type="text">`;
                
                if(data[key]["MERGE_DEALS"].length==1){
                            dealshtml += `<div data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" class="hidedeals"><input style="width: 200px;" class="form-control caltop" name="VALUE_${index_deals}" type="text" value="${data[key]["BANK_AMOUNT_USD"]}" ></div>`;
                }else{
                    dealshtml += `<div data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" class="hidedeals"><input style="width: 200px;" class="form-control caltop" name="VALUE_${index_deals}" type="text"  ></div>`;

                }
                index_deals++;

            }

            deals_names+=`</div>`;

            paymenthtml+=`</div>`;

            dealshtml+=`</div>`;

            var left_to_pay=`<div>`;

            var proj=`<div>`;

            var kontrakts=`<div>`;

            var binisNom=`<div>`;

            for (a=0;a<data[key]["MERGE_DEALS"].length;a++){
                left_to_pay += `<div class="hidedeals" data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" style="height: 38px;display: flex;align-items: center;"><div>${data[key]["MERGE_DEALS"][a]["LEFT_TO_PAY"]}</div></div>`;
                proj += `<div class="hidedeals" data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" style="height: 38px;display: flex;align-items: center;"><div>${data[key]["MERGE_DEALS"][a]["PROJECT"]}</div></div>`;
                kontrakts += `<div class="hidedeals" data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" style="height: 38px;display: flex;align-items: center;"><div>${data[key]["MERGE_DEALS"][a]["kontraktor"]}</div></div>`;
                binisNom += `<div class="hidedeals" data-proj="${data[key]["MERGE_DEALS"][a]["PROJECT"]}" style="height: 38px;display: flex;align-items: center;"><div>${data[key]["MERGE_DEALS"][a]["binisNom1"]}</div></div>`;
            }

            left_to_pay+=`</div>`;
            proj+=`</div>`;


            kontrakts+=`</div>`;

            binisNom+=`</div>`;

            table.innerHTML+=`
            <tr class="filtertr" style="background-color:${back_color}">
                <td style="vertical-align: middle;"><b>${data[key]["CLIENT_NAME"]}</b></td>
                <td style="vertical-align: middle;">${data[key]["DATE"]}</td>
                <td style="vertical-align: middle;width: 50px;">${data[key]["NOMINATION"]}</td>
                <td style="vertical-align: middle;width: 50px;">${data[key]["BENEFICIARY"]}</td>
                <td style="vertical-align: middle;">${data[key]["CURRENCY"]}</td>
                <td style="vertical-align: middle;">${data[key]["BANK_AMOUNT_GEL"]}</td>
                <td class="bank-amount" style="vertical-align: middle;">${data[key]["BANK_AMOUNT_USD"]}</td>
                <td style="vertical-align: middle;">${data[key]["NBG_RATE"]}</td>
                    <td style="width: 125px;vertical-align: middle;">${proj}</td>
                    <td style="width: 125px;vertical-align: middle;">${left_to_pay}</td>
                    <td style="width: 125px;vertical-align: middle;">${kontrakts}</td>
                    <td style="width: 125px;vertical-align: middle;">${binisNom}</td>
                    <td>${deals_names}</td>
                    <td>${dealshtml}</td>
                    <td>${paymenthtml}</td>
                <td><button onclick="addline_main(this)" class="btn btn-dark" type="button">+</button></td>
            </tr>
            `;
        }

    }

      function addline_main(row){
        var mainrow=row.parentElement.parentElement;

        var dealdiv=mainrow.children[12];
        var amountdiv=mainrow.children[13];
        var paymentdiv=mainrow.children[14];
        var buttondiv=mainrow.children[15];

        var lefttopaydiv=mainrow.children[9].children[0];
        var projdov=mainrow.children[8].children[0];
        var bina=mainrow.children[10].children[0];
        var kontr=mainrow.children[11].children[0];

        lefttopaydiv.insertAdjacentHTML('beforeend', `<div style="height: 38px;"></div>`);
        projdov.insertAdjacentHTML('beforeend', `<div style="height: 38px;"></div>`);
        bina.insertAdjacentHTML('beforeend', `<div style="height: 38px;"></div>`);
        kontr.insertAdjacentHTML('beforeend', `<div style="height: 38px;"></div>`);

        var paymentvalue=mainrow.children[14].querySelector('input[name^="PAYMENT_"]').value;

        dealdiv.insertAdjacentHTML('beforeend', `<div style="height: 38px;display: flex;align-items: center;"><input class="form-control" name="DEAL_${index_deals}" value=""></div>`);
        amountdiv.insertAdjacentHTML('beforeend', `<div><input style="width: 300px;" class="form-control caltop" name="VALUE_${index_deals}" type="text"></div>`);
        paymentdiv.insertAdjacentHTML('beforeend', `<input hidden name="PAYMENT_${index_deals}" value="${paymentvalue}" type="text">`);
        let btn = document.createElement("button");
        btn.type = "button";
        btn.name = "BUTTONTOP_" + index_deals;
        btn.className = "btn btn-dark";
        btn.textContent = "X";
        btn.setAttribute("onclick", `clearrow('${index_deals}')`);
        buttondiv.appendChild(btn);
        index_deals++;


        const tableRows = table.querySelectorAll('tr');
        tableRows.forEach(row => {
            row.addEventListener('input', () => {
                checkTotalSum();
            });
        });
        tableRows.forEach(row => {
            row.addEventListener('input', () => {
                limitTotalSum(row);
            });
        });

    }
    function clearrow(index){

        // var amount=document.getElementsByName('DEAL_'+index);
        var deal=document.getElementsByName('VALUE_'+index);
        var pay=document.getElementsByName('PAYMENT_'+index);
        var button=document.getElementsByName('BUTTONTOP_'+index)


        
        const input = document.querySelector(`input[name="DEAL_${index}"]`);
        if (input && input.parentElement) {
            input.parentElement.remove();
        }


        // amount[0].remove();
        deal[0].remove();
        pay[0].remove();
        button[0].remove();

    }
    function limitTotalSum(trElement) {
        const inputs = trElement.querySelectorAll('input.caltop');

        const bankAmount = parseFloat(trElement.querySelector('.bank-amount').textContent);
        let totalSum = 0;

        inputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            totalSum += value;
        });

        totalSum=parseFloat(totalSum.toFixed(2));

        if (totalSum > bankAmount) {
            // მხოლოდ აქტიური (ფოკუსირებული) ველი დააბრუნოს წინა მნიშვნელობაზე
            const activeInput = trElement.querySelector('input.caltop:focus');
            if (activeInput) {
                activeInput.value = activeInput.dataset.prevValue || "";
            }
        } else {
            // შევინახოთ მიმდინარე მნიშვნელობები როგორც წინა ვალიდური მნიშვნელობები
            inputs.forEach(input => {
                input.dataset.prevValue = input.value;
            });
        }
    }
    const tableRows = table.querySelectorAll('tr');

    tableRows.forEach(row => {
        row.addEventListener('input', () => {
            limitTotalSum(row);
        });
    });
    function checkTotalSum() {
        let allMatch = true;
        let anyFilled = false;
        tableRows.forEach(row => {
            const inputs = row.querySelectorAll('input.caltop');
            if(inputs.length==0){
            }else {
                var is_filled=false
                inputs.forEach(input => {
                    const value = input.value;
                    var trim=value.trim();
                    if (trim=="" || trim==0 || trim =="0"){
                    }else {
                        is_filled=true;
                    }

                });

                if(is_filled==true){
                    anyFilled = true;
                    const bankAmount = parseFloat(row.querySelector('.bank-amount').textContent);
                    let totalSum = 0;
                    inputs.forEach(input => {
                        const value = parseFloat(input.value) || 0;
                        totalSum += value;
                    });
                    totalSum=totalSum.toFixed(2);
                    totalSum=parseFloat(totalSum);

                    if (totalSum !== bankAmount) {
                        allMatch = false;
                    }
                }
            }
        });
        const mainButton = document.getElementById("main_button");
        mainButton.disabled = !(allMatch && anyFilled);
    }
    checkTotalSum();
    tableRows.forEach(row => {
        row.addEventListener('input', () => {
            checkTotalSum();
        });
    });
    for (var x=0;x<errors.length;x++){
        var errors_table=document.getElementById("tbody_data_errors");
        errors_table.innerHTML+=`
        <tr style="background-color: #ec8c8c">
            <td>${errors[x]["NAME"]}</td>
            <td>${errors[x]["DATE"]}</td>
            <td style="width: 75px;">${errors[x]["NOMINATION"]}</td>
            <td style="width: 50px;">${errors[x]["BENEFICIARY"]}</td>
            <td>${errors[x]["INN"]}</td>
            <td>${errors[x]["CURRENCY"]}</td>
            <td>${errors[x]["NBG_RATE"]}</td>
            <td style="vertical-align: middle;">${errors[x]["AMOUNT_GEL"]}</td>
            <td class="error_amount" style="vertical-align: middle;">${errors[x]["AMOUNT_USD"]}</td>
            <td><input class="form-control calculate" value="${errors[x]["AMOUNT_USD"]}" name="AMOUNT_${x}" type="text"></td>
            <td><input required class="form-control" name="DEAL_${x}" type="text"></td>
            <td><input hidden name="PAYMENT_${x}" value="${errors[x]["PAYMENT"]}" type="text"></td>
            <td><button onclick="addline(this)" class="btn btn-dark" type="button">+</button></td>
        </tr>
        `;
    }
    const table_err=document.getElementById("tbody_data_errors");
    const tableRows_err = table_err.querySelectorAll('tr');
    function checkTotalSum_err() {
        let allMatch = true;
        tableRows_err.forEach(row => {
            const inputs = row.querySelectorAll('input.calculate');
            if(inputs.length==0){
            }else {
                const bankAmount = parseFloat(row.querySelector('.error_amount').textContent);
                let totalSum = 0;

                inputs.forEach(input => {
                    const value = parseFloat(input.value) || 0;
                    totalSum += value;
                });
                totalSum=totalSum.toFixed(2);
                totalSum=parseFloat(totalSum);
                if (totalSum !== bankAmount) {
                    allMatch = false;
                }
            }
        });
        const mainButton = document.getElementById("errors_button");
        mainButton.disabled = !allMatch;
    }

    checkTotalSum_err();
    tableRows_err.forEach(row => {
        row.addEventListener('input', () => {
            checkTotalSum_err();
        });
    });

    function limitTotalSum_err(trElement) {
        const inputs = trElement.querySelectorAll('input.calculate');
        const bankAmount = parseFloat(trElement.querySelector('.error_amount').textContent);
        let totalSum = 0;

        inputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            totalSum += value;
        });
        totalSum=parseFloat(totalSum.toFixed(2));
        if (totalSum > bankAmount) {
            const activeInput = trElement.querySelector('input.calculate:focus');
            if (activeInput) {
                activeInput.value = activeInput.dataset.prevValue || "";
            }
        } else {
            inputs.forEach(input => {
                input.dataset.prevValue = input.value;
            });
        }
    }
    tableRows_err.forEach(row => {
        row.addEventListener('input', () => {
            limitTotalSum_err(row);
        });
    });

    function addline(row){
        var mainrow=row.parentElement.parentElement;
        var amountdiv=mainrow.children[9];
        var dealdiv=mainrow.children[10];
        var paymentdiv=mainrow.children[11];
        var paymentvalue=mainrow.children[11].children[0].value;
        amountdiv.insertAdjacentHTML('beforeend', `<input class="form-control calculate" value="" name="AMOUNT_${x}" type="text">`);
        dealdiv.insertAdjacentHTML('beforeend', `<input required class="form-control" name="DEAL_${x}" type="text">`);
        paymentdiv.insertAdjacentHTML('beforeend', `<input style="display: none;" hidden name="PAYMENT_${x}" value="${paymentvalue}" type="text">`);
        ++x;
        tableRows_err.forEach(row => {
            row.addEventListener('input', () => {
                limitTotalSum_err(row);
            });
        });
    }
</script>
</html>