<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
CJSCore::Init(array("jquery"));



function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $arDeals = array();
    $res = CCrmDeal::GetListEx($arSort, $arFilter,false,false, array(
        "ID",
        "OPPORTUNITY",
        "UF_CRM_1761658516561",    // project
        "UF_CRM_1762416342444",    // xelshekrulebis gaformis tarigi
        "UF_CRM_1762948106980",    // korpusi
        "UF_CRM_1761658559005",    // binis nomeri
        "UF_CRM_1761658577987",    // sartuli
        "CURRENCY_ID",             // valuta
        "COMPANY_TITLE",
        "CONTACT_FULL_NAME",
        "CONTACT_ID",
        "COMPANY_ID"
    ));
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : array();
}

function getJami($arr){
    $jami = 0;
    foreach($arr as $elem){
        $jami +=$elem;
    }
    return $jami;
}

function getCIBlockElementsByFilter($arFilter = array()) {
    $arElements = array();
    $arSelect = array("ID","PROPERTY_TANXA","PROPERTY_TARIGI","PROPERTY_date","DELA","PROPERTY_refund");
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>1000000), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        $arPushs["image"]    = CFile::GetPath($arPushs["DETAIL_PICTURE"]);
        $arPushs["image1"]    = CFile::GetPath($arPushs["PREVIEW_PICTURE"]);
        $price      = CPrice::GetBasePrice($arPushs["ID"]);
        $arPushs["PRICE"] = $price["PRICE"];

        array_push($arElements, $arPushs);
    }
    return $arElements;
}


function getDaricxvebi($deals, $iblockId = 20){
    $arFilter=array(
        "IBLOCK_ID" =>$iblockId,
        "PROPERTY_DEAL" =>$deals,
    );
    $daricxvebi = getCIBlockElementsByFilter($arFilter);
    $daricxvaJami = 0;
    foreach($daricxvebi as $daricxva){
        $tanxa = str_replace("|USD", "", $daricxva["PROPERTY_TANXA_VALUE"]);
        $daricxvaJami +=$tanxa;
    }
    return $daricxvaJami;
}

function getGadaxda($deals, $iblockId = 21){
    $arFilter=array(
        "IBLOCK_ID" =>$iblockId,
        "PROPERTY_DEAL" =>$deals,
    );
    $daricxvebi = getCIBlockElementsByFilter($arFilter);
    $daricxvaJami = 0;
    foreach($daricxvebi as $daricxva){
        $tanxa = str_replace("|USD", "", $daricxva["PROPERTY_TANXA_VALUE"]);
        $daricxvaJami +=$tanxa;
    }
    return $daricxvaJami;
}




function docs($deals, $iblockId = 34){
    $arFilter=array(
        "IBLOCK_ID" =>$iblockId,
        "PROPERTY_DELA" =>$deals,
    );
    $docs = getCIBlockElementsByFilter($arFilter);

    $haveOrNot="no";
    if($docs){
        $haveOrNot="yes";
    }

    return $haveOrNot;
}


function getDealStagesByCategory($categoryID) {
    $dealStages = CCrmDeal::GetStageNames($categoryID);
    return $dealStages;
}


function getDealProds($dealID)
{
    $prods = CCrmDeal::LoadProductRows($dealID);
    $products = [];
    foreach ($prods as $prod) {
        $arFilter = array(
            "ID" => $prod["PRODUCT_ID"]
        );
        $each = getCIBlockElementsByFilter($arFilter);
        $price = CPrice::GetBasePrice($prod["PRODUCT_ID"]);
        $each["PRICE"] = $price["PRICE"];
        $each["RAODENOBA"] = $prod["QUANTITY"];
        $each["PRODUCT_ID"] = $prod["PRODUCT_ID"];

        array_push($products, $each);
    }
    return $products;
}

$project = $_GET["project"];

$stages =  array();
$arFilter=array("STAGE_ID"=>"WON");
$deals=getDealsByFilter($arFilter);



$dataArr=array();
$dealSum = 0;
$daricxvaSum =0;
$gadaxdaSum = 0;
$dealCount = 0;
$uniqueClients = array();

foreach($deals as $deal){


    $res = array();
    $res["ID"] = $deal["ID"];
    $prods = getDealProds($deal["ID"]);

    $prodID=$prods[0]["PRODUCT_ID"];

    $res["productID"] = "<a target='_blank' href='/crm/catalog/14/product/$prodID/'>$prodID</a>";

    $res["project"] = $deal["UF_CRM_1761658516561"];
    $res["corpus"] = $deal["UF_CRM_1762948106980"];
    $res["flatNum"] = $deal["UF_CRM_1761658559005"];
    $res["flatFloor"] = $deal["UF_CRM_1761658577987"];
    $res["xelshGafDate"] = $deal["UF_CRM_1762416342444"];

    $res["valute"] = $deal["CURRENCY_ID"];

    $contactID = $deal["CONTACT_ID"];
    $contactName = $deal["CONTACT_FULL_NAME"];
    $companID= $deal["COMPANY_ID"];
    $companyName = $deal["COMPANY_TITLE"];

    // Show contact if exists, otherwise show company
    if($contactName) {
        $res["client"] = "<a target='_blank' href='/crm/contact/details/$contactID/'>$contactName</a>";
        $clientNameForCount = $contactName;
    } else if($companyName) {
        $res["client"] = "<a target='_blank' href='/crm/company/details/$companID/'>$companyName</a>";
        $clientNameForCount = $companyName;
    } else {
        $res["client"] = "";
        $clientNameForCount = "";
    }

    // Track unique clients
    if($clientNameForCount) {
        $uniqueClients[$clientNameForCount] = true;
    }

    $res["PRICE"] = $deal["OPPORTUNITY"];
    $res["daricxva"] = round(getDaricxvebi($deal["ID"]),2);
    $res["gadaxda"] = round(getGadaxda($deal["ID"]),2);

    $res["gafDate"] = $deal["UF_CRM_1693398443196"];
    $res["docs"] = docs($deal["ID"]);

    $dealSum += $res["PRICE"];
    $daricxvaSum += $res["daricxva"];
    $gadaxdaSum += $res["gadaxda"];

    $dealCount++;

    array_push($dataArr,$res);
}

$dealSum = round($dealSum,2);
$daricxvaSum = round($daricxvaSum,2);
$gadaxdaSum = round($gadaxdaSum,2);

$clientCount = count($uniqueClients);


ob_end_clean();
?>
<style>

    .reportTable {
        font-family: arial, sans-serif;
        border-collapse: collapse;
        width: 100%;
    }

    .reportTable td,
    .reportTable th {
        border: 1px solid #dddddd;
        text-align: left;
        padding: 8px;
    }

    .reportTable tr:nth-child(even) {
        background-color: #dddddd;
    }

    .export_button{
        width: 120px;
        height: 40px;
        color: white;
        background-color: #149c3b;
        box-shadow: 5px 10px 20px #001e7f inset;
        border-radius: 4px;
        cursor: pointer;
        box-shadow: 5px 10px 8px #888888;
        margin-left: 80px;
        float: right;
    }

    .export_button:hover{
        background-color: #037b27;
        box-shadow: 5px 10px 20px rgba(27, 79, 250, 0.91) inset;
        border-radius: 4px;
        color: #cbc8c8;
        cursor: pointer;
        box-shadow: 5px 10px 8px #c1c1c1;
    }

    .header-totals {
        background-color: #f0f0f0;
        font-weight: bold;
    }

</style>
<div style="width: 100%; height: 70px; margin-left: 20;">
    <table>
        <tr>
            <td><label>პროექტი: </label></td>
            <td><label>კორპუსი: </label></td>
        </tr>
        <tr>
            <td>
                <select name="project" id='project' class="dropdown">
                    <option value="">TOTAL</option>
                </select>
            </td>
            <td>
                <select name="corpus" id='corpus' class="dropdown">
                    <option value="">TOTAL</option>
                </select>
            </td>
        </tr>
    </table>

</div>

<div>
    <button class="export_button" onclick="exportTableToExcel();"><i>ექსპორტი Excel</i></button>
</div>
<table style="width: 100%" class = "reportTable" id = "reportTable">
    <thead class="reportTableHead">
    <tr class="header-totals" style="background-color: #e3f2fd; font-weight: bold;">
        <td id="dealCountHeader">რაოდენობა: <?php echo $dealCount; ?></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td id="clientCountHeader">რაოდენობა: <?php echo $clientCount; ?></td>
        <td id="priceSum">ჯამი: <?php echo number_format($dealSum, 2); ?></td>
        <td id="daricxvaSum">ჯამი: <?php echo number_format($daricxvaSum, 2); ?></td>
        <td id="gadaxdaSum">ჯამი: <?php echo number_format($gadaxdaSum, 2); ?></td>
    </tr>
    <tr>
        <th>ID</th>
        <th>პროდუქტის ID</th>
        <th>დოკუმენტი</th>
        <th>პროექტი</th>
        <th>კორპუსი</th>
        <th>ბინის ნომერი</th>
        <th>სართული</th>
        <th>ხელშ. გაფორმების თარიღი</th>
        <th>ვალუტა</th>
        <th>კლიენტი</th>
        <th>ბინის ღირებულება</th>
        <th>დარიცხვები</th>
        <th>გადახდები</th>
    </tr>
    </thead>

    <tbody id="tableBody"></tbody>

    </tbody>
</table>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<script type="text/javascript" src="//unpkg.com/xlsx/dist/shim.min.js"></script>
<script type="text/javascript" src="//unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>

<script>
    let dataArr = <?php echo json_encode($dataArr);?>;
    let dealSum = <?php echo json_encode($dealSum);?>;
    let daricxvaSum = <?php echo json_encode($daricxvaSum);?>;
    let gadaxdaSum = <?php echo json_encode($gadaxdaSum);?>;

    const projectSelect = document.getElementById("project");
    const corpusSelect = document.getElementById("corpus");
    const tableBody = document.getElementById("tableBody");


    projectSelect.addEventListener("change", filterTable);
    corpusSelect.addEventListener("change", filterTable);

    populateFilters();
    drawTable(dataArr);

    function drawTable(filteredData) {
        tableBody.innerHTML = "";

        // Calculate totals for filtered data
        let filteredDealSum = 0;
        let filteredDaricxvaSum = 0;
        let filteredGadaxdaSum = 0;
        let filteredDealCount = filteredData.length;
        let uniqueClients = {};

        filteredData.forEach(item => {
            // Extract client name from HTML link
            let clientName = item.client.match(/>([^<]+)</);
            if(clientName && clientName[1]) {
                uniqueClients[clientName[1]] = true;
            }

            filteredDealSum += parseFloat(item.PRICE) || 0;
            filteredDaricxvaSum += parseFloat(item.daricxva) || 0;
            filteredGadaxdaSum += parseFloat(item.gadaxda) || 0;

            const row = document.createElement("tr");
            row.innerHTML = `
            <td class="tableData"><a target='_blank' href='/crm/deal/details/${safe(item.ID)}/'>${safe(item.ID)}</a></td>
            <td>${safe(item.productID)}</td>
            <td>${safe(item.docs)}</td>
            <td>${safe(item.project)}</td>
            <td>${safe(item.corpus)}</td>
            <td>${safe(item.flatNum)}</td>
            <td>${safe(item.flatFloor)}</td>
            <td>${safe(item.xelshGafDate)}</td>
            <td>${safe(item.valute)}</td>
            <td>${item.client}</td>
            <td>${safe(item.PRICE)}</td>
            <td>${safe(item.daricxva)}</td>
            <td>${safe(item.gadaxda)}</td>
        `;
            tableBody.appendChild(row);
        });

        // Update header counts and sums
        document.getElementById("dealCountHeader").innerHTML = "რაოდენობა: " + filteredDealCount;
        document.getElementById("clientCountHeader").innerHTML = "რაოდენობა: " + Object.keys(uniqueClients).length;
        document.getElementById("priceSum").innerHTML = "ჯამი: " + getDotted(filteredDealSum);
        document.getElementById("daricxvaSum").innerHTML = "ჯამი: " + getDotted(filteredDaricxvaSum);
        document.getElementById("gadaxdaSum").innerHTML = "ჯამი: " + getDotted(filteredGadaxdaSum);
    }

    function safe(value) {
        return (value === null || value === undefined) ? "" : value;
    }


    function populateFilters() {
        const projects = [...new Set(dataArr.map(item => item.project))].filter(p => p && p.toString().trim() !== "").sort();
        const corpuses = [...new Set(dataArr.map(item => item.corpus))].filter(c => c && c.toString().trim() !== "").sort((a, b) => {
            // Sort alphanumerically (A-1, A-2, ... A-10, B-1, etc.)
            return a.toString().localeCompare(b.toString(), undefined, {numeric: true, sensitivity: 'base'});
        });

        projects.forEach(p => {
            const opt = document.createElement("option");
            opt.value = p;
            opt.textContent = p;
            projectSelect.appendChild(opt);
        });

        corpuses.forEach(c => {
            const opt = document.createElement("option");
            opt.value = c;
            opt.textContent = c;
            corpusSelect.appendChild(opt);
        });
    }

    function filterTable() {
        const selectedProject = projectSelect.value;
        const selectedCorpus = corpusSelect.value;

        const filtered = dataArr.filter(item => {
            return (selectedProject === "" || item.project === selectedProject) &&
                (selectedCorpus === "" || item.corpus === selectedCorpus);
        });

        drawTable(filtered);
    }


    function getDotted(numb){
        numb = parseFloat(numb).toFixed(2);
        numb = numb.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return numb;
    }

    function exportTableToExcel(){
        var tableSelect = document.getElementById("reportTable");
        let wb = XLSX.utils.book_new();
        wb.Props = {
            Title: "daricxva gadaxda",
            Subject: "report",
            Author:"asgroup",
            CreatedDate: new Date()
        };

        let ws_data = [];

        // First row - totals/counts row
        let totalsRow = [];
        let totalsRowElement = tableSelect.rows[0];

        // Get all cells from the totals row
        for (let j = 0; j < totalsRowElement.cells.length; j++) {
            totalsRow.push(totalsRowElement.cells[j].innerText || "");
        }
        ws_data.push(totalsRow);

        // Second row - headers
        let headerRow = [];
        let headerRowElement = tableSelect.rows[1];
        for (let j = 0; j < headerRowElement.cells.length; j++) {
            headerRow.push(headerRowElement.cells[j].innerText || "");
        }
        ws_data.push(headerRow);

        // Data rows - start from row 2 (skip the two header rows)
        let tbody = tableSelect.getElementsByTagName("tbody")[0];
        if (tbody) {
            for (let i = 0; i < tbody.rows.length; i++) {
                let row = [];
                for (let j = 0; j < tbody.rows[i].cells.length; j++) {
                    let cellText = tbody.rows[i].cells[j].innerText || "";

                    // For numeric columns (price, daricxva, gadaxda - columns 10, 11, 12)
                    if(j >= 10) {
                        let numberString = cellText.replace(/,/g, "");
                        let number = parseFloat(numberString);
                        row.push(isNaN(number) ? 0 : number);
                    } else {
                        row.push(cellText);
                    }
                }
                ws_data.push(row);
            }
        }

        let ws = XLSX.utils.aoa_to_sheet(ws_data);
        XLSX.utils.book_append_sheet(wb, ws, "daricxva gadaxda");
        XLSX.writeFile(wb, "daricxva_gadaxda.xlsx");
    }


</script>
