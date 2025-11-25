<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/functions/bp_workflow_functions.php");

if (!function_exists('reservation_34')) {

    function reservation_34($dealID, $arProducts, $deal)
    {
        $prodCount = count($arProducts);
        if ($prodCount <= 1) return;

        $i = 1;
        foreach ($arProducts as $product) {

            $isLast = ($i === $prodCount) ? "no" : "yes";

            $arErrorsTmp = array();

            // !!! მთავარი ფიქსი !!!
            CBPDocument::StartWorkflow(
                35,
                ["crm", "CCrmDocumentDeal", ["DEAL", intval($dealID)]],
                [
                    "TargetUser" => "user_1",
                    "PRODUCT_ID" => $product["PRODUCT_ID"],
                    "copy"       => $isLast
                ],
                $arErrorsTmp
            );

            $i++;
        }
    }
}

$dealID = 101;
$deal   = getDealInfoByID($dealID);
$arProducts = CCrmDeal::LoadProductRows($dealID);

if ($deal["CLOSED"] != "Y" && count($arProducts) > 1) {
    reservation_34($dealID, $arProducts, $deal);
}
