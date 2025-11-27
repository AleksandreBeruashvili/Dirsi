<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("");




function printArr($arr){
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        return $arContact;
    }
    return $arContact;
}

function getCompanyInfo($companyId) {
    $arCompany = array();
    $res = CCrmCompany::GetList(array("ID" => "ASC"), array("ID" => $companyId), array());
    if($arCompany = $res->Fetch()){
        return $arCompany;
    }
    return $arCompany;
}


function getCIBlockElementsByFilter($arFilter = array(),$sort = array())
{
    $arElements = array();
    $arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $res = CIBlockElement::GetList($sort, $arFilter, false, array("nPageSize" => 50), $arSelect);
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

function getUserFullName($userid) {
    $arSelect = array('SELECT'=>array("ID","NAME","LAST_NAME","WORK_POSITION","UF_*"));
    $rsUsers = CUser::GetList(($by="NAME"), ($order="desc"), array("ID"=>$userid), $arSelect);
    if($arUser = $rsUsers->Fetch()) return "{$arUser['NAME']} {$arUser['LAST_NAME']}";
    else return 'unknown';
}

function getDeals($arFilter, $arSelect = array(), $arSort = array()){
    $arDeals = array();
    $arSelect = array("ID", "SOURCE_ID", "STAGE_ID", "CONTACT_ID", "COMPANY_ID", "OPPORTUNITY", "ASSIGNED_BY_ID", "UF_CRM_1761658516561", "UF_CRM_1761658532158", "UF_CRM_1701789405450", "UF_CRM_1693385964548", "UF_CRM_1698063520530", "UF_CRM_1702650778297", "UF_CRM_1702650885089", "UF_CRM_1693386021079", "UF_CRM_1761658516561", "UF_CRM_1694781640289", "UF_CRM_1702649836914", "UF_CRM_1703595745329");
    $arSort = array("ID" => "ASC");
    $res = CCrmDeal::GetListEx($arSort, $arFilter, false, false, $arSelect);
    while ($arDeal = $res->Fetch()) {
        $arDeal["CONTACT_NAME"] = "";
        $arDeal["COMPANY_TITLE"] = "";
        $arDeal["CORPS"] = "";
        $arDeal["PRODUCT_TYPE"] = "";
        $arDeal["RESPONSIBLE_NAME"] = getUserFullName($arDeal["ASSIGNED_BY_ID"]);
        $arDeal["AMOUNT_USD"] = str_replace("|USD", "",  $arDeal["OPPORTUNITY"]);

        if(is_numeric($arDeal["CONTACT_ID"])) {
            $contact = getContactInfo($arDeal["CONTACT_ID"]);
            $arDeal["CONTACT_NAME"] = $contact["FULL_NAME"];
        }

        if(is_numeric($arDeal["COMPANY_ID"])) {
            $company = getCompanyInfo($arDeal["COMPANY_ID"]);
            $arDeal["COMPANY_TITLE"] = $company["TITLE"];
        }

        $prods = CCrmDeal::LoadProductRows($arDeal["ID"]);

        if($prods[0]["PRODUCT_ID"]) {
            $arFilter = array(
                "IBLOCK_ID" => 14,
                "ID" => $prods[0]["PRODUCT_ID"]
            );

            $resProd = getCIBlockElementsByFilter($arFilter)[0];

            if(!empty($resProd["ID"])) {
                $arDeal["CORPS"] = $resProd["CORPS"];
                $arDeal["PRODUCT_TYPE"] = $resProd["PRODUCT_TYPE"];
            }

        }

        array_push($arDeals, $arDeal);

    }
    return (count($arDeals) > 0) ? $arDeals : true;
}






function getDataForTables($project){

    $arFilter=array(
        "STAGE_ID" => array("FINAL_INVOICE", "1"),
        "UF_CRM_1761658516561" => $project,
        //  "UF_CRM_1710256136159" => 0,
       

    );

    $deals = getDeals($arFilter);

    printArr($deals);


    $res = array(
        "GENERAL" => array(),
        "DETAILS" => array()
    );
    $res["GENERAL"]["Appartment"] = 0;
    $res["GENERAL"]["Commercial"] = 0;
    $res["GENERAL"]["Office"] = 0;
    $res["GENERAL"]["Parking"] = 0;
    $res["GENERAL"]["Total"] = 0;
    $res["GENERAL"]["AppartmentInvoice"] = 0;
    $res["GENERAL"]["CommercialInvoice"] = 0;
    $res["GENERAL"]["OfficeInvoice"] = 0;
    $res["GENERAL"]["ParkingInvoice"] = 0;
    $res["GENERAL"]["TotalInvoice"] = 0;

    foreach($deals as $deal){
        if($deal["UF_CRM_1761658532158"] == "Flat"){
            $res["GENERAL"]["Appartment"] = $res["GENERAL"]["Appartment"] +1;
            $res["GENERAL"]["Total"] = $res["GENERAL"]["Total"] +1;
            if($deal["UF_CRM_1701789405450"]){
                $res["GENERAL"]["AppartmentInvoice"] ++; 
                $res["GENERAL"]["TotalInvoice"] ++; 
            }

            array_push($res["DETAILS"], $deal);

        } elseif($deal["UF_CRM_1761658532158"] == "Parking") {
            $res["GENERAL"]["Parking"] = $res["GENERAL"]["Parking"] +1;
            $res["GENERAL"]["Total"] = $res["GENERAL"]["Total"] +1;
            if($deal["UF_CRM_1701789405450"]){
                $res["GENERAL"]["ParkingInvoice"] ++; 
                $res["GENERAL"]["TotalInvoice"] ++; 
            }

            array_push($res["DETAILS"], $deal);

        } elseif($deal["UF_CRM_1761658532158"] == "კომერციული") {
            $res["GENERAL"]["Commercial"] = $res["GENERAL"]["Commercial"] +1;
            $res["GENERAL"]["Total"] = $res["GENERAL"]["Total"] +1;
            if($deal["UF_CRM_1701789405450"]){
                $res["GENERAL"]["CommercialInvoice"] ++; 
                $res["GENERAL"]["TotalInvoice"] ++; 
            }

            array_push($res["DETAILS"], $deal);

        } elseif($deal["UF_CRM_1761658532158"] == "საოფისე ფართი" || $deal["UF_CRM_1761658532158"] == "საოფისე") {
            $res["GENERAL"]["Office"] = $res["GENERAL"]["Office"] +1;
            $res["GENERAL"]["Total"] = $res["GENERAL"]["Total"] +1;
            if($deal["UF_CRM_1701789405450"]){
                $res["GENERAL"]["OfficeInvoice"] ++; 
                $res["GENERAL"]["TotalInvoice"] ++;             
            }

            array_push($res["DETAILS"], $deal);
        }
        
    }

    return $res;

}


$project = $_GET['Project'];


$generalInfos = array();

if(empty($project) || $project=="all"){
    $generalInfos["ParkBoulevardData"]=getDataForTables("Park Boulevard");


   
}




?>



<style>




.seperator {
    width: 100%;
    height: 30px;
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
    align-items: stretch;
}

.statisticContainer2 {
    display: flex;
    align-items: stretch;
}

.projectStat {
    width: 45%;
    height: 200px;
    margin: 3%;
    /* background: gray; */
    padding: 2rem;
}

.tableClass {
    border-collapse: collapse;
    border: 2px solid black; /* Border for the entire table */
}

.tableClass th, .tableClass td {
    border: 1px solid black; /* Border for table cells */
}

.tableHead{
    background:#595959;
    color:#fefeff;
}


.tr{
    text-align:center;
    padding:4px;
    font-weight:bold;
}

th{
    width:150px;
    text-align:center;
}

.prodTypeName{
    text-align:left;
}

.prodTypeNameTotal{
    text-align:left; 
    font-size:18px;
}

.tdTotal{
    font-size:18px;
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
                <label>პროექტი</label><br />
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




    <div class="statisticContainer">
        <div class="projectStat">
            <table id="parkBoulevard" class ="tableClass">
                
            </table>
        </div>


    </div>

    
        
    </div>



<div>


<script src="https://unpkg.com/write-excel-file@1.x/bundle/write-excel-file.min.js"></script>

<script>

let project = <?php echo json_encode($project);?>;


let projectName = "";
let headBgColor = "";

if(project == "Park Boulevard"){
    projectName = "Park Boulevard";
    headBgColor = "#70ad47";
}



document.getElementById("Project").value = project;

const dealsInfo = <?php echo json_encode($generalInfos["ParkBoulevardData"]["DETAILS"]); ?>;

let ParkBoulevardData = <?php echo json_encode($generalInfos["ParkBoulevardData"]["GENERAL"]); ?>;



if(project && project!="all"){
    drawTable(ParkBoulevardData,"parkBoulevard",project);
}else{
    drawTable(ParkBoulevardData,"parkBoulevard","ParkBoulevard");
}








function drawTable(data,tableID,projectName){
    if(data) {
        table = `<thead class="tableHead" id="${projectName}">
                    <tr class="tr">
                        <th>${projectName}</th>
                        <th>reserved unit</th>
                        <th>invoice sent</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="tr">
                        <td class="prodTypeName">Appartment </td>
                        <td>${data["Appartment"]}</td>
                        <td>${data["AppartmentInvoice"]} </td>
                    </tr>
                    <tr class="tr">
                        <td class="prodTypeName">Commercial </td>
                        <td>${data["Commercial"]}</td>
                        <td>${data["CommercialInvoice"]}</td>
                    </tr>
                    <tr class="tr">
                        <td class="prodTypeName">Office </td>
                        <td>${data["Office"]}</td>
                        <td>${data["OfficeInvoice"]}</td>
                    </tr>
                    <tr class="tr">
                        <td class="prodTypeName">Parking </td>
                        <td>${data["Parking"]}</td>
                        <td>${data["ParkingInvoice"]}</td>
                    </tr>
                    <tr class="tr">
                        <td class="prodTypeNameTotal">Total </td>
                        <td class="tdTotal">${data["Total"]}</td>
                        <td class="tdTotal">${data["TotalInvoice"]}</td>
                    </tr>
                <tbody>
        `;

        let chartElement = document.getElementById(tableID);
        chartElement.innerHTML=table;

        const headDiv = document.getElementById(projectName);
        if(projectName == 'Park Boulevard'){
            headBgColor='#70ad47';
        }

        headDiv.style.background = headBgColor;
    }

}

function downloadExcel() {
    let my_json1 = [
        [{value: "კლიენტის სახელი"}, {value: "კორპუსი"}, {value: "ნომერი"}, {value: "სართული"}, {value: "აივნების ჯამური ფართი"}, {value: "ფართი"}, {value: "საერთო ფართი"}, {value: "ზეტიპი"}, {value: "პროექტი"}, {value: "ფასი"}, {value: "კლიენტთან საკონტაქტო პირი"}, {value: "რეზერვაცია სადამადე"}, {value: "რეზერვაციის თარიღი"}, {value: "რეზერვაციის სტატუსი"}]
    ];

    let my_json2 = [
        [{value: "Appartement", fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["Appartment"], fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["AppartmentInvoice"], fontWeight: "bold", borderColor: "#000000"}],

        [{value: "Commercial", fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["Commercial"], fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["CommercialInvoice"], fontWeight: "bold", borderColor: "#000000"}],

        [{value: "Office", fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["Office"], fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["OfficeInvoice"], fontWeight: "bold", borderColor: "#000000"}],

        [{value: "Parking", fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["Parking"], fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["ParkingInvoice"], fontWeight: "bold", borderColor: "#000000"}],

        [{value: "Total", fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["Total"], fontWeight: "bold", borderColor: "#000000"},
        {value: ParkBoulevardData["TotalInvoice"], fontWeight: "bold", borderColor: "#000000"}],

    ];

    dealsInfo.forEach(el => {
        my_json1.push([{value: el["CONTACT_NAME"] ? el["CONTACT_NAME"] : el["COMPANY_TITLE"]}, {value: el["CORPS"]}, {value: el["UF_CRM_1693385964548"]}, {value: el["UF_CRM_1698063520530"]}, {value: el["UF_CRM_1702650778297"]}, {value: el["UF_CRM_1702650885089"]}, {value: el["UF_CRM_1693386021079"]}, {value: el["PRODUCT_TYPE"]}, {value: el["UF_CRM_1761658516561"]}, {value: Number(el["AMOUNT_USD"])}, {value: el["RESPONSIBLE_NAME"]}, {value: el["UF_CRM_1694781640289"]}, {value: el["UF_CRM_1702649836914"]}, {value: el["UF_CRM_1703595745329"]}]);
    });    

    let fileName = `${projectName} ჯავშნები.xlsx`;
    let sheetName1 = `reservation history`;
    let sheetName2 = `reserved units`;

    writeXlsxFile([my_json1, my_json2], {
        sheets: [sheetName1, sheetName2],
        fileName: fileName
    })
}

</script>
