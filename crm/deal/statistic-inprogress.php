<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));
\CJSCore::Init(['date']);

$APPLICATION->SetTitle("მიმდინარე სტატუსების სტატისტიკა");

// ============== GLOBALS ================== //

$months = array("January" => "იანვარი", "February" => "თებერვალი", "March" => "მარტი", "April" => "აპრილი", "May" => "მაისი", "June" => "ივნისი", "July" => "ივლისი", "August" => "აგვისტო", "September" => "სექტემბერი", "October" => "ოქტომბერი", "November" => "ნოემბერი", "December" => "დეკემბერი");
$DATE = "DATE_CREATE";

// =========================================

// =============== FUNCTIONS =============== //

// დინამიურად მოპოვებული წყაროები
function getAllSources() {
    $sources = array();
    $res = CCrmStatus::GetList(array(), array("ENTITY_ID" => "SOURCE"));
    while ($source = $res->Fetch()) {
        $sources[] = $source["STATUS_ID"];
    }
    return $sources;
}

// წყაროების სახელების მოპოვება
function getSourceNames() {
    $sourceNames = array();
    $res = CCrmStatus::GetList(array(), array("ENTITY_ID" => "SOURCE"));
    while ($source = $res->Fetch()) {
        $sourceNames[$source["STATUS_ID"]] = $source["NAME"];
    }
    return $sourceNames;
}


// გაფილტრული წყაროების მოპოვება
function getFilteredSources() {
    return getAllSources();
}

function getDeals($arFilter, $arSelect = array("ID","SOURCE_ID","STAGE_ID","DATE_CREATE","CATEGORY_ID", "ASSIGNED_BY_ID", "ASSIGNED_BY_NAME", "ASSIGNED_BY_LAST_NAME","CREATED_BY_ID","UF_CRM_1700053950", "UF_CRM_1693385992603"), $arSort = array("ID" => "ASC"))
{
    $arLeads = array();
    $res = CCrmDeal::GetListEx($arSort, $arFilter, false, false, $arSelect);
    while ($arLead = $res->Fetch()) {
        $arLeads[] = $arLead;
    }
    return $arLeads;
}

function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID" => "ASC"))
{
    $arDeals = array();
    $arSelect=array("ID","OPPORTUNITY","UF_CRM_1693398443196","UF_CRM_1693385992603","UF_CRM_1701778190","UF_CRM_1714653874003");
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function printArr($arr)
{
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function getUsersdsByArFilter ($arFilter) {
    $arrUsers=array();
    $arSelect = array('SELECT' => array("ID","NAME", "LAST_NAME"));
    $res=array();
    $rsUsers = CUser::GetList(($by = "NAME"), ($order = "desc"), $arFilter, $arSelect);

    while ($arUser = $rsUsers->Fetch()) {
        array_push($res,$arUser);
    }
    return $res;
}



function getSource($inProgressStages, $date, $userId = null) {
    $sources = getFilteredSources();
    $sourceNames = getSourceNames();
    
    $arFilter = array(
        "SOURCE_ID" => $sources,
        ">=DATE_CREATE" => $date['START'],
        "<=DATE_CREATE" => $date["END"]." 11:59:59 pm",
        "CATEGORY_ID" => 0,
    );

    if ($userId && $userId != "all") {
        $arFilter["ASSIGNED_BY_ID"] = $userId;
    }

    $deals = getDeals($arFilter);
    $sourceCounts = array();
    
    // ყველა წყაროს საწყისი რაოდენობა 0
    foreach ($sources as $sourceId) {
        $sourceName = isset($sourceNames[$sourceId]) ? $sourceNames[$sourceId] : $sourceId;
        $sourceCounts[$sourceName] = 0;
    }

    // რაოდენობების დათვლა
    foreach ($deals as $deal) {
        $sourceId = $deal['SOURCE_ID'];
        $sourceName = isset($sourceNames[$sourceId]) ? $sourceNames[$sourceId] : $sourceId;
        
        if (isset($sourceCounts[$sourceName])) {
            $sourceCounts[$sourceName]++;
        }
    }

    // მხოლოდ რაოდენობა > 0 წყაროების დაბრუნება
    $resultArray = array();
    foreach ($sourceCounts as $name => $value) {
        if ($value > 0) {
            $resultArray[] = ['name' => $name, 'value' => $value];
        }
    }
    
    return $resultArray;
}

function getDataForStage($stageID, $date, $userId = null)
{
    $sources = getFilteredSources();

    $arFilter = array(
        "SOURCE_ID" => $sources,
        ">=DATE_CREATE" => $date['START'],
        "<=DATE_CREATE" => $date["END"] . " 11:59:59 pm",
        "CATEGORY_ID" => 0,
    );

    if ($stageID != "all") {
        $arFilter["STAGE_ID"] = $stageID;
    }

    if ($userId && $userId != "all") {
        $arFilter["ASSIGNED_BY_ID"] = $userId;
    }

    $deals = getDeals($arFilter);
    $res = array();

    foreach ($deals as $deal) {
        $tanam_name = $deal["ASSIGNED_BY_NAME"] . " " . $deal["ASSIGNED_BY_LAST_NAME"];
        if (!array_key_exists($tanam_name, $res)) {
            $res[$tanam_name] = 1;
        } else {
            $res[$tanam_name]++;
        }
    }

    $resultArray = [];
    foreach ($res as $name => $value) {
        $resultArray[] = ['name' => $name, 'value' => $value];
    }

    return $resultArray;
}

function getDataForWonDeals($date, $userId = null)
{
    $sources = getFilteredSources();

    $arFilter = array(
        "STAGE_ID" => "WON",
        "SOURCE_ID" => $sources,
        ">=UF_CRM_1693398443196" => $date['START'],
        "<=UF_CRM_1693398443196" => $date["END"] . " 11:59:59 pm",
        "CATEGORY_ID" => 0,
    );

    if ($userId && $userId != "all") {
        $arFilter["ASSIGNED_BY_ID"] = $userId;
    }

    $deals = getDealsByFilter($arFilter);
    $res = array();

    foreach ($deals as $deal) {
        $tanam_name = $deal["ASSIGNED_BY_NAME"] . " " . $deal["ASSIGNED_BY_LAST_NAME"];
        if (!array_key_exists($tanam_name, $res)) {
            $res[$tanam_name] = 1;
        } else {
            $res[$tanam_name]++;
        }
    }

    $resultArray = [];
    foreach ($res as $name => $value) {
        $resultArray[] = ['name' => $name, 'value' => $value];
    }

    return $resultArray;
}


function getDataForLostDeals($date, $userId = null)
{
    $sources = getFilteredSources();

    $arFilter = array(
        "STAGE_ID" => "LOSE",
        ">=DATE_CREATE" => $date['START'],
        "<=DATE_CREATE" => $date["END"] . " 11:59:59 pm",
        "CATEGORY_ID" => 0,
    );

    if ($userId && $userId != "all") {
        $arFilter["ASSIGNED_BY_ID"] = $userId;
    }

    $deals = getDeals($arFilter);
    $res = array();

    foreach ($deals as $deal) {
        $tanam_name = $deal["ASSIGNED_BY_NAME"] . " " . $deal["ASSIGNED_BY_LAST_NAME"];
        if (!array_key_exists($tanam_name, $res)) {
            $res[$tanam_name] = 1;
        } else {
            $res[$tanam_name]++;
        }
    }

    $resultArray = [];
    foreach ($res as $name => $value) {
        $resultArray[] = ['name' => $name, 'value' => $value];
    }

    return $resultArray;
}

function getDataForExcel($deals, $startDate, $endDate, $type) {
    $resToReturn = [];
    $sourceNames = getSourceNames();
    
    $res = [];
    
    // თარიღების ფორმატის გარდაქმნა
    $startDate = str_replace("/", ".", $startDate);
    $endDate = str_replace("/", ".", $endDate);

    $start = DateTime::createFromFormat('d.m.Y', $startDate);
    $end = DateTime::createFromFormat('d.m.Y', $endDate);

    if (!$start || !$end) {
        echo "Error: Invalid date format ($startDate - $endDate). Ensure format is DD.MM.YYYY.";
        return [];
    }

    // მხოლოด შერჩეული დიაპაზონის თარიღები
    while ($start <= $end) {
        $dateKey = $start->format('d.m.Y');
        $res[$dateKey] = array_fill_keys([
            "შემოსული ლიდები ჯამში", "მიღებული ზარები ჯამში", "WEB ლიდი ჯამში", "FB მესენჯერი ჯამში","FB კომენტარი ჯამში", 
            "Instagram ლიდი ჯამში", "შემოსული ლიდი","მოგვიანებით დავურეკო", "ინფორმაცია გაგზავნილია","ელოდება შეთავაზებას","დაინტერესებულია/განიხილავს","აქტიური მოლაპარაკება","შეხვედრა ჩანიშნულია",
            "ჯავშნის რიგი","დაჯავშნა","ხელშეკრულება","ხელშეკრულების დადასტურება","ჩარიცხვის მოლოდინში","გაყიდული ბინა"
        ], 0);
        $start->modify('+1 day');
    }

    foreach ($deals as $deal) {
        $dateCreateArr = explode(" ", $deal["DATE_CREATE"]);
        $dayCreate = str_replace("/", ".", $dateCreateArr[0]);
        $sourceID = $deal["SOURCE_ID"];

        if (isset($res[$dayCreate])) {
            $res[$dayCreate]["შემოსული ლიდები ჯამში"]++;

            // დინამიურად განისაზღვრება წყაროს ტიპი
            $sourceName = isset($sourceNames[$sourceID]) ? $sourceNames[$sourceID] : $sourceID;
            
            // კატეგორიზაცია წყაროების სახელების მიხედვით
            if (stripos($sourceName, "ზარ") !== false || stripos($sourceName, "call") !== false) {
                $res[$dayCreate]["მიღებული ზარები ჯამში"]++;
            }
            if (stripos($sourceName, "web") !== false || stripos($sourceName, "ონლაინ") !== false) {  
                $res[$dayCreate]["WEB ლიდი ჯამში"]++;
            }
            if (stripos($sourceName, "messenger") !== false || stripos($sourceName, "მესენჯერ") !== false) {
                $res[$dayCreate]["FB მესენჯერი ჯამში"]++;
            }
            if (stripos($sourceName, "facebook") !== false && stripos($sourceName, "comment") !== false) {
                $res[$dayCreate]["FB კომენტარი ჯამში"]++;
            }
            if (stripos($sourceName, "instagram") !== false) {
                $res[$dayCreate]["Instagram ლიდი ჯამში"]++;
            }
        }

        // სტატუსების დამუშავება
        if (isset($res[$dayCreate])) {
            switch ($deal["STAGE_ID"]) {
                case "NEW":
                    $res[$dayCreate]["შემოსული ლიდი"]++;
                    break;
                case "UC_TTIL7S":
                    $res[$dayCreate]["მოგვიანებით დავურეკო"]++;
                    break;
                case "UC_Y21FZK":
                    $res[$dayCreate]["ინფორმაცია გაგზავნილია"]++;
                    break;
                case "UC_0YQWZ5":
                    $res[$dayCreate]["ელოდება შეთავაზებას"]++;
                    break;
                case "UC_9QIHB1":
                    $res[$dayCreate]["დაინტერესებულია/განიხილავს"]++;
                    break;
                case "UC_8KYNPG":
                    $res[$dayCreate]["აქტიური მოლაპარაკება"]++;
                    break;
                case "UC_3XKZNP":
                    $res[$dayCreate]["შეხვედრა ჩანიშნულია"]++;
                    break;
                case "UC_4QU5BL":
                    $res[$dayCreate]["შეხვედრა შედგა"]++;
                    break;    
                case "19":
                    $res[$dayCreate]["ჯავშნის რიგი"]++;
                    break;
                case "UC_2OKWI1":
                    $res[$dayCreate]["დაჯავშნა"]++;
                    break;
                case "UC_XG2GSV":
                    $res[$dayCreate]["ხელშეკრულება"]++;
                    break;
                case "UC_LI19P8":
                    $res[$dayCreate]["ხელშეკრულების დადასტურება"]++;
                    break;
                case "UC_FQGDYH":
                    $res[$dayCreate]["ჩარიცხვის მოლოდინში"]++;
                    break;
            }
        }

        // WON დილების დამუშავება
        if ($deal["STAGE_ID"] == "WON" && isset($deal["UF_CRM_1693398443196"]) && !empty($deal["UF_CRM_1693398443196"])) {
            $dateWonArr = explode(" ", $deal["UF_CRM_1693398443196"]);
            $dateWon = str_replace("/", ".", $dateWonArr[0]);
            if (isset($res[$dateWon])) {
                $res[$dateWon]["გაყიდული ბინა"]++;
            }
        }
    }

    // ტიპის მიხედვით დაჯგუფება
    if ($type == "day") {
        foreach ($res as $key => $singleDay) {
            $resToReturn[] = ["პერიოდი" => $key] + $singleDay;
        }
    } elseif ($type == "week") {
        $aggregatedRes = [];
        $keys = array_keys($res);
        $totalDays = count($keys);
        
        // კვირის ჯგუფი - 7 დღე
        $i = 0;
        while ($i < $totalDays) {
            $startPeriod = $keys[$i];
            $endIndex = min($i + 6, $totalDays - 1);
            $endPeriod = $keys[$endIndex];
            $periodKey = "$startPeriod - $endPeriod";

            $aggregatedRes[$periodKey] = array_fill_keys(array_keys($res[$keys[0]]), 0);

            for ($j = $i; $j <= $endIndex; $j++) {
                foreach ($res[$keys[$j]] as $metric => $value) {
                    $aggregatedRes[$periodKey][$metric] += $value;
                }
            }

            $i += 7;
        }

        foreach ($aggregatedRes as $key => $aggregatedData) {
            $resToReturn[] = ["პერიოდი" => $key] + $aggregatedData;
        }

    } elseif ($type == "month") {
        $monthlyRes = [];

        foreach ($res as $date => $data) {
            $monthKey = date("Y-m", strtotime(str_replace(".", "/", $date)));

            if (!isset($monthlyRes[$monthKey])) {
                $monthlyRes[$monthKey] = array_fill_keys(array_keys($data), 0);
            }

            foreach ($data as $metric => $value) {
                $monthlyRes[$monthKey][$metric] += $value;
            }
        }

        foreach ($monthlyRes as $key => $monthlyData) {
            $resToReturn[] = ["პერიოდი" => $key] + $monthlyData;
        }
    }

    return $resToReturn;
}

function getRedirectedData($date, $employees, $sales) {
    $res = array();
    $sources = getFilteredSources();

    foreach ($sales as $sale) {
        $res[$sale["NAME"]." ".$sale["LAST_NAME"]] = 0;
    }

    foreach ($employees as $employee) {
        $arFilter = array(
            "ASSIGNED_BY_ID" => $employee["ID"],
            "SOURCE_ID" => $sources,
            ">=DATE_CREATE" => $date['START'],
            "<=DATE_CREATE" => $date["END"]." 11:59:59 pm",
            "CATEGORY_ID" => 0,
        );

        $deals = getDeals($arFilter); 

        foreach ($deals as $deal) {
            $responsibleName = $deal['ASSIGNED_BY_NAME']." ".$deal["ASSIGNED_BY_LAST_NAME"];
            if (array_key_exists($responsibleName, $res)) {
                $res[$responsibleName]++;
            }
        }
    }

    foreach ($res as $name => $value) {
        $resultArray[] = ['name' => $name, 'value' => $value];
    }
    return $resultArray;
}

// ავტორიზაციის შემოწმება
global $USER;

if($USER->GetID()){
    $NotAuthorized = false;
    $user_id = $USER->GetID();
    $USER->Authorize(1);
} else {
    $NotAuthorized = true;
    $USER->Authorize(1);
}

$dateArr = array();
$today = date("d/m/Y");

if ($_GET['startDate']) {
    $dateArr["START"] = $_GET['startDate'];
} else {
    $dateArr["START"] = $today;
}

if ($_GET['endDate']) {
    $dateArr["END"] = $_GET['endDate'];
} else {
    $dateArr["END"] = $today;
}

$arFilter = array("UF_DEPARTMENT" => array(3));
$users = getUsersdsByArFilter($arFilter);

$adminUser = array();
$adminUser["ID"] = 1;
$adminUser["NAME"] = "Admin";
$adminUser["LAST_NAME"] = "adminadze";
array_push($users, $adminUser);

$sales = getUsersdsByArFilter($arFilter);

$inProgressStages = getFilteredSources();

$selectedUserId = isset($_GET['userId']) ? $_GET['userId'] : 'all';


$sumLeads = getDataForStage("all", $dateArr, $selectedUserId);
$sources2 = getSource($inProgressStages, $dateArr, $selectedUserId);
$incomingLeads = getDataForStage("NEW", $dateArr, $selectedUserId);
$pirveladiKomunikacia = getDataForStage(["PREPAYMENT_INVOICE","UC_12CJ1Z","UC_2EW8VW","UC_15207E","EXECUTING","UC_BAUB5P","UC_F3FOBF","FINAL_INVOICE","1","2","3","4"], $dateArr, $selectedUserId);
$wonDeals = getDataForWonDeals($dateArr, $selectedUserId);
$lostDeals = getDataForLostDeals($dateArr, $selectedUserId);


$startDate = $_GET['startDate'] ?? date("d/m/Y");
$endDate = $_GET['endDate'] ?? date("d/m/Y");


$arfiltermonth = array();
$arfiltermonth[">=DATE_CREATE"] = $dateArr["START"];
$arfiltermonth["<=DATE_CREATE"] = $dateArr["END"] . " 11:59:59 pm";
$arfiltermonth["CATEGORY_ID"] = 0;

$sources = getFilteredSources();
$arfiltermonth["SOURCE_ID"] = $sources;

$onemonthDealsExcel = getDeals($arfiltermonth);
$dailyReport = getDataForExcel($onemonthDealsExcel, $dateArr["START"], $dateArr["END"], "day");

if($NotAuthorized) {
    $USER->Logout();
} else {
    $USER->Authorize($user_id);
}

?>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">

<style>
    .statistic {
        background: linear-gradient(180deg, #005ba6, #299fbd);
    }

    .statisticContainer {
        display: flex;
        align-items: stretch;
    }

    .statisticTable{
        width:96%;
        background:#0d2b43;
        color:white;
        margin:2%;
    }

    .seperator {
        width: 100%;
        height: 80px;
    }

    .title{
        display:flex;
        align-items: center;
        justify-content: center;
        color:white;
    }

    .stageStat {
        display: flex;
        flex-direction: column;
        width: 30%;
        margin: 3%;
        background: #0d2b43;
        color: white;
        padding: 2rem;
    }


    .totalNumber {
        font-size: 42px;
        color: #7470ff;
        font-weight: 550;

    }

    .progress-bar-my-style {
        background-color: yellow;
    }

    .progress {
        height: 4px;
    }

    th{
        text-align:center;
    }

    td{
        text-align:center;
        padding-top:5px;
    }

    .leads-list-wrapper {
    max-height: 150px;
    overflow: hidden;
    transition: max-height 0.5s ease, padding 0.3s ease;
    padding-bottom: 0;
    }

    .leads-list-wrapper.expanded {
        max-height: none;
        padding-bottom: 10px;
    }

    .see-more-btn {
        margin-top: 10px;
        background: linear-gradient(135deg, #7470ff 0%, #2a276e 100%);

        color: white;
        border: none;
        padding: 8px 14px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: background 0.3s ease, transform 0.2s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .see-more-btn:hover {
        background: linear-gradient(135deg, #8c89ff 0%, #3b378e 100%);
        transform: scale(1.03);
    }

    .see-more-btn:active {
        transform: scale(0.98);
    }




</style>



<div class="statistic">
<form method="get" id="newCalendarForm">
    <div style="float: left">
        <div style="float: left;">
            <label style="color: white;">დაწყების თარიღი</label><br />
            <input name="startDate" id="startDate" autocomplete="off" type="text"
                onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>
        <div style="float: left; margin-left: 10px;">
            <label style="color: white;">დასრულების თარიღი</label><br />
            <input type="text" id="endDate" name="endDate"
                onclick="BX.calendar({node: this, field: this, bTime: false, bSetFocus: false})">
        </div>
      
        <div style="float: left; margin-left: 10px;">
            <label style="color: white;">პასუხისმგებელი</label><br />
            <select name="userId" id="userId" style="padding: 5px; border-radius: 4px;">
                <option value="all" <?php echo ($selectedUserId == 'all') ? 'selected' : ''; ?>>ყველა</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['ID']; ?>" <?php echo ($selectedUserId == $user['ID']) ? 'selected' : ''; ?>>
                        <?php echo $user['NAME'] . ' ' . $user['LAST_NAME']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="float: left; margin-left: 10px;">
            <input type="submit" style="margin-top: 25px;">
        </div>
    </div>
</form>
<div>
<button onclick="createWorkbook()" style="margin-top: 25px; display:none;">Export to Excel</button>

</div>

    <div class="seperator"></div>

    <div class="title"><h1 style="text-align: center;">Lead Statistics </h1></div>



    <div class="statisticContainer">
        <div class="stageStat" id="sumLeads">
            <p>ლიდების ჯამური რაოდენობა</p>
            <div class="totalNumber" id="sumLeadsTOTAL">0</div>
        </div>
    
        <div class="stageStat" id="sources2">
            <p>SOURCE</p>
            <div class="totalNumber" id="sourcesTOTAL">0</div>
            <div class="leads-list-wrapper" id="sourcesListWrapper">
        <!-- Dynamic labels and progress bars will go here -->
    </div>
    <button class="see-more-btn" id="seeMoreSourcesBtn" style="display: none;">See more sources...</button>
        </div>
        <div class="stageStat" id="incomingLeads">
            <p>New lead</p>
            <div class="totalNumber" id="incomingLeadsTOTAL">0</div>
        </div>
    </div>
    <div class="statisticContainer">
        <div class="stageStat" id="pirveladiKomunikacia">
            <p>სეილების ლიდები</p>
            <div class="totalNumber" id="pirveladiKomunikaciaTOTAL">0</div>
        </div>

        <div class="stageStat" id="wonDeals">
            <p>WON LEADS</p>
            <div class="totalNumber" id="wonDealsTOTAL">0</div>
        </div>

        <div class="stageStat" id="lostDeals">
            <p>LOST LEADS</p>
            <div class="totalNumber" id="lostDealsTOTAL">0</div>

        </div>

  

    </div>
 

   

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

<script>


    let startFilter = <?php echo json_encode($dateArr['START']); ?>;
    let endFilter = <?php echo json_encode($dateArr['END']); ?>;
    document.getElementById("startDate").value = startFilter;
    document.getElementById("endDate").value = endFilter;

    // let registeredDeals = <?php echo json_encode($registeredDeals); ?>;
    
    let sumLeads = <?php echo json_encode($sumLeads); ?>;
    //let callCenterRedirected = <?php echo json_encode($callCenterRedirected); ?>;    
    let sources2 = <?php echo json_encode($sources2); ?>;
    let incomingLeads = <?php echo json_encode($incomingLeads); ?>;
    let pirveladiKomunikacia = <?php echo json_encode($pirveladiKomunikacia); ?>;




    let wonDeals = <?php echo json_encode($wonDeals); ?>;


    let lostDeals = <?php echo json_encode($lostDeals); ?>;

    let dailyReport = <?php echo json_encode($dailyReport); ?>;


    
    renderLeadsCard(sumLeads, "sumLeads");    
    renderLeadsCardSource(sources2, "sources2");
    renderLeadsCard(incomingLeads, "incomingLeads");
    renderLeadsCard(pirveladiKomunikacia, "pirveladiKomunikacia");


    renderLeadsCard(wonDeals, "wonDeals");

    

    renderLeadsCard(lostDeals, "lostDeals");

    function renderLeadsCard(leadsArray, chartId) {
        if (leadsArray?.length) {
            const chartElement = document.getElementById(chartId);
            const totalElement = chartElement.querySelector('.totalNumber');
            const sum = getLeadsSum(leadsArray);
            totalElement.textContent = sum;

            leadsArray.sort((a, b) => b.value - a.value).map(entry => {
                //Append name label
                chartElement.append(createLabel(entry));
                //Append progress bar
                chartElement.appendChild(createProgressBar(entry.value, sum));
            })
        }
    }

    function renderLeadsCardSource(leadsArray, chartId) {
    if (leadsArray?.length) {
        const chartElement = document.getElementById(chartId);
        const totalElement = chartElement.querySelector('.totalNumber');
        const listWrapper = chartElement.querySelector('.leads-list-wrapper');
        const seeMoreBtn = chartElement.querySelector('.see-more-btn');

        // Clear previous content
        listWrapper.innerHTML = '';

        const sum = getLeadsSum(leadsArray);
        totalElement.textContent = sum;

        leadsArray.forEach(entry => {
            listWrapper.append(createLabel(entry));
            listWrapper.appendChild(createProgressBar(entry.value, sum));
        });

        // Check if content exceeds limit to show the button
        setTimeout(() => {
            if (listWrapper.scrollHeight > listWrapper.clientHeight) {
                seeMoreBtn.style.display = 'inline-block';
            } else {
                seeMoreBtn.style.display = 'none';
            }
        }, 100); // Wait for DOM to render
    }
}

document.getElementById('seeMoreSourcesBtn').addEventListener('click', () => {
    const wrapper = document.getElementById('sourcesListWrapper');
    const btn = document.getElementById('seeMoreSourcesBtn');

    const isExpanded = wrapper.classList.toggle('expanded');
    btn.textContent = isExpanded ? 'See less sources...' : 'See more sources...';

    // Scroll to top if "See less" is clicked
    if (!isExpanded) {
        window.scrollTo({
            top: 0,
            behavior: 'smooth', // Smooth scroll
        });
    }
});

    function getLeadsSum(leadsArray) {
        return leadsArray.reduce((prev, cur) => prev + cur.value, 0);
    }

    function createLabel(entry) {
        const labelElement = document.createElement('div');
        return labelElement.textContent = `${entry.name}  ${entry.value} leads`;
    }

    function createProgressBar(value, sum) {
        const percentage = value * 100 / sum;
        var progressContainer = document.createElement('div');
        progressContainer.classList.add('progress');
        var progressBar = document.createElement('div');
        progressBar.classList.add('progress-bar', 'progress-bar-my-style');
        progressBar.setAttribute('role', 'progressbar');
        progressBar.style.width = percentage + '%';
        var srOnly = document.createElement('span');
        srOnly.classList.add('sr-only');
        srOnly.textContent = percentage + '% Complete';
        progressBar.appendChild(srOnly);
        progressContainer.appendChild(progressBar);
        return progressContainer;
    }


    function createWorkbook() {
        console.log(dailyReport);
    const workbook = XLSX.utils.book_new(); // Create a new workbook

    function addSheet(dataArray, sheetName, isRangeFormat = false) {
        if (dataArray.length === 0) return; // Skip if empty
        const headers = Object.keys(dataArray[0]);
        const sheetData = [headers]; // Start with headers

        dataArray.forEach(el => {
            sheetData.push(headers.map(key => el[key] || "")); // Ensure missing fields are handled
        });

        const sheet = XLSX.utils.aoa_to_sheet(sheetData);

        const range = XLSX.utils.decode_range(sheet['!ref']);
        
        for (let rowNum = range.s.r + 1; rowNum <= range.e.r; rowNum++) {
            const cellAddress = XLSX.utils.encode_cell({r: rowNum, c: 0}); // Column A (index 0)

            if (!sheet[cellAddress]) continue;
            
            if (!isRangeFormat) {
                sheet[cellAddress].t = 'd'; // Set type to date
                
                // Try to convert string date to JS Date if it's in DD.MM.YYYY format
                const dateStr = sheet[cellAddress].v;
                if (typeof dateStr === 'string' && dateStr.match(/^\d{2}\.\d{2}\.\d{4}$/)) {
                    const [day, month, year] = dateStr.split('.');
                    sheet[cellAddress].v = new Date(year, parseInt(month, 10) - 1, day);
                }
            } else {
                sheet[cellAddress].t = 's'; // Set type to string
                
                // Apply a custom number format that looks good for date ranges
                if (!sheet[cellAddress].z) {
                    sheet[cellAddress].z = '@'; // @ is the text format in Excel
                }
            }
        }
        
        const dateColWidth = { wch: isRangeFormat ? 20 : 12 }; // wider for date ranges
        if (!sheet['!cols']) sheet['!cols'] = [];
        sheet['!cols'][0] = dateColWidth;
        
        XLSX.utils.book_append_sheet(workbook, sheet, sheetName);
    }

    addSheet(dailyReport, "ლიდების ყოველდღიური რეპორტი");
    XLSX.writeFile(workbook, "ლიდების ყოველდღიური რეპორტი.xlsx");
}

</script>

<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>