require_once($_SERVER["DOCUMENT_ROOT"]."/functions/bp_workflow_functions.php");

$root=$this->GetRootActivity();
$dealID     =$root->GetVariable("Copy_deal_ID");
$PRODUCT_ID =$root->GetVariable("PRODUCT_ID");


$deal  = getDealInfoByID($dealID);
$element = getProductDataByID($PRODUCT_ID);

reservation_163($deal,$element);