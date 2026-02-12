<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealInfoByID ($dealID,$arrSelect=array()) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), $arrSelect);

    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal;
    }
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

//        $arPushs["ID"] = $arFilds["ID"];
//        $arPushs["DATE"] = $arProps["TARIGI"]["VALUE"];
//        $arPushs["PAYMENT"] = "";
//        $arPushs["PAYMENT_Gel"] = "";
//        $arPushs["PLAN"] = str_replace("|USD","",$arProps["TANXA"]["VALUE"]);
//        $arPushs["PLAN_Gel"] = $arProps["amount_GEL"]["VALUE"];
//        $arPushs["TYPE"] = "PLAN";
        $arPushs["PLAN_DATE"] = $arProps["TARIGI"]["VALUE"];
        $arPushs["PAYMENT_DATE"] = "";
        $arPushs["PLAN"] = str_replace("|USD","",$arProps["TANXA"]["VALUE"]);
        $arPushs["PAYMENT"] = "";
        $arPushs["PAYMENT_Gel"] = "";
        $arPushs["RATE"] = "";
        $arPushs["TYPE"] = "PLAN";

        array_push($arElements,$arPushs);

    }

    return $arElements;
}

function getPaymentPlanGEL($arFilter = array())
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
        $arPushs["PLAN"] = $arProps["amount_GEL"]["VALUE"];
        $arPushs["TYPE"] = "PLAN";
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
//        $arPushs["ID"] = $arFilds["ID"];
//        $arPushs["DATE"] = $arProps["date"]["VALUE"];
//        $arPushs["PAYMENT"] = str_replace("|USD","",$arProps["TANXA"]["VALUE"]);
//        $arPushs["PAYMENT_Gel"] = $arProps["tanxa_gel"]["VALUE"];
//        $arPushs["PLAN"] = "";
//        $arPushs["PLAN_Gel"] = "";
//        $arPushs["TYPE"] = "PAYMENT";
//        $arPushs["refund"] = $arProps["refund"]["VALUE"];
        $arPushs["PLAN_DATE"] = "";
        $arPushs["PAYMENT_DATE"] = $arProps["date"]["VALUE"];

        $arPushs["RATE"] = getNbgKurs($arPushs["PAYMENT_DATE"]);

        $arPushs["PLAN"] = "";

        $arPushs["PAYMENT"] = (float)str_replace("|USD","",$arProps["TANXA"]["VALUE"]);

// ⬇⬇⬇ აქ არის მთავარი ცვლილება ⬇⬇⬇
        $arPushs["PAYMENT_Gel"] = $arPushs["RATE"]
                ? round($arPushs["PAYMENT"] * $arPushs["RATE"], 2)
                : "";

        $arPushs["TYPE"] = "PAYMENT";
        $arPushs["refund"] = $arProps["refund"]["VALUE"];



        array_push($arElements,$arPushs);

    }

    return $arElements;
}


function getPaymentsGEL($arFilter = array())
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
        $arPushs["PAYMENT"] = str_replace("|USD","",$arProps["tanxa_gel"]["VALUE"]);
        $arPushs["PLAN"] = "";
        $arPushs["TYPE"] = "PAYMENT";
        $arPushs["refund"] = $arProps["refund"]["VALUE"];

        array_push($arElements,$arPushs);
    }

    return $arElements;
}

function getApprovedInstallment($arFilter = array()) {
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $res = CIBlockElement::GetList(Array("ID"=>"DESC"), $arFilter, false, Array("nPageSize" => 1), $arSelect);
    if ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];

        return $arPushs;
    }

    return false;
}


//function sortByDate($a, $b) {
//    $dateA = DateTime::createFromFormat('d/m/Y', $a['DATE']);
//    $dateB = DateTime::createFromFormat('d/m/Y', $b['DATE']);
//    return $dateA <=> $dateB;
//}

function sortByDate($a, $b) {
    $dateA = $a["PLAN_DATE"] ?: $a["PAYMENT_DATE"];
    $dateB = $b["PLAN_DATE"] ?: $b["PAYMENT_DATE"];

    $dA = DateTime::createFromFormat('d/m/Y', $dateA);
    $dB = DateTime::createFromFormat('d/m/Y', $dateB);

    return $dA <=> $dB;
}



function getContactName($id) {
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $id), array("ID", "FULL_NAME"));
    if($arContact = $res->Fetch()){
        return $arContact["FULL_NAME"];
    }
    return "";
}


// $arrForAdd ["UF_CRM_1761658559005"] = $prodNumber;     //ბინის N
// $arrForAdd ["UF_CRM_1761658577987"] =$prodFLOOR; //სართული
// $arrForAdd ["UF_CRM_1761658516561"] = $productData["PROJECT"];    //პროექტი
// $arrForAdd ["UF_CRM_1762948106980"] = $productData["KORPUSIS_NOMERI_XE3NX2"];//ბლოკი
// $arrForAdd ["UF_CRM_1761658503260"] = $productData["KVM_PRICE"];  //კვ/მ ფასი
// $arrForAdd ["UF_CRM_1761658532158"] = $prodPRODUCT_TYPE;      //ფართის ტიპი
// $arrForAdd ["UF_CRM_1761658608306"] = $prodTOTAL_AREA;    //საერთო ფართი
// $arrForAdd ["UF_CRM_1762867479699"] = $productData["_15MYD6"];     //სადარბაზო
// $arrForAdd ["UF_CRM_1761658765237"]  = $productData["LIVING_SPACE"];    //საცხოვრებელი ფართი მ²

$deal_ID = $_GET["dealid"];
$dealData    = getDealInfoByID($deal_ID);
$href="/crm/deal/details/$deal_ID/";
$floor = $dealData["UF_CRM_1761658577987"];
$number = $dealData["UF_CRM_1761658559005"];
$prodType = $dealData["UF_CRM_1761658532158"];
$project = $dealData["UF_CRM_1761658516561"];
$contactId = $dealData["CONTACT_ID"];
$contact=getContactName($contactId);
$xelshNom = $dealData["UF_CRM_1699907477758"];



$dealData["UF_CRM_1702019032102"] == 322 ? $valuta = "₾" : $valuta = "$";

/*if($dealData["UF_CRM_1702019032102"] == 322){
    $payments = getPaymentsGEL(array("IBLOCK_ID" => 25,"PROPERTY_DEAL"=>$deal_ID));
    $paymentPlans = getPaymentPlanGEL(array("IBLOCK_ID" => 24,"PROPERTY_DEAL"=>$deal_ID));

}
else{*/
$payments = getPayments(array("IBLOCK_ID" => 21,"PROPERTY_DEAL"=>$deal_ID));
$paymentPlans = getPaymentPlan(array("IBLOCK_ID" => 20,"PROPERTY_DEAL"=>$deal_ID));
//}
$financeArr = array_merge($paymentPlans, $payments);


usort($financeArr, 'sortByDate');
//printArr($financeArr);
for ($i = 0; $i<count($financeArr);$i++){
    if($i==0){
        if($financeArr[$i]["TYPE"]=="PLAN") {
            $financeArr[$i]["leftToPay"] = $financeArr[$i]["PLAN"];
        }
        else{
            if($financeArr[$i]["refund"]=="YES"){
                $financeArr[$i]["leftToPay"] = round( $financeArr[$i]["PAYMENT"],2);
            }
            else{
                $financeArr[$i]["leftToPay"] = round(0 - $financeArr[$i]["PAYMENT"],2);
            }
        }
    }
    else{
        if($financeArr[$i]["TYPE"]=="PLAN") {
            $financeArr[$i]["leftToPay"] = round($financeArr[$i-1]["leftToPay"] + $financeArr[$i]["PLAN"],2);
        }
        else{
            if($financeArr[$i]["refund"]=="YES"){
                $financeArr[$i]["leftToPay"] = round($financeArr[$i-1]["leftToPay"] + $financeArr[$i]["PAYMENT"],2);
            }
            else{
                $financeArr[$i]["leftToPay"] = round($financeArr[$i-1]["leftToPay"] - $financeArr[$i]["PAYMENT"],2);
            }
        }
    }
}

$approvedInstallment = array();
if($deal_ID) {
    $arFilter = array(
            "IBLOCK_ID" => 23,
            "PROPERTY_DASTURI" => "დადასტურებული",
            "PROPERTY_DEAL" => $deal_ID
    );

    $res = getApprovedInstallment($arFilter);

    if($res["ID"]) {
        $approvedInstallment = $res;
    }
}

// ეროვნული ბანკის კურსი კონკრეტულ თარიღში
function getNbgKurs($date){
    if (!$date) return null;

    $dateObj = DateTime::createFromFormat('d/m/Y', $date);
    if (!$dateObj) return null;

    $dateFormatted = $dateObj->format('Y-m-d');
    $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$dateFormatted}";

    $resp = @file_get_contents($url);
    if (!$resp) return null;

    $json = json_decode($resp);
    return $json[0]->currencies[0]->rate ?? null;
}


?>

<style>
    .hide {
        display: none;
    }
    .tabler {
        width: 100%;
        margin: 0px auto;
        color: #333;
        border-collapse: collapse;
        font-family: 'Noto Sans Georgian', sans-serif;
        font-size: 12px;
        text-align: center;
    }

    .cover{
        display: flex;
        width: 100%;
        height: 40px;
        background: #535c69;
        border-radius: 10px 10px 0 0;

    }

    .tabler td:first-child {
        border-left: none;
    }

    .tabler td:last-child {
        border-right: none;
    }

    thead {
        color: #6b6b6b;
    }


    .tablerlist {
        width: 100%;
        margin: 0px auto;
        color: #333;
        border-collapse: collapse;
        font-family: 'Noto Sans Georgian', sans-serif;
        font-size: 12px;
        text-align: center;
    }

    .tablerlist th {
        padding: 10px;
        background-color: #c6cdd3;
        color: #515967;
    }


    .inputer:nth-child(even) {
        background-color: #f2f2f2;
    }

    .inputer td {
        padding: 10px 0;
        transition: all 200ms ease-out;
    }
    .inputer:hover {
        background-color: #e4e4e4;
        transition: all 200ms ease-out;
    }

    .mainBlock{
        padding-left: 10%;
        width: 80%;
    }


    .currentMonth {
        border: 2px solid #ffc800;
    }

    .cover_icon{
        padding: 5px 20px 0 20px;
    }

    .cover-titles{
        color: white;
        font-size: 15px;
    }

</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script type="text/javascript" src="//unpkg.com/xlsx/dist/shim.min.js"></script>
<script type="text/javascript" src="//unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>

<div class="mainBlock">
    <div>
        <a href="<?php echo $href; ?>"><?php echo $dealData["TITLE"]; ?></a>


        <p>კლიენტი:  <?php echo $contact; ?></p>
        <p>პროექტი:  <?php echo $project; ?></p>
        <p>სართული:  <?php echo $floor; ?></p>
        <p><?php echo "$prodType:  N$number"; ?></p>
        <p>განვადების ტიპი:  <?php echo $approvedInstallment["SELECTID_GRAPH"]; ?></p>

    </div>
    <button onclick="exportTableToExcel()" style="background-color: #0c970c;border-radius: 6px;height: 30px;width: 95px">
        <span class="glyphicon glyphicon-export"></span> Export
    </button>
    <button onclick="exportTableToExcelENG()" style="background-color: #0c970c;border-radius: 6px;height: 30px;width: 115px">
        <span class="glyphicon glyphicon-export"></span> Export ENG
    </button>
    <div class="cover">
        <div class="cover_icon">
            <i class="fa fa-building" style="font-size: 30px; color:#cac9c7 " aria-hidden="true"></i>
        </div>
    </div>

    <table class="tablerlist" id = "table">
        <thead>
        <th>განვადების თარიღი</th>
        <th>გადახდის თარიღი</th>
        <th>განვადება $</th>
        <th>ეროვნული ბანკის კურსი</th>
        <th>გადახდილი $</th>
        <th>გადახდილი ₾</th>
        <th>ნაშთი</th>
        </thead>
        <tbody ID="financial_data"></tbody>
    </table>
</div>

<script>
    $financeArr = <?php echo json_encode($financeArr); ?>;
    let valuta = <?php echo json_encode($valuta); ?>;
    let financial_data = document.getElementById("financial_data");
    let rows = "";
    for(let i=0;i<$financeArr.length;i++) {
        rows += `
                <tr class="inputer">
                    <td>${$financeArr[i]["PLAN_DATE"] || ""}</td>
                    <td>${$financeArr[i]["PAYMENT_DATE"] || ""}</td>
                    <td>${$financeArr[i]["PLAN"] ? "$" + $financeArr[i]["PLAN"] : ""}</td>
                    <td>${$financeArr[i]["RATE"] || ""}</td>
                    <td>${$financeArr[i]["PAYMENT"] ? getPaymentAmount($financeArr[i], "$") : ""}</td>
                    <td>${$financeArr[i]["PAYMENT_Gel"] ? getPaymentAmountGel($financeArr[i], "₾") : ""}</td>
            `;

        if ($financeArr[i]["leftToPay"] > 0) {
            rows += `
                        <td style="color: red">${$financeArr[i]["leftToPay"]}</td>
                    </tr>
                `;
        } else {
            rows += `
                        <td style="color: green">${$financeArr[i]["leftToPay"]}</td>
                    </tr>
                `;
        }
    }

    function getPaymentAmount($financeArr,valuta){
        if($financeArr["refund"] == "YES"){
            return "-" + valuta + $financeArr["PAYMENT"];
        }else{
            return valuta + $financeArr["PAYMENT"];
        }
    }
    function getPaymentAmountGel($financeArr,valuta){
        if($financeArr["refund"] == "YES"){
            return "-" + valuta + $financeArr["PAYMENT_Gel"];
        }else{
            return valuta + $financeArr["PAYMENT_Gel"];
        }
    }
    financial_data.innerHTML = rows;


    function getLatinName(str) {
        const GEO_LAT = {
            "ქ": "q", "წ": "ts", "ჭ": "ch", "ე": "e", "რ": "r", "ღ": "gh", "ტ": "t", "თ": "t",
            "ყ": "y", "უ": "u", "ი": "i", "ო": "o", "პ": "p", "ა": "a", "ს": "s", "შ": "sh",
            "დ": "d", "ფ": "p", "გ": "g", "ჰ": "h", "ჯ": "j", "ჟ": "zh", "კ": "k", "ლ": "l",
            "ზ": "z", "ხ": "x", "ძ": "dz", "ც": "c", "ჩ": "ch", "ვ": "v", "ბ": "b", "ნ": "n", "მ": "m"
        };

        return str.split('').map(char => GEO_LAT[char] || char).join('');
    }




    function exportTableToExcel(){
        // var tableSelect = document.getElementById("table");

        // let wb = XLSX.utils.book_new();
        // wb.Props = {
        //     Title: "financialReport",
        //     Subject: "financialReport",
        //     Author:"w2",
        //     CreatedDate: new Date()
        // };
        // let ws_data = [];
        // let row = [];
        // let ws;


        // for (let i = 0; i < tableSelect.rows.length; i++) {
        //     for (let j = 0; j < tableSelect.rows[i].cells.length; j++) {
        //         if(i != 0 && j==2){
        //             let number = tableSelect.rows[i].cells[j].innerText.replace("$","");
        //             number = number.replace(",",".");
        //             row.push(Number(number));
        //         }else if (i != 0 && j==3){
        //             let number = tableSelect.rows[i].cells[j].innerText.replace("$","");
        //             number = number.replace(",",".");
        //             row.push(Number(number));
        //         }
        //         else if (i != 0 && j==4){
        //             let number = tableSelect.rows[i].cells[j].innerText.replace(",",".");
        //             row.push(Number(number));
        //         }
        //         else{
        //             row.push(tableSelect.rows[i].cells[j].innerText);
        //         }
        //     }
        //     ws_data.push(row);
        //     row=[];
        // }

        // ws = XLSX.utils.aoa_to_sheet(ws_data);
        // XLSX.utils.book_append_sheet(wb, ws, "financialReport");
        // var wbout = XLSX.writeFile(wb,"financialReport.xlsx");



        var tableSelect = document.getElementById("table");

        let wb = XLSX.utils.book_new();
        wb.Props = {
            Title: "financialReport",
            Subject: "financialReport",
            Author: "w2",
            CreatedDate: new Date()
        };

        let ws_data = [];

        // **დამატებითი ინფორმაცია ცხრილის ზემოთ**
        ws_data.push(["კლიენტი:", ("<?php echo $contact ; ?>")]);
        ws_data.push(["პროექტი:", ("<?php echo $project; ?>")]);
        ws_data.push(["სართული:", "<?php echo $floor; ?>"]);

        var prodType = "<?php echo $prodType; ?>"; // PHP მონაცემის გადაცემა JavaScript-ში

        if (prodType === "ბინა") {
            var prodTypeENG = "Apartment";
        } else {
            var prodTypeENG = "Parking";
        }

        ws_data.push([prodTypeENG, "N<?php echo $number; ?>"]);
        ws_data.push(["განვადების ტიპი:", ("<?php echo $approvedInstallment['SELECTID_GRAPH']; ?>")]);
        ws_data.push(["ხელშ. ნომერი:", "<?php echo $xelshNom; ?>"]);
        ws_data.push([]); // ცარიელი ხაზი (გაყრისთვის)

        // **ცხრილის სათაურების თარგმნა (Header Row)**
        let translatedHeaders = ["N", "Date", "განვადება $", "განვადება ₾", "გადახდილი $", "გადახდილი ₾", "ნაშთი"];

        ws_data.push(translatedHeaders);

        // **მონაცემების დამუშავება**
        for (let i = 1; i < tableSelect.rows.length; i++) { // იწყება 1-დან, რადგან 0 სათაურია
            let row = [];
            for (let j = 0; j < tableSelect.rows[i].cells.length; j++) {
                let cellText = tableSelect.rows[i].cells[j].innerText.trim();

                if (j == 1) { // "თარიღი" (Date) სვეტი
                    row.push(cellText);
                } else if ([2, 3, 4, 5].includes(j)) { // თანხის ველები
                    let number = cellText.replace("$", "").replace("₾", "").replace(",", ".");
                    if(Number(number)){
                        row.push(Number(number));
                    }else{
                        row.push("");
                    }
                } else {
                    row.push(cellText);
                }
            }
            ws_data.push(row);
        }

        let ws = XLSX.utils.aoa_to_sheet(ws_data);
        XLSX.utils.book_append_sheet(wb, ws, "financialReport");

        XLSX.writeFile(wb, "financialReport.xlsx");

    }





    function exportTableToExcelENG() {
        var tableSelect = document.getElementById("table");

        let wb = XLSX.utils.book_new();
        wb.Props = {
            Title: "financialReport",
            Subject: "financialReport",
            Author: "w2",
            CreatedDate: new Date()
        };

        let ws_data = [];

        // **დამატებითი ინფორმაცია ცხრილის ზემოთ**
        ws_data.push(["Client:", getLatinName("<?php echo $contact ; ?>")]);
        ws_data.push(["Project:", getLatinName("<?php echo $project; ?>")]);
        ws_data.push(["Floor:", "<?php echo $floor; ?>"]);

        var prodType = "<?php echo $prodType; ?>"; // PHP მონაცემის გადაცემა JavaScript-ში

        if (prodType === "ბინა") {
            var prodTypeENG = "Apartment";
        } else {
            var prodTypeENG = "Parking";
        }

        var approvedInstallment = "<?php echo $approvedInstallment['SELECTID_GRAPH']; ?>"; // PHP მონაცემის გადაცემა JavaScript-ში



        if (approvedInstallment === "არასტანდარტული") {
            var approvedInstallmentENG = "Non-standard";
        } else if (approvedInstallment === "ერთიანი გადახდა -5 %"){
            var approvedInstallmentENG = "All cash -5 % ";
        }else if (approvedInstallment === "განვადება 15%-15%-70% რეზიდენტები"){
            var approvedInstallmentENG = "Installment 15%-15%-70% residents";
        }else if (approvedInstallment === "განვადება 30%-40%-30% არარეზიდენტები"){
            var approvedInstallmentENG = "Installment 30%-40%-30% Non-residents";
        }else if (approvedInstallment === "განვადება 50/50"){
            var approvedInstallmentENG = "Installment 50/50";
        }else{
            var approvedInstallmentENG = approvedInstallment;
        }


        ws_data.push([prodTypeENG, "N<?php echo $number; ?>"]);
        ws_data.push(["Plan Type:", approvedInstallmentENG]);
        ws_data.push(["Contract Number:", "<?php echo $xelshNom; ?>"]);
        ws_data.push([]); // ცარიელი ხაზი (გაყრისთვის)

        // **ცხრილის სათაურების თარგმნა (Header Row)**
        let translatedHeaders = ["N", "თარიღი", "Plan $", "PLan ₾", "Paid $", "Paid ₾", "Left to pay"];
        ws_data.push(translatedHeaders);

        // **მონაცემების დამუშავება**
        for (let i = 1; i < tableSelect.rows.length; i++) { // იწყება 1-დან, რადგან 0 სათაურია
            let row = [];
            for (let j = 0; j < tableSelect.rows[i].cells.length; j++) {
                let cellText = tableSelect.rows[i].cells[j].innerText.trim();

                if (j == 1) { // "თარიღი" (Date) სვეტი
                    row.push(cellText);
                } else if ([2, 3, 4, 5].includes(j)) { // თანხის ველები
                    let number = cellText.replace("$", "").replace("₾", "").replace(",", ".");
                    if(Number(number)){
                        row.push(Number(number));
                    }else{
                        row.push("");
                    }
                } else {
                    row.push(cellText);
                }
            }
            ws_data.push(row);
        }

        let ws = XLSX.utils.aoa_to_sheet(ws_data);
        XLSX.utils.book_append_sheet(wb, ws, "financialReport");

        XLSX.writeFile(wb, "financialReport.xlsx");
    }




</script>
