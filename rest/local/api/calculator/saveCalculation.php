<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/element.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");




$json = array();
try {
    $json = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}


if (count($json["data"])) {
    if($json["dealId"] && $json["dealId"] != 1) {

        $productsData = getCIBlockElementsByFilter(array("ID"=>$json["PROD_ID"]));
        $arForAdd = array(
            'IBLOCK_ID' => 24,
            'NAME' => "კალკულაცია " . $json["dealId"],
            'ACTIVE' => 'Y',
        );

        if (is_numeric($json["graph"])) {
            $graph = getCIBlockElementsByFilter(array("ID" => $json["graph"]));
            $graphName = $graph[0]["NAME"];
    
        }else{
            $graphName = "არასტანდარტული";
        }


        $arPropsOld = array();
        $arPropsOld["JSON"] = json_encode($json);
        $arPropsOld["TYPE"] = $json["selected_type"];
        $arPropsOld["SELECTID_GRAPH"] = $json["graph"];
        $arPropsOld["HEADER_JSON"] = json_encode($json["calculatorHead"]);
        $arPropsOld["GRAPH_JSON"] = json_encode($json["data"]);
        $arPropsOld["planType"] = $graphName;
        $arPropsOld["AUTHOR"] = $json["author"];
        $arPropsOld["PERIOD"] = $json["period"];
        $arPropsOld["commentInput"] = $json["commentInput"];
        $arPropsOld["DEAL"] = $json["dealId"];
        $arPropsOld["project"] = $productsData[0]["PROJECT"];
        $arPropsOld["prodType"] = $productsData[0]["PRODUCT_TYPE"];
        $arPropsOld["FLOOR"] = $productsData[0]["FLOOR"];
        $arPropsOld["number"] = $productsData[0]["Number"];
        $arPropsOld["loan_amount"] = $json["loan_amount"];
        $arPropsOld["tanamonawileoba"] = $json["tanamonawileoba"];
        $arPropsOld["wliuriProcent"] = $json["wliuriProcent"];
        $arPropsOld["sesxisVada"] = $json["sesxisVada"];
        $arPropsOld["dasafariSul"] = $json["dasafariSul"];
        $arPropsOld["gadasaxadiTveshi"] = $json["gadasaxadiTveshi"];
        $arPropsOld["nbgKursi"] = $json["nbgKursi"];
        $arPropsOld["priceGel"] = round($json["priceGel"],2);
        $arPropsOld["startpriceGel"] = round($json["startpriceGel"],2);
        $arPropsOld["kvmPriceGel"] = round($json["kvmPriceGel"],2);
        $arPropsOld["startSqmPriceGel"] = round($json["startSqmPriceGel"],2);
        $arPropsOld["priceUSD"] = round($json["PRICE"],2);
        $arPropsOld["kvmPriceUSD"] = round($json["kvmPrice"],2);
        $arPropsOld["startPriceUSD"] =  round($json["startPriceUSD"],2);
        $arPropsOld["startKVMPriceUSD"] = round($json["startKVMPriceUSD"],2);
        
        
        

        $res = addCIBlockElement($arForAdd, $arPropsOld);

        if ($res) {
            $arErrorsTmp = array();
            $wfId = CBPDocument::StartWorkflow(
                26,                                                    //ბიზნეს პროცესის ID
                array("bizproc", "CBPVirtualDocument", $res),        //$res არის დოკუმენტის ID
                array_merge(array(), array("TargetUser" => "user_1")),
                $arErrorsTmp
            );
            $result["status"] = 400;
            $result["TEXT"] = "კალკულაცია წარმატებით შეინახა";
        } else {
            $result["status"] = 400;
            $result["TEXT"] = "კალკულაცია ვერ შეინახა";
        }
    }else{
        $result["status"] = 400;
        $result["TEXT"] = "გთხოვთ კალკულაცია დააგენერიროთ დილიდან";
    }
}
else{
    $result["status"] = 400;
    $result["TEXT"] = "კალკულაცია ვერ მოიძებნა";
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

echo json_encode($result,JSON_UNESCAPED_UNICODE);
