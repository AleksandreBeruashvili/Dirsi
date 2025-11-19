<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/element.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");

function getDealProds ($dealID) {
    $products = [];
    if ($dealID) {
        $prods = CCrmDeal::LoadProductRows($dealID);
        foreach ($prods as $prod) {
            $each = getProductDataByID( $prod["PRODUCT_ID"]);
            $price = CPrice::GetBasePrice($prod["PRODUCT_ID"]);
            array_push($products, $each[0]);
        }
    }
    return $products;
}

$json = array();
try {
    $json = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

if (count($json["data"])) {
    if($json["dealId"] && $json["dealId"] != 1) {
        $productsData = getDealProds($json["dealId"]);
        $arForAdd = array(
            'IBLOCK_ID' => 23,
            'NAME' => "განვადება " . $json["dealId"],
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
        $arPropsOld["planType"] = $graphName;
        $arPropsOld["AUTHOR"] = $json["author"];
        $arPropsOld["commentInput"] = $json["commentInput"];
        $arPropsOld["PERIOD"] = $json["period"];
        $arPropsOld["DEAL"] = $json["dealId"];
        $arPropsOld["project"] = $productsData[0]["PROJECT"];
        $arPropsOld["prodType"] = $productsData[0]["PRODUCT_TYPE"];
        $arPropsOld["FLOOR"] = $productsData[0]["FLOOR"];
        $arPropsOld["number"] = $productsData[0]["Number"];

        $arPropsOld["advancePayment"] = $json["advancePayment"];
        $arPropsOld["lastPayment"] = $json["lastPayment"];
        $arPropsOld["DistributedPayment"] = $json["DistributedPayment"];

        
        

        $res = addCIBlockElement($arForAdd, $arPropsOld);
        if ($res) {
            $arErrorsTmp = array();
            $wfId = CBPDocument::StartWorkflow(
                25,                                                    //ბიზნეს პროცესის ID
                array("bizproc", "CBPVirtualDocument", $res),        //$res არის დოკუმენტის ID
                array_merge(array(), array("TargetUser" => "user_".$json["author"])),
                $arErrorsTmp
            );


            $result["status"] = 400;
            $result["TEXT"] = "გრაფიკი წარმატებით გაიგზავნა";
        } else {
            $result["status"] = 400;
            $result["TEXT"] = "გრაფიკი ვერ გაიგზავნა";
        }
    }else{
        $result["status"] = 400;
        $result["TEXT"] = "გთხოვთ გრაფიკი დააგენერიროთ დილიდან";
    }
}
else{
    $result["status"] = 400;
    $result["TEXT"] = "გრაფიკი ვერ მოიძებნა";
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

echo json_encode($result,JSON_UNESCAPED_UNICODE);
