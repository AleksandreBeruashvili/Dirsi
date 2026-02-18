<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("test");

function printArr ($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"ASC")) {
    $arDeals = array();
    $res = CCrmDeal::GetList($arSort, $arFilter, array("ID", "OPPORTUNITY"));
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}


function getCIBlockElementsByFilter($arFilter = array()) {
    $arElements = array();
    $arSelect = Array();
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>15000), $arSelect);
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


$arfilter=[
    "STAGE_ID"=> "WON",
    "!OPPORTUNITY"=>"",
    // "ID"=>2874,
];

$deals= getDealsByFilter($arfilter);

// printArr(count($deals));

$count=0;

foreach($deals as $deal){

    $dealId = $deal["ID"];
    $opportunity = $deal["OPPORTUNITY"];

    $arFilter=array(  
        "IBLOCK_ID" => 14,
        "PROPERTY_STATUS" =>"გაყიდული",
        "PROPERTY_OWNER_DEAL" => $dealId,
    );
    
    $prods=getCIBlockElementsByFilter($arFilter);

    if(count($prods) > 0){
        foreach($prods as $Product){
            $ProductID = $Product["ID"];

            $priceUpdate = CPrice::SetBasePrice($ProductID, $opportunity, "USD");
            
            if($priceUpdate){
                $count++;
            }
            // $arLoadProductArray = array(
            //     "PROPERTY_VALUES" => $Product,
            //     "NAME" => $Product["NAME"],
            //     "ACTIVE" => "Y",
            // );


            // if($opportunity){
            //     $arLoadProductArray["PROPERTY_VALUES"]["OWNER_DEAL"] = $dealId;
            // }
            
                
            // $el = new CIBlockElement;
            // $res = $el->Update($ProductID, $arLoadProductArray);


        }

        // printArr($opportunity);
        // printArr($prods);
        // $prodId=$prods[0]["ID"];
        // printArr($prodId);
        // $count++;
    }else{
        // printArr($deal);
    }
}

printArr($count);






