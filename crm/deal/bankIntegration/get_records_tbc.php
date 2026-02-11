<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("ამონაწერის გენერაცია(თიბისი)");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function addCIBlockElement($arForAdd, $arProps = array())
{
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Add($arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
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

if (!empty($_POST)) {
    $url = 'http://tfs.fmgsoft.ge:7780/API/FMGSoft/TBC_Movements';

    $proj=$_POST["COMPANY"];
    $fromdate=$_POST["from_date"];
    $todate=$_POST["to_date"];
    $currency=$_POST["CURRENCY"];
    $acc=$_POST["ACCOUNT"];

    $accnumber='';
    $user='';
    $pass='';
    $keycode='';
    if($proj=='OTIUMI'){
        $user='RADIUSLLC';
        $keycode='FEA4JVyX';
        $accnumber=$acc;
    }
    if($proj=='OTIUM_BATUMI'){
        $user='OTIUMDEVELOPMENTLLC';
        $keycode='HY8Jnqu9';
        $accnumber=$acc;
    }

    $list=getCIBlockElementsByFilter(array("IBLOCK_ID"=>64,"PROPERTY_PROJECT"=>$proj));
    $data = [
        'DateFrom' => $fromdate,//y-m-d
        'DateTo' => $todate,
        'AccountNumber' => $accnumber,
        'User' => $user,
        'Pass' => $list[0]["PASS"],
        'KeyCode' => $keycode,
        'Currency' => $currency
    ];

    // $data = [
    //     'DateFrom' => "2024-08-01T00:00:00",
    //     'DateTo' => "2024-08-31T00:00:00",
    //     'AccountNumber' => "GE80TB7866136050100003",
    //     'User' => "FIFIA@",
    //     'Pass' => "Fifia2024!",
    //     'KeyCode' => "WzVO3Tm9",
    //     'Currency' => "GEL"
    // ];
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $response = curl_exec($ch);
    $response=json_decode($response);

    // printArr($response);
    // printArr($data);

    if ($response === false) {
        $error = curl_error($ch);

    } else {

    }
    curl_close($ch);

    $data=$response->Data;

    // printArr($data);


    if(isset($data[0]->faultstring)){

        printArr($data[0]->faultstring);

    }else{

        foreach($data as $record){

            $identity_check=getCIBlockElementsByFilter(array("IBLOCK_ID"=>65,"PROPERTY_movementId"=>$record->movementId));

            if (empty($identity_check)) {
                if ($record->transactionType == '20' && strpos($record->description, "სესხ") === false && strpos($record->description, "101001000") === false && $record->taxpayerCode !== "405569665"  && strpos($record->description, "კონვერტაც") === false && $record->taxpayerCode !== "426551625") {

                    $arForAdd = array(
                        'IBLOCK_ID' => 65,
                        'NAME' => "ამონაწერი",
                        'ACTIVE' => 'Y',
                    );

                    $formdatearr = explode("T", $record->valueDate);

                    $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$formdatearr[0]}";


        

                    $seb = file_get_contents($url);

                    $seb = json_decode($seb);

                    $seb_currency = $seb[0]->currencies[0]->rate;

                    $amount_base = $record->amount;

                    $seb_currency = floatval(str_replace(",", ".", number_format(floatval($seb_currency), 4, ".", "")));
                    $amount_base = floatval(str_replace(",", ".", number_format(floatval($amount_base), 2, ".", "")));

                    $arPropsOld = array();

                    if ($currency == "GEL") {

                        $amount_usd = $amount_base / $seb_currency;

                        $amount_usd = floatval(str_replace(",", ".", number_format(floatval($amount_usd), 2, ".", "")));

                        $arPropsOld["AMOUNT_USD"] = $amount_usd;

                    } elseif ($currency == "USD") {

                        $amount_gel = $amount_base * $seb_currency;

                        $amount_gel = floatval(str_replace(",", ".", number_format(floatval($amount_gel), 2, ".", "")));

                        $arPropsOld["AMOUNT_GEL"] = $amount_gel;

                    }

                    $arPropsOld["movementId"] = $record->movementId;
                    $arPropsOld["externalPaymentId"] = $record->externalPaymentId;
                    $arPropsOld["debitCredit"] = $record->debitCredit;
                    $arPropsOld["valueDate"] = $record->valueDate;
                    $arPropsOld["description"] = $record->description;
                    $arPropsOld["amount"] = $record->amount;
                    $arPropsOld["currency"] = $record->currency;
                    $arPropsOld["accountNumber"] = $record->accountNumber;
                    $arPropsOld["accountName"] = $record->accountName;
                    $arPropsOld["additionalInformation"] = $record->additionalInformation;
                    $arPropsOld["documentDate"] = $record->documentDate;
                    $arPropsOld["documentNumber"] = $record->documentNumber;
                    $arPropsOld["partnerAccountNumber"] = $record->partnerAccountNumber;
                    $arPropsOld["partnerName"] = $record->partnerName;
                    $arPropsOld["partnerBankCode"] = $record->partnerBankCode;
                    $arPropsOld["partnerBank"] = $record->partnerBank;
                    $arPropsOld["partnerTaxCode"] = $record->partnerTaxCode;
                    $arPropsOld["taxpayerCode"] = $record->taxpayerCode;
                    $arPropsOld["taxpayerName"] = $record->taxpayerName;
                    $arPropsOld["operationCode"] = $record->operationCode;
                    $arPropsOld["exchangeRate"] = $record->exchangeRate;
                    $arPropsOld["partnerPersonalNumber"] = $record->partnerPersonalNumber;
                    $arPropsOld["partnerDocumentType"] = $record->partnerDocumentType;
                    $arPropsOld["partnerDocumentNumber"] = $record->partnerDocumentNumber;
                    $arPropsOld["parentExternalPaymentId"] = $record->parentExternalPaymentId;
                    $arPropsOld["statusCode"] = $record->statusCode;
                    $arPropsOld["transactionType"] = $record->transactionType;
                    $arPropsOld["GetAccountMovementsResponseIo_Id"] = $record->GetAccountMovementsResponseIo_Id;
                    $arPropsOld["ACCOUNT_CURRENCY"] = $currency;
                    $arPropsOld["NBG_RATE"] = $seb_currency;

                    $res = addCIBlockElement($arForAdd, $arPropsOld);


                }
            }
        }
    }

}


?>
<html>
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>

        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .container{
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        form {
            width: 50%;
        }

        #header_text{
            width: 100%;
            font-size: 20px;
            /*padding: 15px;*/
            display: flex;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
<div style="width: 100%;display: flex;align-items: center;justify-content: center;"><div style="display: flex;align-items: center;justify-content: center;"><img width="20%" src='https://crm.otium.ge/crm/deal/logo_tbcnew.png'></div></div>
<div id="header_text"><b>ამონაწერის გენერაცია</b></div>
<div class="container">
    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" >
        <div class="mb-3 mt-3">
            <label for="COMPANY" class="form-label">Company:</label>
            <select onchange="hideacc()" required ID="COMPANY" name="COMPANY" class="form-select">
                <option value="OTIUMI">ოტიუმი</option>
                <option value="OTIUM_BATUMI">რევერანსი - ბათუმი</option>
                <!-- <option value="LISI">დეკა ლისი</option>
                <option value="DEVELOPMENT">დეკა დეველოპმენტი</option>
                <option value="VERONA">დეკა ვერონა</option> -->
            </select>
        </div>
        <div class="mb-3 mt-3">
            <label for="CURRENCY" class="form-label">Currency:</label>
            <select onchange="hideacc()" required ID="CURRENCY" name="CURRENCY" class="form-select">
                <option selected value="USD">USD</option>
                <option value="GEL">GEL</option>
                <!-- <option value="EUR">EUR</option> -->
            </select>
        </div>
        <div class="mb-3 mt-3">
            <label for="ACCOUNT" id="acc_lable" class="form-label">Account:</label>
            <select ID="ACCOUNT" name="ACCOUNT" class="form-select">
                <!-- <option value="GE33TB7722036050100003">B და C ბლოკი</option>
                <option value="GE60TB7722036050100002">ბიზნეს ანგარიში</option>
                <option value="GE76TB7722036050100005">A ბლოკი</option> -->
                <option value="GE50TB7722036150100004">B და C ბლოკი (რეკონი)</option>
                <option value="GE76TB7722036050100005">A ბლოკი</option>
            </select>
        </div>
        <div class="mb-3 mt-3">
            <label for="from_date" class="form-label">From:</label>
            <input required type="date" class="form-control" id="from_date" placeholder="From Date" name="from_date">
        </div>
        <div class="mb-3 mt-3">
            <label for="to_date" class="form-label">To:</label>
            <input required type="date" class="form-control" id="to_date" placeholder="To Date" name="to_date">
        </div>
        <button type="submit" class="btn btn-dark mt-3">Upload</button>
    </form>
</div>

</body>
<script>
function hideacc() {
    const company = document.getElementById("COMPANY").value;
    const currency = document.getElementById("CURRENCY").value;

    const accounts = document.getElementById('ACCOUNT');
    const accounts_label = document.getElementById('acc_lable');

    let optionsHTML = "";

    if (company === "OTIUMI") {
        if (currency === "GEL") {
            optionsHTML = `
                <option value="GE33TB7722036050100003">B და C ბლოკი</option>
                <option value="GE60TB7722036050100002">ბიზნეს ანგარიში</option>
                <option value="GE76TB7722036050100005">A ბლოკი</option>
            `;
        } else if (currency === "USD") {
            optionsHTML = `
                <option value="GE50TB7722036150100004">B და C ბლოკი (რეკონი)</option>
                <option value="GE76TB7722036050100005">A ბლოკი</option>
            `;
        }

    }else{
            optionsHTML = `
                <option value="GE72TB7608236080100010">GE72TB7608236080100010</option>
                <option value="GE59TB7608236150100003">GE59TB7608236150100003</option>
            `;
    }



    


    if (optionsHTML !== "") {
        accounts.innerHTML = optionsHTML;
        accounts.style.display = "block";
        accounts_label.style.display = "block";
    } else {
        accounts.innerHTML = "";
        accounts.style.display = "none";
        accounts_label.style.display = "none";
    }
}



</script>
</html>