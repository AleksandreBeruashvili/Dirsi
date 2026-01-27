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

function getUniqueFieldValues($fieldName, $projectFilter = null, $buildingFilter = null) {
    $arFilter = array("STAGE_ID" => "WON");
    if($projectFilter && $projectFilter != "all") {
        $arFilter["UF_CRM_1761658516561"] = $projectFilter;
    }
    if($buildingFilter) {
        $arFilter["UF_CRM_1766736693236"] = $buildingFilter;
    }
    
    $values = array();
    $res = CCrmDeal::GetList(array("ID"=>"ASC"), $arFilter, array());
    while($arDeal = $res->Fetch()) {
        if(!empty($arDeal[$fieldName]) && !in_array($arDeal[$fieldName], $values)) {
            $values[] = $arDeal[$fieldName];
        }
    }
    sort($values);
    return $values;
}

function processDealData($deal) {
    $prods = CCrmDeal::LoadProductRows($deal["ID"]);

    $arFilter = array(
        "IBLOCK_ID" => 14,
        "ID" => $prods[0]["PRODUCT_ID"]
    );

    $product = getCIBlockElementByFilter($arFilter);
    $deal["PRODUCT_INFO"] = $product;
    $deal["PAYMENT_TYPE"] = $deal["UF_CRM_1705413820965"];
    
    return $deal;
}

function getDataForTablesGrouped($project, $building = "", $block = "") {
    $dealsArr = array();
    
    $arFilter = array(
        "STAGE_ID" => "WON",
        "UF_CRM_1761658516561" => $project,
    );
    
    // Add building filter if provided
    if(!empty($building)) {
        $arFilter["UF_CRM_1766736693236"] = $building;
    }
    
    // Add block filter if provided
    if(!empty($block)) {
        $arFilter["UF_CRM_1766560177934"] = $block;
    }

    $resDeals = getDealsByFilter($arFilter);

    // Group deals by building and block
    foreach ($resDeals as $deal) {
        $deal = processDealData($deal);
        
        $dealBuilding = $deal["UF_CRM_1766736693236"];
        $dealBlock = $deal["UF_CRM_1766560177934"];
        $dealProject = $deal["UF_CRM_1761658516561"];
        
        // Create unique key for grouping
        $groupKey = $dealBuilding . "_" . $dealBlock;
        
        if(!isset($dealsArr[$groupKey])) {
            $dealsArr[$groupKey] = array(
                "BUILDING" => $dealBuilding,
                "BLOCK" => $dealBlock,
                "PROJECT" => $dealProject,
                "GENERAL" => array(
                    "FLAT" => array("COUNT" => 0, "AREA" => 0, "AMOUNT" => 0),
                    "PARKING" => array("COUNT" => 0, "AREA" => 0, "AMOUNT" => 0),
                    "COMMERCIAL" => array("COUNT" => 0, "AREA" => 0, "AMOUNT" => 0),
                    "OFFICE" => array("COUNT" => 0, "AREA" => 0, "AMOUNT" => 0),
                    "SUM" => array("COUNT" => 0, "AREA" => 0, "AMOUNT" => 0)
                ),
                "DETAILS" => array(
                    "FLAT" => array(),
                    "PARKING" => array(),
                    "COMMERCIAL" => array(),
                    "OFFICE" => array(),
                    "ALL" => array()
                )
            );
        }
        
        // Categorize and count deals
        if($deal["UF_CRM_1761658532158"] == "Flat") {
            $dealsArr[$groupKey]["GENERAL"]["FLAT"]["COUNT"]++;
            $dealsArr[$groupKey]["GENERAL"]["FLAT"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr[$groupKey]["GENERAL"]["FLAT"]["AMOUNT"] += $deal["AMOUNT_USD"];
            array_push($dealsArr[$groupKey]["DETAILS"]["FLAT"], $deal);
        } else if($deal["UF_CRM_1761658532158"] == "Parking") {
            $dealsArr[$groupKey]["GENERAL"]["PARKING"]["COUNT"]++;
            $dealsArr[$groupKey]["GENERAL"]["PARKING"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr[$groupKey]["GENERAL"]["PARKING"]["AMOUNT"] += $deal["AMOUNT_USD"];
            array_push($dealsArr[$groupKey]["DETAILS"]["PARKING"], $deal);
        } else if($deal["UF_CRM_1761658532158"] == "კომერციული") {
            $dealsArr[$groupKey]["GENERAL"]["COMMERCIAL"]["COUNT"]++;
            $dealsArr[$groupKey]["GENERAL"]["COMMERCIAL"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr[$groupKey]["GENERAL"]["COMMERCIAL"]["AMOUNT"] += $deal["AMOUNT_USD"];
            array_push($dealsArr[$groupKey]["DETAILS"]["COMMERCIAL"], $deal);
        } else if($deal["UF_CRM_1761658532158"] == "საოფისე" || $deal["UF_CRM_1761658532158"] == "საოფისე") {
            $dealsArr[$groupKey]["GENERAL"]["OFFICE"]["COUNT"]++;
            $dealsArr[$groupKey]["GENERAL"]["OFFICE"]["AREA"] += $deal["UF_CRM_1761658608306"];
            $dealsArr[$groupKey]["GENERAL"]["OFFICE"]["AMOUNT"] += $deal["AMOUNT_USD"];
            array_push($dealsArr[$groupKey]["DETAILS"]["OFFICE"], $deal);
        }
        
        $dealsArr[$groupKey]["GENERAL"]["SUM"]["COUNT"]++;
        $dealsArr[$groupKey]["GENERAL"]["SUM"]["AREA"] += $deal["UF_CRM_1761658608306"];
        $dealsArr[$groupKey]["GENERAL"]["SUM"]["AMOUNT"] += $deal["AMOUNT_USD"];
        array_push($dealsArr[$groupKey]["DETAILS"]["ALL"], $deal);
    }
    
    return $dealsArr;
}


$project = $_GET['Project'] ? $_GET['Project'] : "all";
$building = $_GET['Building'] ? $_GET['Building'] : "";
$block = $_GET['Block'] ? $_GET['Block'] : "";

// Get unique buildings and blocks for dropdowns
$buildings = getUniqueFieldValues("UF_CRM_1766736693236", $project, null);
$blocks = getUniqueFieldValues("UF_CRM_1766560177934", $project, $building);


$generalInfos = array();

if($project == "all") {
    $arrProjects = array("33");
    $generalInfos = getDataForTablesGrouped($arrProjects, $building, $block);
}
else if(!empty($project)) {
    $generalInfos = getDataForTablesGrouped($project, $building, $block);
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
    justify-content: flex-start;
}


.projectStat {
    width: 45%;
    margin: 2%;
    padding: 2%;
    box-sizing: border-box;
}

.tableHead{
    background:#8989ff;
    color:#fefeff;
}

.table {
    font-family: arial, sans-serif;
    border-collapse: collapse;
    width: 100%;
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

.groupTitle {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
    padding: 5px;
    background: #f0f0f0;
    border-radius: 5px;
}

</style>




<div class="statistic">

    <form method="get" id="newCalendarForm">
        <div style="float: left">
            <div style="float: left; margin-left: 10px;">
                <label>Project</label><br />
                <select id="Project" name="Project" style="display: inline-block;">
                    <option value="Park Boulevard">Park Boulevard</option>
                </select>
            </div>
            
            <div style="float: left; margin-left: 10px;">
                <label>Building</label><br />
                <select id="Building" name="Building" style="display: inline-block;">
                    <option value="">All Buildings</option>
                    <?php foreach($buildings as $bld): ?>
                        <option value="<?php echo htmlspecialchars($bld); ?>" <?php echo ($building == $bld) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bld); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="float: left; margin-left: 10px;">
                <label>Block</label><br />
                <select id="Block" name="Block" style="display: inline-block;">
                    <option value="">All Blocks</option>
                    <?php foreach($blocks as $blk): ?>
                        <option value="<?php echo htmlspecialchars($blk); ?>" <?php echo ($block == $blk) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($blk); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="float: left; margin-left: 10px; margin-top: 17px;">
                <input type="submit" value="Filter">
            </div>
        </div>
    </form>

    <div class="excel-btn-container">
        <img onClick="downloadExcel();" class="excel-icon" src="https://img.icons8.com/color/32/000000/export-excel.png" />
    </div>


    <div class="seperator"></div>

    <div class="title"><h1 class="titleName">Sold Statistics </h1></div>

    <div class="seperator"></div>




    <div class="statisticContainer" id="statisticContainer"></div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

<script>
    const generalInfos = <?php echo json_encode($generalInfos, JSON_UNESCAPED_UNICODE); ?>;
    const project = <?php echo json_encode($project, JSON_UNESCAPED_UNICODE); ?>;
    const building = <?php echo json_encode($building, JSON_UNESCAPED_UNICODE); ?>;
    const block = <?php echo json_encode($block, JSON_UNESCAPED_UNICODE); ?>;

    document.getElementById("Project").value = project;

    const projectNamesJson = {
        "Park Boulevard": "Park Boulevard",
    };

    drawTables(generalInfos);

    function drawTables(data) {
        const statisticContainer = document.getElementById("statisticContainer");
        statisticContainer.innerHTML = '';

        const groupKeys = Object.keys(data);
        
        if(groupKeys.length === 0) {
            statisticContainer.innerHTML = '<div style="width:100%; text-align:center; padding:50px; font-size:20px;">No data found for the selected filters.</div>';
            return;
        }

        // Calculate totals across all groups
        const totalData = {
            FLAT: {COUNT: 0, AREA: 0, AMOUNT: 0},
            PARKING: {COUNT: 0, AREA: 0, AMOUNT: 0},
            COMMERCIAL: {COUNT: 0, AREA: 0, AMOUNT: 0},
            OFFICE: {COUNT: 0, AREA: 0, AMOUNT: 0},
            SUM: {COUNT: 0, AREA: 0, AMOUNT: 0}
        };

        groupKeys.forEach(groupKey => {
            const groupData = data[groupKey];
            totalData.FLAT.COUNT += groupData.GENERAL.FLAT.COUNT;
            totalData.FLAT.AREA += groupData.GENERAL.FLAT.AREA;
            totalData.FLAT.AMOUNT += groupData.GENERAL.FLAT.AMOUNT;
            
            totalData.PARKING.COUNT += groupData.GENERAL.PARKING.COUNT;
            totalData.PARKING.AREA += groupData.GENERAL.PARKING.AREA;
            totalData.PARKING.AMOUNT += groupData.GENERAL.PARKING.AMOUNT;
            
            totalData.COMMERCIAL.COUNT += groupData.GENERAL.COMMERCIAL.COUNT;
            totalData.COMMERCIAL.AREA += groupData.GENERAL.COMMERCIAL.AREA;
            totalData.COMMERCIAL.AMOUNT += groupData.GENERAL.COMMERCIAL.AMOUNT;
            
            totalData.OFFICE.COUNT += groupData.GENERAL.OFFICE.COUNT;
            totalData.OFFICE.AREA += groupData.GENERAL.OFFICE.AREA;
            totalData.OFFICE.AMOUNT += groupData.GENERAL.OFFICE.AMOUNT;
            
            totalData.SUM.COUNT += groupData.GENERAL.SUM.COUNT;
            totalData.SUM.AREA += groupData.GENERAL.SUM.AREA;
            totalData.SUM.AMOUNT += groupData.GENERAL.SUM.AMOUNT;
        });

        // Draw TOTAL table first if there are multiple groups
        if(groupKeys.length > 1) {
            const totalTable = `
                <div class="groupTitle" style="background: #70ad47; color: white; font-size: 20px;">TOTAL SUMMARY</div>
                <table class="table">
                    <thead class="tableHead" style="background: #70ad47;">
                        <tr class="tr">
                            <th class="th">Type</th>
                            <th class="th">Count</th>
                            <th class="th">Area</th>
                            <th class="th">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="tr">
                            <td class="td prodTypeName">Apartment</td>
                            <td class="td">${totalData.FLAT.COUNT.toFixed(0)}</td>
                            <td class="td">${totalData.FLAT.AREA.toFixed(2)}</td>
                            <td class="td">${totalData.FLAT.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr">
                            <td class="td prodTypeName">Commercial</td>
                            <td class="td">${totalData.COMMERCIAL.COUNT.toFixed(0)}</td>
                            <td class="td">${totalData.COMMERCIAL.AREA.toFixed(2)}</td>
                            <td class="td">${totalData.COMMERCIAL.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr">
                            <td class="td prodTypeName">Office</td>
                            <td class="td">${totalData.OFFICE.COUNT.toFixed(0)}</td>
                            <td class="td">${totalData.OFFICE.AREA.toFixed(2)}</td>
                            <td class="td">${totalData.OFFICE.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr">
                            <td class="td prodTypeName">Parking</td>
                            <td class="td">${totalData.PARKING.COUNT.toFixed(0)}</td>
                            <td class="td">${totalData.PARKING.AREA.toFixed(2)}</td>
                            <td class="td">${totalData.PARKING.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr" style="background: #e8f5e9; font-weight: bold;">
                            <td class="td prodTypeName">Total</td>
                            <td class="td">${totalData.SUM.COUNT.toFixed(0)}</td>
                            <td class="td">${totalData.SUM.AREA.toFixed(2)}</td>
                            <td class="td">${totalData.SUM.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                    </tbody>
                </table>
            `;

            statisticContainer.innerHTML += `
                <div class="projectStat" style="width: 95%; border: 3px solid #70ad47; margin-bottom: 30px;">
                    ${totalTable}
                </div>
            `;
        }

        // Draw individual group tables
        groupKeys.forEach(groupKey => {
            const groupData = data[groupKey];
            const projEN = projectNamesJson[groupData.PROJECT] || groupData.PROJECT;
            const buildingName = groupData.BUILDING || 'N/A';
            const blockName = groupData.BLOCK || 'N/A';
            
            const tableTitle = `${projEN} - Building: ${buildingName} - Block: ${blockName}`;
            
            table = `
                <div class="groupTitle">${tableTitle}</div>
                <table class="table">
                    <thead class="tableHead">
                        <tr class="tr">
                            <th class="th">Type</th>
                            <th class="th">Count</th>
                            <th class="th">Area</th>
                            <th class="th">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="tr">
                            <td class="td prodTypeName">Apartment</td>
                            <td class="td">${groupData.GENERAL.FLAT.COUNT.toFixed(0)}</td>
                            <td class="td">${groupData.GENERAL.FLAT.AREA.toFixed(2)}</td>
                            <td class="td">${groupData.GENERAL.FLAT.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr">
                            <td class="td prodTypeName">Commercial</td>
                            <td class="td">${groupData.GENERAL.COMMERCIAL.COUNT.toFixed(0)}</td>
                            <td class="td">${groupData.GENERAL.COMMERCIAL.AREA.toFixed(2)}</td>
                            <td class="td">${groupData.GENERAL.COMMERCIAL.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr">
                            <td class="td prodTypeName">Office</td>
                            <td class="td">${groupData.GENERAL.OFFICE.COUNT.toFixed(0)}</td>
                            <td class="td">${groupData.GENERAL.OFFICE.AREA.toFixed(2)}</td>
                            <td class="td">${groupData.GENERAL.OFFICE.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr">
                            <td class="td prodTypeName">Parking</td>
                            <td class="td">${groupData.GENERAL.PARKING.COUNT.toFixed(0)}</td>
                            <td class="td">${groupData.GENERAL.PARKING.AREA.toFixed(2)}</td>
                            <td class="td">${groupData.GENERAL.PARKING.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                        <tr class="tr">
                            <td class="td prodTypeName">Total</td>
                            <td class="td">${groupData.GENERAL.SUM.COUNT.toFixed(0)}</td>
                            <td class="td">${groupData.GENERAL.SUM.AREA.toFixed(2)}</td>
                            <td class="td">${groupData.GENERAL.SUM.AMOUNT.toLocaleString('en-US', {style: 'currency', currency: 'USD'})}</td>
                        </tr>
                    </tbody>
                </table>
            `;

            statisticContainer.innerHTML += `
                <div class="projectStat">
                    ${table}
                </div>
            `;
        });
    }

    function downloadExcel() {
        const wb = XLSX.utils.book_new();

        let my_json1 = [];
        let projectsArr = [];

        Object.keys(generalInfos).forEach(groupKey => {
            const groupData = generalInfos[groupKey];
            
            groupData.DETAILS.ALL.forEach(el => {
                let kvMetriFasi = (Number(el["UF_CRM_1665571815062"]) || 0).toFixed(2);
        
                if (el["UF_CRM_1761658532158"] === "პარკინგი") {
                    kvMetriFasi = "";
                }
                
                my_json1.push({
                    "პროექტი": projectNamesJson[el["UF_CRM_1761658516561"]], 
                    "შენობა": el["UF_CRM_1766736693236"],
                    "ბლოკი": el["UF_CRM_1766560177934"],
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
                    "კომენტარი/შენიშვნა": el["UF_CRM_1740467769492"]
                });

                if(!projectsArr.includes(el["UF_CRM_1761658516561"])) {
                    projectsArr.push(el["UF_CRM_1761658516561"]);
                }
            });
        });

        let projectsText = projectsArr.join(", ");

        let fileName = `გაყიდული ობიექტები.xlsx`;
        let sheetName1 = `DATA`;

        let ws1 = XLSX.utils.json_to_sheet(my_json1);

        XLSX.utils.book_append_sheet(wb, ws1, sheetName1);

        XLSX.writeFile(wb, fileName);
    }

</script>