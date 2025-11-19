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
    if($json["dealId"]) {

        $arForAdd = array(
            'IBLOCK_ID' => 26,
            'NAME' => $json["dealId"],
            'ACTIVE' => 'Y',
        );



        $arPropsOld = array();
        $arPropsOld["JSON"] = json_encode($json);
      

        $res = addCIBlockElement($arForAdd, $arPropsOld);

        if ($res) {
            $result["status"] = 400;
            $result["TEXT"] = "გრაფიკი წარმატებით შეინახა";
            $result["dataID"] = $res;
        } else {
            $result["status"] = 400;
            $result["TEXT"] = "გრაფიკი ვერ შეინახა";
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
