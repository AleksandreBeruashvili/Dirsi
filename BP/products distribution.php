<?

require_once($_SERVER["DOCUMENT_ROOT"]."/functions/bp_workflow_functions.php");

if (!function_exists('reservation_34')) {
    function reservation_34($dealID, $arProducts, $deal)
    {
        $sendNotification = false;
        $errors = array();
        $prodCount = count($arProducts);
        $count = 1;
        if ($prodCount > 1) {
            foreach ($arProducts as $product) {
                $count++;
                if ($count != $prodCount) {
                    $arErrorsTmp = array();
                    $wfId = CBPDocument::StartWorkflow(
                        35,
                        array("crm", "CCrmDocumentDeal", "DEAL_$dealID"),
                        array("TargetUser" => "user_1", "PRODUCT_ID" => $product["PRODUCT_ID"], "copy" => "yes"),
                        $arErrorsTmp
                    );
                } else {
                    $arErrorsTmp = array();
                    $wfId = CBPDocument::StartWorkflow(
                        35,
                        array("crm", "CCrmDocumentDeal", "DEAL_$dealID"),
                        array("TargetUser" => "user_1", "PRODUCT_ID" => $product["PRODUCT_ID"], "copy" => "no"),
                        $arErrorsTmp
                    );
                }
            }
        }
    }
}


$root   = $this->GetRootActivity();
$dealID = $root->GetVariable("DEAL_ID");

//$dealID=35;

$dealID = intval($dealID);
$deal   = getDealInfoByID($dealID);
$logText = "";
$allocation = 0;

$arProducts = CCrmDeal::LoadProductRows($dealID);
if($deal["CLOSED"] != "Y") {
    if (count($arProducts) > 1) {
        $logText = reservation_34($dealID, $arProducts, $deal);
        $allocation = 1;
    }
}

//$this->SetVariable("log", $logText, JSON_UNESCAPED_UNICODE);
