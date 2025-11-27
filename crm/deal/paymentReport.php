<?
ob_start();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
CJSCore::Init(array("jquery"));

GLOBAL $USER;
$APPLICATION->SetTitle("დავალიანებები");
$groupArray = $USER->GetUserGroupArray();
//if(!(in_array(17,$groupArray) ) )die("თქვენ არ გაქვთ წვდომა!");

//=================================FUNCTION=========================================//

function echonewLINE($a){
    echo "<br>".$a."</br>";
}


function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function format_to_float($x){
    return number_format($x,2,",","");
}

function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();

    return $res["NAME"]." ".$res["LAST_NAME"];
}
function getcontactdeals($contactID)
{
    $arSelect = array("ID");
    $dealsForFilter = CCrmDeal::GetList(array("ID" => "ASC"), array("CONTACT_ID"=>$contactID), $arSelect);
    $deal_ID = array();
    while ($arClnt = $dealsForFilter->Fetch()) {
        array_push($deal_ID,$arClnt["ID"]);
    }
    return $deal_ID;
}

function getProductProject($arFilter = array(),$sort=array()) {
    $arElements = array();
    $arSelect=array("ID","IBLOCK_SECTION_ID");  //// CATEGORY_ID -ია დასამატებელი
    $res = CIBlockElement::GetList($sort, $arFilter, false, Array("nPageSize"=>1), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function getProjectdeals($projectName)
{
    $arSelect = array("ID");
    $dealsForFilter = CCrmDeal::GetList(array("ID" => "ASC"), array("UF_CRM_PB_HOUSE"=>$projectName), $arSelect);
    $deal_ID = array();
    while ($arClnt = $dealsForFilter->Fetch()) {
        array_push($deal_ID,$arClnt["ID"]);
    }
    return $deal_ID;
}

function getArDeal($dealid) {
    $res = CCrmDeal::GetList(array(), array('ID'=>$dealid, "!STAGE_ID" => "LOSE"), array('ID','TITLE','CONTACT_ID','CONTACT_FULL_NAME','UF_CRM_PB_HOUSE'));
    if($arDeal = $res->Fetch()) {
        // if(!$arDeal['UF_CRM_1543577942174']) $arDeal['UF_CRM_1543577942174'] = '---';
        return $arDeal;
    } else return false;
}

function get_elements_filter($IBLOCK_ARR,$client,$contract,$wonDeals){


    $arFilter=array(
        "IBLOCK_ID" => $IBLOCK_ARR,
        "PROPERTY_DEAL" => $wonDeals,
    );

    if($client) {
        $contacts_dealID_arr = getcontactdeals($client);
        if (!empty($contacts_dealID_arr)) {
//            printArr( $contacts_dealID_arr);
            if ($contract) {

                if (in_array($contract, $contacts_dealID_arr)) {
                    $arFilter["PROPERTY_DEAL"] = $contract;
                } else {
                    $arFilter = 0;
                }
            } else {
                $arFilter["PROPERTY_DEAL"] = $contacts_dealID_arr;
            }
        }
        else{
            $arFilter = 0;
        }
    }
    elseif($contract){
        $arFilter["PROPERTY_DEAL"]=$contract;
    }
    return $arFilter;
}

function get_contacts_for_filter($wonDealsContactIDs){
    //------------კონტაქტები----------------//
    $arSelect=array("ID","FULL_NAME");
    $arr_contacts = CCrmContact::GetList(array("FULL_NAME" => "ASC"), array("ID"=> $wonDealsContactIDs), $arSelect);
    $contacts[0]="აირჩიეთ კონტაქტი";
    while($arClnt  = $arr_contacts->Fetch()) {
        $contacts[$arClnt["ID"]]=$arClnt["FULL_NAME"];
    }
    return $contacts;
}

function get_deals_for_filter(){
    $arSelect=array("TITLE","ID","CONTACT_ID");
    $arrFilter["STAGE_ID"] = "WON";
    $arrFilter["UF_CRM_1702019032102"] = "322";

    if($_GET["project"]){
        $arrFilter["UF_CRM_1761658516561"] = $_GET["project"];
    }else{
        $arrFilter["UF_CRM_1761658516561"] = "ვარკეთილი 2";
    }
    $arr_deals = CCrmDeal::GetList(array("ID" => "ASC"), $arrFilter, $arSelect);
    $dealsForFilter[0]="აირჩიეთ კონტაქტი";
    $wonDealsIDs = array();
    $CONTACT_ID = array();
    while($arClnt  = $arr_deals->Fetch()) {
        $dealsForFilter[$arClnt["ID"]]=$arClnt["TITLE"];
        array_push($wonDealsIDs,$arClnt["ID"]);
        array_push($CONTACT_ID,$arClnt["CONTACT_ID"]);
    }
    $result["dealsForFilter"] = $dealsForFilter;
    $result["wonDealsIDs"] = $wonDealsIDs;
    $result["CONTACT_ID"] = $CONTACT_ID;
    return $result;
}

function getCIBlockElementsByFilter($arFilter = array(),$sort=array()) {
    $arElements = array();
    $arSelect=array("ID","IBLOCK_ID","NAME","DATE_ACTIVE_FROM","PROPERTY_*");
    $res = CIBlockElement::GetList(array("PROPERTY_TARIGI"=>"ASC"), $arFilter, false, Array("nPageSize"=>9999999), $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}


function getDealInfo ($dealID) {
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array("TITLE","ASSIGNED_BY_ID","STAGE_ID","UF_CRM_1693398443196","OPPORTUNITY","CONTACT_ID","CONTACT_FULL_NAME","COMPANY_ID","COMPANY_TITLE","UF_CRM_PB_HOUSE","UF_CRM_1660738949084","UF_CRM_1761658516561","UF_CRM_1712920188845"));

    $resArr = array();
    if($arDeal = $res->Fetch()){
        return $arDeal;
//        echo "<pre>"; print_r($arContact); echo "</pre>";
    }
//    echo "<pre>"; print_r($resArray); echo "</pre>";
}

function get_client_debt($arr_dealIDs)
{
    $CLIENTS_DEBT = array();
    $nextPaymentDate = 0;
    $count = 0;
    $gadaxdebiCount = 0;
    foreach ($arr_dealIDs as $dealID) {
   
        $array = array();
        $array["ID"] = $dealID;
        $array["daricxvebi"] = 0;
        $array["planedTillToDay"]=0;
        $array["vadagadacileba"]='';
        $array["vadagadilebisTarigi"]='';
        //----------------------------------deal info--------------------------//
        $deal_info = getDealInfo($dealID);
        if($deal_info){
            $userName=getUserName($deal_info["ASSIGNED_BY_ID"]);
            $deal_info["responsName"] = $userName;
        }
        $array["deal_info"] = $deal_info;
        if ($deal_info) {

            $arr_filter_daricxvebi = array(
                "IBLOCK_ID" => 20,
                "PROPERTY_DEAL" => $dealID,
            );

            $sort_filter = array("TARIGI" => "ASC");
            $arr_daricxvebi = getCIBlockElementsByFilter($arr_filter_daricxvebi, $sort_filter);
            $nextPaymentDate = 0;
            foreach ($arr_daricxvebi as $arr_daricxva) {
                if (strtotime(str_replace("/",".",$arr_daricxva["TARIGI"]))<strtotime(date("d.m.Y"))){
                    if($arr_daricxva["amount_GEL"]){
                        $array["planedTillToDay"] +=   $arr_daricxva["amount_GEL"];
                    }
                }
                else if($nextPaymentDate==0){
                    $nextPaymentDate = $arr_daricxva["TARIGI"];
                }
                
                $tanxa = is_numeric($arr_daricxva["amount_GEL"])?$arr_daricxva["amount_GEL"]:0;
                $array["daricxvebi"] += $tanxa;
            }

            //----------------------------------გადახდები------------------------------------//

            $arr_filter_gadaxdebi = array(
                "IBLOCK_ID" => 21,
                "PROPERTY_DEAL" => $dealID,
            );

            $arr_gadaxdebi = getCIBlockElementsByFilter($arr_filter_gadaxdebi);

            $array["GADAXDILI"] = 0;

            foreach ($arr_gadaxdebi as $arr_gadaxda) {
                if($arr_gadaxda["refund"] == "YES"){
                    $array["GADAXDILI"] -= $arr_gadaxda["tanxa_gel"];

                }else{
                    $array["GADAXDILI"] += $arr_gadaxda["tanxa_gel"];
                }
                $gadaxdebiCount ++;
            }

            $overdue = round($array["planedTillToDay"]-$array["GADAXDILI"],2);
            $array["overdue"]= $overdue;

            $array["nextPaymentDate"] = $nextPaymentDate;

            // printArr($array["overdue"]); 

            if($array["overdue"]>0){

                $hadToPay=0;
                $alreadyPayed=$array["GADAXDILI"];

               

                foreach ($arr_daricxvebi as $arr_daricxva) {
                   
                        $hadToPay +=   $arr_daricxva["amount_GEL"];

                        // // printArr($hadToPay);
                        // echo '</br>h: '.$hadToPay.' A:'.$alreadyPayed;


                            $date1=date_create_from_format("d/m/Y",$arr_daricxva["TARIGI"]);
                            $date2=date_create_from_format("d/m/Y",date("d/m/Y"));
                            $diff=date_diff($date1,$date2);
                  
                            $vadagadacileba = $diff->format("%R%a");

           


                        if($hadToPay > $alreadyPayed && $vadagadacileba>0 && !$array["vadagadilebisTarigi"]){
           
                            $array["vadagadacileba"]=str_replace("+","",$vadagadacileba);
                            $array["vadagadilebisTarigi"]= $arr_daricxva["TARIGI"];
                        }

                }


            }

            array_push($CLIENTS_DEBT, $array);

            // if(($array["daricxvebi"]-$array["GADAXDILI"])!=0 || $array["daricxvebi"]!=$array["deal_info"]["OPPORTUNITY"]) {
            //     array_push($CLIENTS_DEBT, $array);
            //     $count ++ ;
            //     echo $count."</br>";
            // }else{
            //    echo $dealID."</br>";
            // }
        }
    }

printArr($gadaxdebiCount);
    return $CLIENTS_DEBT;
}


function get_month_ge($date){
    switch ($date){
        case 1 :return "იან.";
        case 2 :return "თებ.";
        case 3 :return "მარ.";
        case 4 :return "აპრ.";
        case 5 :return "მაის.";
        case 6 :return "ივნ.";
        case 7 :return "ივლ.";
        case 8 :return "აგვ.";
        case 9 :return "სექ.";
        case 10 :return "ოქტ.";
        case 11 :return "ნოემ.";
        case 12 :return "დეკ.";
    }
}


function get_arr_dates($last_date){
    $array["for_header"] = array("კლიენტი", "ხელშეკრულება", "გაფ. თარიღი","შემდეგი გადახდა", "კონტრ. ღირებულება","ფასდაკლებამდე ფასი","დარიცხული", "გადახდილი", "დარჩენილი");
    $array["for_debt"] = array();
    $month = intval(date("m"));

    $year_YYYY = date("Y");

    do {
        $month_ge = get_month_ge($month);
        $date_header = $month_ge . " " . $year_YYYY;
        $month >= 10 ? $date_debt = $year_YYYY . $month . "01" : $date_debt = $year_YYYY ."0". $month . "01";


        array_push($array["for_header"], $date_header);
        array_push($array["for_debt"], $date_debt);
        $month++;
        if ($month > 12) {
            $month = 1;
            $year_YYYY++;
        }
    } while ($date_debt <= $last_date);
//    array_push($array,);
    return $array;

}

function get_sum($array){
    $arr_sum["daricxvebi"]=0;
    $arr_sum["planedTillToDay"]=0;
    $arr_sum["OPPORTUNITY"]=0;
    $arr_sum["GADAXDILI"]=0;
    for($i=0;$i<count($array);$i++) {
        if($array[$i]["daricxvebi"]){
            $arr_sum["daricxvebi"] += $array[$i]["daricxvebi"];
        }
        if($array[$i]["daricxvebi"]){
            $arr_sum["OPPORTUNITY"] += $array[$i]["daricxvebi"];
        }
        if($array[$i]["GADAXDILI"]){
            $arr_sum["GADAXDILI"] += $array[$i]["GADAXDILI"];
        }
        if($array[$i]["planedTillToDay"]){
            $arr_sum["planedTillToDay"] += $array[$i]["planedTillToDay"];
        }        
        if($array[$i]["overdue"]){
            $arr_sum["overdue"] += $array[$i]["overdue"];
        }
    }
//    printArr($arr_sum["daricxvebi"]);
    return $arr_sum;
}

//======================================code=======================================//

$DARICXVA_IBLOCK_ID = 20;
$PAYMENT_IBLOCK_ID  = 21;
$IBLOCK_ARR         = array($PAYMENT_IBLOCK_ID,$DARICXVA_IBLOCK_ID);
$client   = $_GET["CLIENT"];
$contract = $_GET["contract"];
$filter["CLIENT"]=$client;
$filter["contract"]=$contract;

$wonDeals       =   get_deals_for_filter();
$wonDealsIDs       =  $wonDeals["wonDealsIDs"];
// printArr($wonDealsIDs);
if(count($wonDealsIDs)>0){
    $wonDealsIDs       =  $wonDeals["wonDealsIDs"];
    $DEALS_FOR_FILTER       =  $wonDeals["dealsForFilter"];
    $wonDealsContactIDs       =  $wonDeals["CONTACT_ID"];
    $CONTACTS_FOR_FILTER    =   get_contacts_for_filter($wonDealsContactIDs);
    $filter_for_element        =   get_elements_filter($IBLOCK_ARR,$client,$contract,$wonDealsIDs);
}





$clients_debt=array();
$ARR_HEADER=array("კლიენტი","პროექტი", "დილის ID","კომენტარი","ვადაგადაცილების. თარიღი","შემდეგი გადახდა", "კონტრ. ღირებულება","დარიცხული","მიმდ. დავალიანება", "გადახდილი", "დარჩენილი","ვადაგადაცილება (დღე)");
if($filter_for_element!=0) {
    $daricxva_gadaxda_elements = getCIBlockElementsByFilter($filter_for_element);


    $arr_deals_with_daricxva_gadaxda = array();
    $last_date = date("YMD");
    foreach ($daricxva_gadaxda_elements as $element) {
        if ($element["TARIGI_NUMBR"] > $last_date) {
            $last_date = $element["TARIGI_NUMBR"];
        }
        if (!in_array($element["DEAL"], $arr_deals_with_daricxva_gadaxda) && $element["DEAL"]) {
            $dealInfo = getDealInfo($element["DEAL"]);

            if($dealInfo["STAGE_ID"]=="WON") {
                $prods = CCrmDeal::LoadProductRows($element["DEAL"]);
                $prodID=$prods[0]["PRODUCT_ID"];
                $arFilter = array(
                    "ID" => $prodID,
                );
                $product = getProductProject($arFilter);
                $productProject=$product[0]['IBLOCK_SECTION_ID'];
            
                array_push($arr_deals_with_daricxva_gadaxda, $element["DEAL"]);

                            
            }
        }
    }

    $clients_debt = get_client_debt($arr_deals_with_daricxva_gadaxda);
    $JAMEBI=get_sum($clients_debt);

    // printArr($arr_deals_with_daricxva_gadaxda);
    // printArr($clients_debt);
    $JAMEBI["OPPORTUNITY"]  =round($JAMEBI["OPPORTUNITY"],2);
    $JAMEBI["GADAXDILI"]    =round($JAMEBI["GADAXDILI"],2);
    $JAMEBI["planedTillToDay"]    =round($JAMEBI["planedTillToDay"],2);
    $JAMEBI["darchenili"]   =round($JAMEBI["OPPORTUNITY"]-$JAMEBI["GADAXDILI"],2);



}

ob_end_clean();

?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>  <!-- delete column-->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>

<style>
    .head2{
        position: sticky;
        top: 0;
        z-index: 1000; /* Ensure it's above other content */
    }
    .tabler {
        border: 1px solid #242424;
        border-collapse: collapse;
        width: 100%;
    }
    .tabler tbody tr td, .tabler thead tr th {
        border: 1px solid #929191;
        padding: 5px;
        max-height: 50px;
        vertical-align: top;
    }
    .tabler thead tr th {
        border: 1px solid #fdfcfc;

        padding: 10px;
        background: #585858;
        color: #ffffff;
    }
    .tabler tbody tr:hover {
        background: #ddd;
    }

    .dropdown{
        max-width: 200px;
        height: 28px ;
        border-radius: 5px;
    }

    .submitBTN{
        width: 80px;
        height: 30px;
        border-radius: 5px;
        margin-left: 10px;
        font-size: 16px;


    }

    #menu-items-block{
        display:none;
    }

    #header{
        display:none;

    }

    .page__toolbar{
        display: none;
    }

    .app__page {
        padding-left: 0;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .page__workarea-content {
        padding: 15px;
        flex: 1;
        background-color: var(--ui-color-background-primary);
        border-radius: 0;
        overflow: hidden;
        position: relative;
        height: 800px;
        width: 100%;

    }

    .app__footer{

    display:none;


    }

    .excel-export-button {
        background-color: #4CAF50; /* Green background color */
        border: none;
        color: white;
        padding: 10px 20px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        font-size: 16px;
        cursor: pointer;
        border-radius: 4px;
    }

    /* Hover effect */
    .excel-export-button:hover {
        background-color: #45a049; /* Darker green background color */
    }

    /* Active (clicked) state */
    .excel-export-button:active {
        background-color: #3e8e41; /* Even darker green background color */
    }

    .header{
        display: flex;
        justify-content: center;
        font-size: 15px;
        font-weight: bold;
    }
</style>

<div style="width: 100%;height: 70px">
    <form>
        <table>
            <tr>
                <td><label>პროექტი: </label></td>
                <td><label>კლიენტი: </label></td>
                <td><label>ხელშეკრულება: </label></td>
                <td><label>ვადაგადაცილება: </label></td>
            </tr>
            <tr>
            <td>
                    <select name="project" class="dropdown" id="project" >
                        <option value="Park Boulevard">Park Boulevard</option>
               


                    </select>
                </td>
                <td>
                    <select name="CLIENT" id="CLIENT" class="CLIENT dropdown">
                    </select>
                </td>

                <td>
                    <select name="contract" id="contract" CLASS="CONTRACT dropdown">
                    </select>
                </td>
                <td>
                    <select name="Overdue" id="Overdue" CLASS="Overdue dropdown" onchange="selectOverdues()">
                        <option value="all" selected>ყველა</option>
                        <option value="Overdue">ვადაგადაცილებული</option>
                        <option value="OnTime">გეგმიური</option>
                    </select>
                </td>
                <td>
                    <input type="submit" CLASS="submitBTN" value="ძიება">
                </td>
            </tr>
        </table>
    </form>
</div>
<!-- <div class="header"> დავალიანების რეპორტი ლარებში </div> -->
<div id="count"></div>
<div style="float: right" >
    <button onclick="exportProd1();" id="exportBtn" class="excel-export-button" hidden><i>Export</i></button>
</div>

<div style="max-height: 400px; overflow-y: auto; scroll-behavior: smooth;">
    <table class="tabler" id="table">
        <thead>
            <tr id="head1" style="border-bottom: 7px solid white"></tr>
            <tr id="head2" class="head2"></tr>
        </thead>
        <tbody id="body1"></tbody>
    </table>
</div>


<script>

    fillClient();
    fillContract();
    fillHead1();
    fillHead2();
    fillBody();
    // delete_empty_coll();

    $arParams=<?echo json_encode($filter,JSON_UNESCAPED_UNICODE);?>;

    $("select[name='CLIENT']").val($arParams["CLIENT"]);
    $("select[name='contract']").val($arParams["contract"]);

    $('.daricxvebi_tr td').click(function(){
        var td = this.cellIndex
        console.log(td)
    })


    $(document).ready(function() {
        $('.CLIENT').select2();
        $('.CONTRACT').select2();
    });



    project = <?echo json_encode($_GET["project"],JSON_UNESCAPED_UNICODE);?>;
    client = <?echo json_encode($_GET["CLIENT"],JSON_UNESCAPED_UNICODE);?>;
    contract = <?echo json_encode($_GET["contract"],JSON_UNESCAPED_UNICODE);?>;
    Overdue = <?echo json_encode($_GET["Overdue"],JSON_UNESCAPED_UNICODE);?>;

    document.getElementById("project").value = project;
    document.getElementById("CLIENT").value = client;
    document.getElementById("contract").value = contract;
    document.getElementById("Overdue").value = Overdue;


    //=====================================function=============================================



    // function numberWithCommas(x) {
    //     return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    // }

    function numberWithCommas(x)  {
        x = parseFloat(x).toFixed(2);
        if(x.toString() != 'NaN') return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        else return '--- ';
    }
    function numberformat ( tanxa ) {
        tanxa=tanxa.replace("$", "");
        tanxa="$"+tanxa;
        return tanxa;
    }

    function numberUSDformat(x){

    }

    function sortTable(e) {
        // console.log(e.rows);
        var a=document.getElementById("table");
        for(var i=0;i<a.rows[0].cells.length;i++){
            if(a.rows[1].cells[i].innerText==e.innerText) {
                // console.log(i);
                sortTable1(i);
                break;
            }
        }
    }


    function selectOverdues(){

        let status=document.getElementById("Overdue").value;

        if(status=='Overdue'){
            fillBodyOverdue();
        }
        else if(status=='OnTime'){
            fillBodyOnTime();
           
        }else if(status=='all'){
            fillBody();
        }

        // console.log(status);
        
    }


    function sortTable1(n) {
        var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
        table = document.getElementById("table");
        switching = true;
        dir = "asc";

        while (switching) {
            switching = false;
            rows = table.rows;
            // console.log(1,n);
            for (i = 2; i < (rows.length - 1); i++) {
                shouldSwitch = false;
                x = rows[i].getElementsByTagName("TD")[n];
                y = rows[i + 1].getElementsByTagName("TD")[n];

                if (dir == "asc") {
                    if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                        shouldSwitch = true;
                        break;
                    }
                } else if (dir == "desc") {
                    if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                        shouldSwitch = true;
                        break;
                    }
                }
            }
            if (shouldSwitch) {
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
                switchcount ++;
            } else {
                if (switchcount == 0 && dir == "asc") {
                    dir = "desc";
                    switching = true;
                }
            }
        }
    }


    function delete_empty_coll(){
        var table=document.getElementById("table");

        for( var i=0;i<table.rows[0].cells.length;i++){
            if(table.rows[0].cells[i].innerText=="0.00") {
                $('.tabler tr').find('th:eq(' + i + '),td:eq(' + i + ')' ).remove();
                i--;
            }
        }
    }


    function fillHead1(){
        var head1 = <? echo json_encode($ARR_HEADER,JSON_UNESCAPED_UNICODE);?>;
        var jamebi = <? echo json_encode($JAMEBI,JSON_UNESCAPED_UNICODE);?>;
        var th="<th onclick='sortTable(this)' style='background-color: white'></b></th>";

        for(var i=0;i<head1.length;i++){
            if(i<6) {
                th += "<th style='background-color: white'></th>";
            }
            else if(i==6){
                th += "<th>"+numberWithCommas(jamebi["OPPORTUNITY"])+"</th>";
            }
            else if(i==7){
                th += "<th>"+numberWithCommas(jamebi["planedTillToDay"])+"</th>";
            }
            else if(i==8){
                th += "<th>"+numberWithCommas(jamebi["overdue"])+"</th>";
            }
            else if(i==9){
                th += "<th>"+numberWithCommas(jamebi["GADAXDILI"])+"</th>";
            }
            else if(i==10){
                th += "<th>"+numberWithCommas(jamebi["darchenili"])+"</th>";
            }
            else if(i==11){
                th += "<th></th>";
            }
            else{
                th += "<th>"+numberWithCommas(jamebi["daricxvebi"][i-11])+"</th>";
            }
        }
        document.getElementById("head1").innerHTML=th;
    }

    function fillHead2(){
        var head2 = <? echo json_encode($ARR_HEADER,JSON_UNESCAPED_UNICODE);?>;
        var th="<th onclick='sortTable(this)'></b></th>";

        for(var i=0;i<head2.length;i++){
            if(i!=1 && i!=2) {
                th += "<th onclick='sortTable(this)' style='text-align: center; cursor: pointer; '> <b>" + head2[i] + "</b></th>";
            }
            else{
                th += "<th style='text-align: center; cursor: default;'> <b>" + head2[i] + "</b></th>";
            }
        }
        document.getElementById("head2").innerHTML=th;
    }

    function fillBody(){

        var body1 = <? echo json_encode($clients_debt,JSON_UNESCAPED_UNICODE);?>;
        var tr="";

        // vadagadacileba='<p1 style=color:"red">123<p1>';
        let count = 0;
        for(var i=0;i<body1.length;i++){
            count++;
            if(body1[i]["deal_info"]["UF_CRM_1712920188845"]){
                commentText = body1[i]["deal_info"]["UF_CRM_1712920188845"];
            }else{
                commentText = "";
            }

            var davalianeba = parseFloat(body1[i]["daricxvebi"])-parseFloat(body1[i]["GADAXDILI"]);
            // console.log(body1[i]["deal_info"]["TITLE"], "გადახდილი", body1[i]["GADAXDILI"], "დარიცხული:",body1[i]["daricxva_till_today"] );
            if(body1[i]["overdue"]>0) {
                tr +="<tr class='daricxvebi_tr'><td><img src='/images/icons/redX.png'  width='30' height='30'></td>";
            }
            else{
                tr +="<tr class='daricxvebi_tr'><td ><img src='/images/icons/greenV.jpg'  width='30' height='30'></td>";

            }

            if(body1[i]["deal_info"]["CONTACT_FULL_NAME"]){
                tr +="<td><a href='/crm/contact/details/"+body1[i]["deal_info"]["CONTACT_ID"]+"/'>"+body1[i]["deal_info"]["CONTACT_FULL_NAME"]+"</a></td>";
            }else{
                tr +="<td><a href='/crm/company/details/"+body1[i]["deal_info"]["COMPANY_ID"]+"/'>"+body1[i]["deal_info"]["COMPANY_TITLE"]+"</a></td>";
            }
            tr +="<td>"+body1[i]["deal_info"]["UF_CRM_1761658516561"]+"</td>";
            tr +="<td><a href='/crm/deal/details/"+body1[i]["deal_info"]["ID"]+"/'>"+body1[i]["deal_info"]["TITLE"]+"</a></td>";
            tr +=`<td> <div contenteditable="true" id="${body1[i]["deal_info"]["ID"]}">${commentText} </div> <button onclick="saveDealComment(${body1[i]["deal_info"]["ID"]})">Save</button> </td>`;
            tr +="<td>"+body1[i]["vadagadilebisTarigi"]+"</td>";
            tr +="<td>"+body1[i]["nextPaymentDate"]+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["deal_info"]["OPPORTUNITY"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["planedTillToDay"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["overdue"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["GADAXDILI"].toFixed(2))+"</td>";
            tr += "<td>" + numberWithCommas(davalianeba.toFixed(2)) + "</td>";
            tr += "<td>" + body1[i]["vadagadacileba"] + "</td>";

            tr +="</tr>";

            // "<td> "+body1[i]["deal_info"]["CONTACT_FULL_NAME"]+"</td>"+
            // "<td> "+body1[i]["deal_info"]["TITTLE"]+"</td>"+
            // "</tr>";
        }
        document.getElementById("count").innerHTML=`<span>რაოდენობა: ${count}</span>`;
        document.getElementById("body1").innerHTML=tr;
    }


    //////////////////////////////////////////////


    function fillBodyOnTime(){

        var body1 = <? echo json_encode($clients_debt,JSON_UNESCAPED_UNICODE);?>;
        var tr="";
        let count = 0;
        
        for(var i=0;i<body1.length;i++){
            if(body1[i]["overdue"]<=0){
                count++;
                if(body1[i]["deal_info"]["UF_CRM_1712920188845"]){
                    commentText = body1[i]["deal_info"]["UF_CRM_1712920188845"];
                }else{
                    commentText = "";
                }
                var davalianeba = parseFloat(body1[i]["daricxvebi"])-parseFloat(body1[i]["GADAXDILI"]);
                // console.log(body1[i]["deal_info"]["TITLE"], "გადახდილი", body1[i]["GADAXDILI"], "დარიცხული:",body1[i]["daricxva_till_today"] );
                if(body1[i]["overdue"]>0) {
                    tr +="<tr class='daricxvebi_tr'><td><img src='/images/icons/redX.png'  width='30' height='30'></td>";
                }
                else{
                    tr +="<tr class='daricxvebi_tr'><td ><img src='/images/icons/greenV.jpg'  width='30' height='30'></td>";

                }

                if(body1[i]["deal_info"]["CONTACT_FULL_NAME"]){
                tr +="<td><a href='/crm/contact/details/"+body1[i]["deal_info"]["CONTACT_ID"]+"/'>"+body1[i]["deal_info"]["CONTACT_FULL_NAME"]+"</a></td>";
            }else{
                tr +="<td><a href='/crm/company/details/"+body1[i]["deal_info"]["COMPANY_ID"]+"/'>"+body1[i]["deal_info"]["COMPANY_TITLE"]+"</a></td>";
            }
            tr +="<td>"+body1[i]["deal_info"]["UF_CRM_1761658516561"]+"</td>";
            tr +="<td><a href='/crm/deal/details/"+body1[i]["deal_info"]["ID"]+"/'>"+body1[i]["deal_info"]["TITLE"]+"</a></td>";
            tr +=`<td> <div contenteditable="true" id="${body1[i]["deal_info"]["ID"]}">${commentText} </div> <button onclick="saveDealComment(${body1[i]["deal_info"]["ID"]})">Save</button> </td>`;
            console.log(commentText)
            tr +="<td>"+body1[i]["vadagadilebisTarigi"]+"</td>";
            tr +="<td>"+body1[i]["nextPaymentDate"]+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["deal_info"]["OPPORTUNITY"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["planedTillToDay"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["overdue"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["GADAXDILI"].toFixed(2))+"</td>";
            tr += "<td>" + numberWithCommas(davalianeba.toFixed(2)) + "</td>";
            tr += "<td>" + body1[i]["vadagadacileba"] + "</td>";

                tr +="</tr>";

                // "<td> "+body1[i]["deal_info"]["CONTACT_FULL_NAME"]+"</td>"+
                // "<td> "+body1[i]["deal_info"]["TITTLE"]+"</td>"+
                // "</tr>";
            }
            document.getElementById("count").innerHTML=`<span>რაოდენობა: ${count}</span>`;
            document.getElementById("body1").innerHTML=tr;
        }
    }


    function fillBodyOverdue(){

        var body1 = <? echo json_encode($clients_debt,JSON_UNESCAPED_UNICODE);?>;
        var tr="";

        // vadagadacileba='<p1 style=color:"red">123<p1>';
        let count = 0;
        for(var i=0;i<body1.length;i++){

            if(body1[i]["overdue"]>0){
                count ++;
                if(body1[i]["deal_info"]["UF_CRM_1712920188845"]){
                    commentText = body1[i]["deal_info"]["UF_CRM_1712920188845"];
                }else{
                    commentText = "";
                }
                var davalianeba = parseFloat(body1[i]["daricxvebi"])-parseFloat(body1[i]["GADAXDILI"]);
                // console.log(body1[i]["deal_info"]["TITLE"], "გადახდილი", body1[i]["GADAXDILI"], "დარიცხული:",body1[i]["daricxva_till_today"] );
                if(body1[i]["overdue"]>0) {
                    tr +="<tr class='daricxvebi_tr'><td><img src='/images/icons/redX.png'  width='30' height='30'></td>";
                }
                else{
                    tr +="<tr class='daricxvebi_tr'><td ><img src='/images/icons/greenV.jpg'  width='30' height='30'></td>";

                }
                if(body1[i]["deal_info"]["CONTACT_FULL_NAME"]){
                tr +="<td><a href='/crm/contact/details/"+body1[i]["deal_info"]["CONTACT_ID"]+"/'>"+body1[i]["deal_info"]["CONTACT_FULL_NAME"]+"</a></td>";
            }else{
                tr +="<td><a href='/crm/company/details/"+body1[i]["deal_info"]["COMPANY_ID"]+"/'>"+body1[i]["deal_info"]["COMPANY_TITLE"]+"</a></td>";
            }
            tr +="<td>"+body1[i]["deal_info"]["UF_CRM_1761658516561"]+"</td>";
            tr +="<td><a href='/crm/deal/details/"+body1[i]["deal_info"]["ID"]+"/'>"+body1[i]["deal_info"]["TITLE"]+"</a></td>";
            tr +=`<td> <div contenteditable="true" id="${body1[i]["deal_info"]["ID"]}">${commentText} </div> <button onclick="saveDealComment(${body1[i]["deal_info"]["ID"]})">Save</button> </td>`;
            tr +="<td>"+body1[i]["vadagadilebisTarigi"]+"</td>";
            tr +="<td>"+body1[i]["nextPaymentDate"]+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["deal_info"]["OPPORTUNITY"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["planedTillToDay"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["overdue"])+"</td>";
            tr +="<td>"+numberWithCommas(body1[i]["GADAXDILI"].toFixed(2))+"</td>";
            tr += "<td>" + numberWithCommas(davalianeba.toFixed(2)) + "</td>";
            tr += "<td>" + body1[i]["vadagadacileba"] + "</td>";

                tr +="</tr>";

                // "<td> "+body1[i]["deal_info"]["CONTACT_FULL_NAME"]+"</td>"+
                // "<td> "+body1[i]["deal_info"]["TITTLE"]+"</td>"+
                // "</tr>";
            }
            document.getElementById("count").innerHTML=`<span>რაოდენობა: ${count}</span>`;
            document.getElementById("body1").innerHTML=tr;
        }
    }

    //////////////////////////////////////////////
    
    function fillClient(){
        var client = document.getElementById("CLIENT"),
            itemArray1 = <?php echo json_encode($CONTACTS_FOR_FILTER); ?>;
        for (var key in itemArray1) {
            var value = itemArray1[key];
            var el          = document.createElement("option");
            el.textContent  = value;
            el.value        = key;
            client.appendChild(el);
        }
    }

    function fillContract(){

        var deal            = document.getElementById("contract"),
            itemArray1      = <?php echo json_encode($DEALS_FOR_FILTER); ?>;
        for (var key in itemArray1) {
            var value       = itemArray1[key];
            var el          = document.createElement("option");
            el.textContent  = value;
            el.value        = key;
            deal.appendChild(el);
        }

    }

    function exportProd1()
    {
        var tableSelect = document.getElementById("table");


        let wb = XLSX.utils.book_new();
        wb.Props = {
            Title: "დავალიანების გრაფიკი",
            Subject: "დავალიანების გრაფიკი",
            Author:"თეთრი კვადრატი",
            CreatedDate: new Date()
        };
        let ws_data = [];
        let row = [];
        let ws;


        for (let i = 0; i < tableSelect.rows.length; i++) {
            for (let j = 0; j < tableSelect.rows[i].cells.length; j++) {
                if(i == 0 && j>6 && false){
                    let number = tableSelect.rows[i].cells[j].innerText.replace(",","");
                    row.push(Number(number));
                }
                else if(i >=2 && j==4){
                    row.push(tableSelect.rows[i].cells[j].children[0].innerText);
                }
                else if(i >=2 && j>6){
                    let number = tableSelect.rows[i].cells[j].innerText.replace(",","");
                    row.push(Number(number));
                }
                else{
                    row.push(tableSelect.rows[i].cells[j].innerText);
                }
            }
            ws_data.push(row);
            row=[];
        }

        ws = XLSX.utils.aoa_to_sheet(ws_data);
        XLSX.utils.book_append_sheet(wb, ws, "1");

        var wbout = XLSX.writeFile(wb,"დავალიანების რეპორტი.xlsx");
        console.log(wbout);
    }


    function saveDealComment(id){

        comment = document.getElementById(id).innerText;

        let reqParams = `dealID=${id}&comment=${comment}`;


        fetch(`${location.origin}/rest/local/saveComment.php?${reqParams}`)
                .then(data => {
                    return data.json();
                })
                .then(data => {
                    console.log(data);

                })
                .catch(error => {
                    console.log(error);
                });
    }
    

</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
