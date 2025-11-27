<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("");




function printArr($arr){
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getCIBlockElementByFilter($arFilter)
{
    $res = CIBlockElement::GetList(array("ID"=>"ASC"), $arFilter, false, Array("nPageSize" => 1), array());
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        return $arPushs;
    }
    return false;
}

function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $arDeals = array();
	$arSelect = array();
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) {
        $arDeal["AMOUNT_USD"] = floatval(str_replace("|USD", "", $arDeal["OPPORTUNITY"]));
        $arDeal["AMOUNT_GEL"] = floatval(str_replace("|GEL", "", $arDeal["UF_CRM_1701778190"]));

        $contactInfo = getContactInfo($arDeal["CONTACT_ID"]);
        $companyInfo = getCompanyInfo($arDeal["COMPANY_ID"]);

        $arDeal["CONTACT_INFO"] = $contactInfo;
        $arDeal["COMPANY_INFO"] = $companyInfo;

        $arDeal["RESPONSIBLE_NAME"] = getUserFullName($arDeal["ASSIGNED_BY_ID"]);

        $arDeal["CURRENCY"] = "";
        if($arDeal["UF_CRM_1702019032102"] == 322) $arDeal["CURRENCY"] = "GEL";
        else if($arDeal["UF_CRM_1702019032102"] == 323) $arDeal["CURRENCY"] = "USD";

        $arDeal["CONTRACT_DATE"] = contactDate($arDeal["UF_CRM_1693398443196"]);

        array_push($arDeals, $arDeal);
    }
    return (count($arDeals) > 0) ? $arDeals : array();
}

function contactDate($date) {
    $date = explode("/", $date);

    $date = $date[2] . "-" . $date[1] . "-" . $date[0];

    return $date;
}

function getUserFullName($userid) {
    $arSelect = array('SELECT'=>array("ID","NAME","LAST_NAME","WORK_POSITION","UF_*"));
    $rsUsers = CUser::GetList(($by="NAME"), ($order="desc"), array("ID"=>$userid), $arSelect);
    if($arUser = $rsUsers->Fetch()) return "{$arUser['NAME']} {$arUser['LAST_NAME']}";
    else return 'unknown';
}

function getDealFields($fieldName){
    $option=array();
    $rsUField = CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $fieldName));
    while($arUField = $rsUField->GetNext())   {
        $option[$arUField["ID"]]=$arUField["VALUE"];
    }

    return $option;
}

function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $EMAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK|HOME', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["EMAIL"] = $EMAIL["VALUE"];
        $arContact["PHONE"] = $PHONE["VALUE"];

        return $arContact;
    }

    return $arContact;
}

function getCompanyInfo($companyId) {
    $arCompany = array();
    $res = CCrmCompany::GetList(array("ID" => "ASC"), array("ID" => $companyId), array());
    if($arCompany = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'COMPANY','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arCompany["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'COMPANY','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arCompany["ID"]))->Fetch();
        $arCompany["PHONE"] = $PHONE["VALUE"];
        $arCompany["EMAIL"] = $MAIL["VALUE"];

        return $arCompany;
    }
    return $arCompany;
}

function getDataForTables($project) {
    $dealsArr = array(
        "GENERAL" => array(),
        "DETAILS" => array()
    );

    $dealsArr["DETAILS"]["ALL"] = array();

    $arFilter = array(
        "STAGE_ID" => array("FINAL_INVOICE", "1"),
        "UF_CRM_1761658516561" => $project,
        // "!OPPORTUNITY" => 0,
        // "UF_CRM_1710256136159" => 0,
        // "UF_CRM_1707309740747" => 0,
    );

    $resDeals = getDealsByFilter($arFilter);

    foreach ($resDeals as $deal) {
        $prods = CCrmDeal::LoadProductRows($deal["ID"]);

        $arFilter = array(
            "IBLOCK_ID" => 14,
            "ID" => $prods[0]["PRODUCT_ID"]
        );

        // printArr($arFilter);

        $product = getCIBlockElementByFilter($arFilter);


        $deal["PRODUCT_INFO"]=$product;

        // printArr($product);


        $deal["PAYMENT_TYPE"] = $deal["UF_CRM_1705413820965"];
        $dealProject = $deal["UF_CRM_1761658516561"];

        if(!is_numeric($dealsArr["GENERAL"][$dealProject]["FLAT"]["COUNT"])) {
            $dealsArr["DETAILS"][$dealProject]["FLAT"] = array();
            $dealsArr["GENERAL"][$dealProject]["FLAT"]["COUNT"] = 0;
            $dealsArr["GENERAL"][$dealProject]["FLAT"]["AREA"] = 0;
            $dealsArr["GENERAL"][$dealProject]["FLAT"]["AMOUNT"] = 0;

            $dealsArr["DETAILS"][$dealProject]["PARKING"] = array();
            $dealsArr["GENERAL"][$dealProject]["PARKING"]["COUNT"] = 0;
            $dealsArr["GENERAL"][$dealProject]["PARKING"]["AREA"] = 0;
            $dealsArr["GENERAL"][$dealProject]["PARKING"]["AMOUNT"] = 0;

            $dealsArr["DETAILS"][$dealProject]["COMMERCIAL"] = array();
            $dealsArr["GENERAL"][$dealProject]["COMMERCIAL"]["COUNT"] = 0;
            $dealsArr["GENERAL"][$dealProject]["COMMERCIAL"]["AREA"] = 0;
            $dealsArr["GENERAL"][$dealProject]["COMMERCIAL"]["AMOUNT"] = 0;

            $dealsArr["DETAILS"][$dealProject]["OFFICE"] = array();
            $dealsArr["GENERAL"][$dealProject]["OFFICE"]["COUNT"] = 0;
            $dealsArr["GENERAL"][$dealProject]["OFFICE"]["AREA"] = 0;
            $dealsArr["GENERAL"][$dealProject]["OFFICE"]["AMOUNT"] = 0;

            $dealsArr["GENERAL"][$dealProject]["SUM"]["COUNT"] = 0;
            $dealsArr["GENERAL"][$dealProject]["SUM"]["AREA"] = 0;
            $dealsArr["GENERAL"][$dealProject]["SUM"]["AMOUNT"] = 0;
        }

        if($deal["UF_CRM_1761658532158"] == "Flat") {
            $dealsArr["GENERAL"][$dealProject]["FLAT"]["COUNT"]++;
            $dealsArr["GENERAL"][$dealProject]["FLAT"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr["GENERAL"][$dealProject]["FLAT"]["AMOUNT"] += $deal["AMOUNT_USD"];

            array_push($dealsArr["DETAILS"][$dealProject]["FLAT"], $deal);
        } else if($deal["UF_CRM_1761658532158"] == "Parking") {
            $dealsArr["GENERAL"][$dealProject]["PARKING"]["COUNT"]++;
            $dealsArr["GENERAL"][$dealProject]["PARKING"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr["GENERAL"][$dealProject]["PARKING"]["AMOUNT"] += $deal["AMOUNT_USD"];

            array_push($dealsArr["DETAILS"][$dealProject]["PARKING"], $deal);
        } else if($deal["UF_CRM_1761658532158"] == "კომერციული") {
            $dealsArr["GENERAL"][$dealProject]["COMMERCIAL"]["COUNT"]++;
            $dealsArr["GENERAL"][$dealProject]["COMMERCIAL"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr["GENERAL"][$dealProject]["COMMERCIAL"]["AMOUNT"] += $deal["AMOUNT_USD"];

            array_push($dealsArr["DETAILS"][$dealProject]["OFFICE"], $deal);
        } else if($deal["UF_CRM_1761658532158"] == "საოფისე" || $deal["UF_CRM_1761658532158"] == "საოფისე") {
            $dealsArr["GENERAL"][$dealProject]["OFFICE"]["COUNT"]++;
            $dealsArr["GENERAL"][$dealProject]["OFFICE"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr["GENERAL"][$dealProject]["OFFICE"]["AMOUNT"] += $deal["AMOUNT_USD"];

            array_push($dealsArr["DETAILS"][$dealProject]["OFFICE"], $deal);
        }

        $dealsArr["GENERAL"][$dealProject]["SUM"]["COUNT"]++;
        $dealsArr["GENERAL"][$dealProject]["SUM"]["AREA"]  += $deal["UF_CRM_1761658608306"];
        $dealsArr["GENERAL"][$dealProject]["SUM"]["AMOUNT"]  += $deal["AMOUNT_USD"];
        array_push($dealsArr["DETAILS"]["ALL"], $deal);
    }

    return $dealsArr;
}


$project = $_GET['Project'] ? $_GET['Project'] : "all";



$generalInfos = array();

if($project == "all") {
    $arrProjects = array("33");
    $generalInfos = getDataForTables($arrProjects);
}
else if(!empty($project)) {
    $generalInfos = getDataForTables($project);
}


ob_end_clean();




?>



<style>




.seperator {
    width: 100%;
    height: 80px;
}

.titleName{
    font-size: 50px;
    color:#FF9A7B;
    text-align:center;
    font-weight:500;
    margin-top:3%;
}

.statisticContainer {
    display: flex;
    flex-wrap: wrap;
}


.projectStat {
    width: 40%;
    height: 200px;
    margin: 3%;
    padding: 2%;
    margin-top:-80px;
}

.tableHead{
    background:#8989ff;
    color:#fefeff;
}

.table {
    font-family: arial, sans-serif;
    border-collapse: collapse;
}

.tr{
    text-align:right;
    padding: 5px;
    font-weight:bold;
}

.th{
    width: 150px;
    text-align:center;
}

.td, .th {
  border: 1px solid #000;
  text-align: left;
  padding: 4px;
}

.prodTypeName{
    text-align:left;
}

.excel-btn-container {
    float: right;
}

.excel-icon {
    cursor: pointer;
}

</style>




<div class="statistic">

    <form method="get" id="newCalendarForm">
        <div style="float: left">
        <div style=" float: left;  margin-left: 10px;">
                <label>Project</label><br />
                <select id="Project" name="Project" style="display: inline-block;">
                    <option value="Park Boulevard">Park Boulevard</option>
      
                   
                </select>
            </div>
        <div style="float: left; margin-left: 10px;margin-top: 17px;">
                <input type="submit">
            </div>
        </div>
    </form>

    <div class="excel-btn-container">
        <img onClick="downloadExcel();" class="excel-icon" src="https://img.icons8.com/color/32/000000/export-excel.png" />
    </div>


    <div class="seperator"></div>

    <div class="title"><h1 class="titleName">Reservation Statistics </h1></div>

    <div class="seperator"></div>




    <div class="statisticContainer" id="statisticContainer"></div>
<div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

<script>
    const generalInfos = <?php echo json_encode($generalInfos, JSON_UNESCAPED_UNICODE); ?>;
    const project = <?php echo json_encode($project, JSON_UNESCAPED_UNICODE); ?>;

    document.getElementById("Project").value = project;

    const projectNamesJson = {
        "Park Boulevard": "Park Boulevard",
     
      
    };

    drawTable(generalInfos["GENERAL"]);

    function drawTable(data) {
        const statisticContainer = document.getElementById("statisticContainer");

        let counter = 0;

        const projectIds = Object.keys(data);

        projectIds.forEach(key => {
            console.log(key)

            const projEN = projectNamesJson[key];

            table = `
                <thead class="tableHead" id="${key}">
                    <tr class="tr">
                        <th class="th">${projEN}</th>
                        <th class="th">Count</th>
                        <th class="th">Area</th>
                        <th class="th">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="tr">
                        <td class="td prodTypeName">Appartment </td>
                        <td class="td">${data[key]["FLAT"]["COUNT"].toFixed(0)}</td>
                        <td class="td">${data[key]["FLAT"]["AREA"].toFixed(2)}</td>
                        <td class="td">${data[key]["FLAT"]["AMOUNT"].toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                    </tr>
                    <tr class="tr">
                        <td class="td prodTypeName">Commercial </td>
                        <td class="td">${data[key]["COMMERCIAL"]["COUNT"].toFixed(0)}</td>
                        <td class="td">${data[key]["COMMERCIAL"]["AREA"].toFixed(2)}</td>
                        <td class="td">${data[key]["COMMERCIAL"]["AMOUNT"].toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                    </tr>
                    <tr class="tr">
                        <td class="td prodTypeName">Office </td>
                        <td class="td">${data[key]["OFFICE"]["COUNT"].toFixed(0)}</td>
                        <td class="td">${data[key]["OFFICE"]["AREA"].toFixed(2)}</td>
                        <td class="td">${data[key]["OFFICE"]["AMOUNT"].toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                    </tr>
                    <tr class="tr">
                        <td class="td prodTypeName">Parking </td>
                        <td class="td">${data[key]["PARKING"]["COUNT"].toFixed(0)}</td>
                        <td class="td">${data[key]["PARKING"]["AREA"].toFixed(2)}</td>
                        <td class="td">${data[key]["PARKING"]["AMOUNT"].toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                    </tr>
                    <tr class="tr">
                        <td class="td prodTypeName">Total </td>
                        <td class="td">${data[key]["SUM"]["COUNT"].toFixed(0)}</td>
                        <td class="td">${data[key]["SUM"]["AREA"].toFixed(2)}</td>
                        <td class="td">${data[key]["SUM"]["AMOUNT"].toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                    </tr>
                <tbody>
            `;

            statisticContainer.innerHTML += `
                <div class="projectStat">
                    <table class="table">${table}</table>
                </div>
            `;

            const headDiv = document.getElementById(key);
            if(key == '33'){
                headDiv.style.background='#70ad47';
            }

        });
    }

    function downloadExcel() {
        const wb = XLSX.utils.book_new();

        let my_json1 = [];
        let projectsArr = [];

        generalInfos["DETAILS"]["ALL"].forEach(el => {

            let kvMetriFasi = (Number(el["UF_CRM_1665571815062"]) || 0).toFixed(2); // Default value for "კვ/მ ფასი"
    
    // Check if "ფართის ტიპი" is "პარკინგი"
    if (el["UF_CRM_1761658532158"] === "პარკინგი") {
        kvMetriFasi = ""; // Set "კვ/მ ფასი" to empty if "ფართის ტიპი" is "პარკინგი"
    }
            my_json1.push({
                "პროექტი": projectNamesJson[el["UF_CRM_1761658516561"]], 
                "კორპუსი": el["UF_CRM_1661249856017"], 
                "სართული": el["UF_CRM_1657706704459"], 
                "ბინის №": Number(el["UF_CRM_1657706778710"]), 
                "ფართის ტიპი": el["UF_CRM_1761658532158"], 
                "საერთო ფართი": Number(el["UF_CRM_1761658608306"]), 
                "კვ/მ ფასი":  kvMetriFasi,
                "ჯამური თანხა": (Number(el["OPPORTUNITY"])|| 0).toFixed(2), 
                "ხელშეკრულების გაფორმების თარიღი":el["UF_CRM_1667309488937"], 
                "საკადასტრო კოდი": el["PRODUCT_INFO"]["__70OHLJ"],
                "მესაკუთრე": el["COMPANY_INFO"]["TITLE"] ? el["COMPANY_INFO"]["TITLE"] : el["CONTACT_INFO"]["FULL_NAME"],
                "ტელეფონი": el["COMPANY_INFO"]["PHONE"] ? el["COMPANY_INFO"]["PHONE"] : el["CONTACT_INFO"]["PHONE"], 
                "მეილი": el["COMPANY_INFO"]["EMAIL"] ? el["COMPANY_INFO"]["EMAIL"] : el["CONTACT_INFO"]["EMAIL"], 
                "პასუხისმგებელი": el["RESPONSIBLE_NAME"], 
                //"ანგარიშსწორების ტიპი": el["PAYMENT_TYPE"], 
               // "კონტრაქტის ნომერი": el["UF_CRM_1699907477758"], 
               // "თანხა ₾": Number(el["AMOUNT_GEL"]), 
               // "კურსი (კონტრაქტის გაფორმების დღის)": el["UF_CRM_1701786033562"], 
               // "მნიშვნელოვანი ტრანზაქციის განმარტება": el["UF_CRM_1702038078127"], 
               // "აივნების ჯამური ფართი": el["UF_CRM_1702650778297"], 
               // "ტიპი (სრული)": el["UF_CRM_1702650856624"], 
              //  "საცხოვრებელი ფართი მ²": el["UF_CRM_1702650885089"], 
               // "კომპანია": el["COMPANY_INFO"]["TITLE"], 
              //  "პირადი ნომერი": el["COMPANY_INFO"]["UF_CRM_1693399408936"] ? el["COMPANY_INFO"]["UF_CRM_1693399408936"] : el["CONTACT_INFO"]["UF_CRM_1693399408936"], 
               // "ტრანზაქციის ვალუტა": el["CURRENCY"] , 
               "კომენტარი/შენიშვნა": el["UF_CRM_1740467769492"]});
                

            if(!projectsArr.includes(el["UF_CRM_1761658516561"])) {
                projectsArr.push(el["UF_CRM_1761658516561"]);
            }
        });

        let projectsText = projectsArr.join(", ");

        let fileName = `reservation.xlsx`;
        let sheetName1 = `DATA`;

        let ws1 = XLSX.utils.json_to_sheet(my_json1);

        XLSX.utils.book_append_sheet(wb, ws1, sheetName1);

        XLSX.writeFile(wb, fileName);
    }

</script>