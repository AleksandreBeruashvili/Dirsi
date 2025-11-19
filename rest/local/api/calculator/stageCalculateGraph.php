<?
ob_start();
// require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/element.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/functions/functions.php");

function validateDate($date, $format = 'd/m/Y')
{
    $d = DateTime::createFromFormat($format, $date);
    // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
    return $d && $d->format($format) === $date;
}

function dateCompare($date1,$date2)
{
    if($date1 && $date2) {
        $startDate_dateTime = DateTime::createFromFormat('d/m/Y', $date1);
        $endDate_dateTime = DateTime::createFromFormat('d/m/Y', $date2);
        if ($startDate_dateTime < $endDate_dateTime) {
            return true;
        } else return false;
    }
    else{
        return false;
    }
}

function endDateCheck($date1,$date2,$type_selected)
{

    if (validateDate($date1) && validateDate($date2)){
        $graphEndDate = DateTime::createFromFormat('d/m/Y', $date1);
        $endDate_dateTime = DateTime::createFromFormat('d/m/Y', $date2);

//        if ($graphEndDate < $endDate_dateTime || $graphEndDate == $endDate_dateTime || $type_selected == "customType") {
        if (true){
            $result["status"] = 200;
            $result["result"] = "more";
        }
        else {
            $result["status"] = 400;
            $result["result"] = "გრაფიკის დასრულების თარიღი არ უნდა იყოს პროექტის დასრულების თარიღამდე $date2";
        }


    }else if(!validateDate($date1)){
        $result["status"] = 400;
        $result["result"] = "დასრულების თარიღის ფორმატი არასწორია";
    }
    else{
        $result["status"] = 400;
        $result["result"] = "პროექტის დასრულების თარიღი არასწორია";
    }
    return $result;
}

function getPayed($arrFilter,$dealData)
{
    $arrPaid= array();
    $PaymentsArr = getCIBlockElementsByFilter($arrFilter,Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"),array("ID"=>"ASC"));
    if(count($PaymentsArr)) {
        if ($dealData["UF_CRM_1709818236372"] == 121) {
            foreach ($PaymentsArr as $payment){
                $paid = array();
                $paid["amount"] = round($payment["tanxa_gel"],2);
                $paid["date"] = $payment["date"];
                $arrPaid[] = $paid;
            }
        } else {
            foreach ($PaymentsArr as $payment){
                $paid = array();
                $paid["amount"] = round(str_replace("|USD","",$payment["TANXA"]),2);
                $paid["date"] = $payment["date"];
                $arrPaid[] = $paid;

            }
        }
    }
    return $arrPaid;
}

function startDatesMonthsFirstDate($date){

    $arrDate = explode("/", $date);
    return "01/".$arrDate[1]."/".$arrDate[2];
}

function getPaymentDay($date){

    $arrDate = explode("/", $date);
    return $arrDate[0];
}

function getPaymentDate($date,$day){
    $date = DateTime::createFromFormat('d/m/Y', $date);
    $lastDay = $date->format('t');
    if(intval($lastDay) < intval($day)) {
        return $lastDay . "/" . $date->format('m') ."/" . $date->format('Y');
    }
    else{
        return $day . "/" . $date->format('m') ."/" . $date->format('Y');
    }
}


$json = array();
try {
    $json = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}



$dealID     = $json["dealId"];
$type_selected = $json["type_selected"];
$startDate = $json["startDate"];
$period = $json["period"]?:1;


$endDate    = $json["endDate"];
if ($json["advancePayment"]){
    $advancePayDate = $json["advancePayDate"];
    $advancePayment = round($json["advancePayment"],2)?:0;
}
$bookPayment = 0;
if ($json["bookPayment"]){
    $bookPayDate = $json["bookPayDate"];
    $bookPayment = round($json["bookPayment"],2)?:0;
}

$price = $json["price"];
$projEndDate = $json["projEndDate"]?:$endDate;
$endDateCheck = endDateCheck($json["endDate"],$projEndDate,$type_selected);

$lastPayment = $json["lastPayment"]?:0;
if($lastPayment && $lastPayment!="0"){
    $lastPayDate =  $json["lastPayDate"];
    $endDate  =  $json["endDate"];
}
if ($period == 1){
    $paymentsCount = intval((monthsBetweenDates($startDate, $endDate)+1)/$period);
}else if($period==3 || $period==4 || $period==6 || $period==12){
    $paymentsCount = intval((monthsBetweenDates($startDate, $endDate)+1)/$period+ 1) ;
}

// $paymentsCount =4;

if($dealID && is_numeric($dealID)){
    if($price && is_numeric($price)) {
        if ($endDateCheck["status"] == 200) {
            if (validateDate($startDate) && validateDate($endDate)) {
                if (dateCompare($startDate, $endDate)) {
                    if (!$bookPayment || (is_numeric($bookPayment) && $bookPayDate && validateDate($bookPayDate))) {
                        if (!$advancePayment || (is_numeric($advancePayment) && $advancePayDate && validateDate($advancePayDate))) {
                            if (!$advancePayment || (dateCompare($advancePayDate, $startDate))) {
                                if (!$lastPayment || (is_numeric($lastPayment) && $lastPayDate && validateDate($lastPayDate))) {
                                    if (!$lastPayment || (is_numeric($lastPayment) && dateCompare($endDate, $lastPayDate))) {
                                        if ($paymentsCount) {
                                            $arrDATA = array();
                                            $dealData = getDealInfoByID($dealID);

                                            $arrPaid = getPayed(array("IBLOCK_ID" => 21, "PROPERTY_DEAL" => $dealID), $dealData);

                                            if (count($arrPaid)) {
                                                foreach ($arrPaid as $paid) {
                                                    $price = round($price - $paid["amount"], 2);
                                                    $data["payment"] = "რესტრუქტურიზაცია";
                                                    $data["date"] = $paid["date"];
                                                    $data["amount"] = $paid["amount"];
                                                    $data["leftToPay"] = $price;
                                                    array_push($arrDATA, $data);
                                                }
                                            }
                                            $number = 0;
                                            if ($price == $bookPayment) {
                                                $number++;
                                                $data["payment"] = $number;
                                                $data["dateWithFirstDay"] = $bookPayDate;
//                                              $data["date"] = dateWorkingDays($bookPayDate);
                                                $data["date"] = $bookPayDate;
                                                $data["amount"] = $bookPayment;
                                                $data["leftToPay"] = round($price - $bookPayment, 2);
                                                array_push($arrDATA, $data);
                                                $result["status"] = 200;
                                                $result["result"] = $arrDATA;
                                            }else if($price > $bookPayment){
                                                if ($bookPayment) {
                                                    $number++;
                                                    $data["payment"] = $number;
                                                    $data["dateWithFirstDay"] = $bookPayDate;
                                                    $data["date"] = $bookPayDate;
                                                    $data["amount"] = $bookPayment;
                                                    $data["leftToPay"] = round($price - $bookPayment, 2);
                                                    array_push($arrDATA, $data);
                                                    $price -= $bookPayment;
                                                }


                                                if ($price == $advancePayment) {
                                                    $number++;
                                                    $data["payment"] = $number;
                                                    $data["dateWithFirstDay"] = $advancePayDate;
//                                            $data["date"] = dateWorkingDays($advancePayDate);
                                                    $data["date"] = $advancePayDate;
                                                    $data["amount"] = $advancePayment;
                                                    $data["leftToPay"] = round($price - $advancePayment, 2);
                                                    array_push($arrDATA, $data);
                                                    $result["status"] = 200;
                                                    $result["result"] = $arrDATA;
                                                } elseif ($price > $advancePayment) {
                                                    //თუ პირველადი გადახდაა
                                                    if ($advancePayment) {
                                                        $number++;
                                                        $data["payment"] = $number;
                                                        $data["dateWithFirstDay"] = $advancePayDate;
//                                            $data["date"] = dateWorkingDays($advancePayDate);
                                                        $data["date"] = $advancePayDate;
                                                        $data["amount"] = $advancePayment;
                                                        $data["leftToPay"] = round($price - $advancePayment, 2);
                                                        array_push($arrDATA, $data);
                                                    }


                                                    $data["dateWithFirstDay"] = startDatesMonthsFirstDate($startDate);
                                                    $paymentDay = getPaymentDay($startDate);
//                                            $data["date"] = dateWorkingDays($startDate);
                                                    $data["date"] = $startDate;
                                                    $data["leftToPay"] = $price - $advancePayment;


                                                    //firstStageAmount


                                                    $rangePay = round(($price - $advancePayment - $lastPayment) / $paymentsCount);

                                                    for ($i = 0; $i < $paymentsCount; $i++) {
                                                        $number++;
                                                        if ($i != 0) {
                                                            $data["dateWithFirstDay"] = dateAddMonths($data["dateWithFirstDay"], $period);
//                                            $data["date"] = dateWorkingDays($data["dateWithFirstDay"]);
                                                            $data["date"] = getPaymentDate($data["dateWithFirstDay"], $paymentDay);
                                                        }
                                                        $data["payment"] = $number;

                                                        if ($i == $paymentsCount - 1) {
                                                            $data["amount"] = round($data["leftToPay"] - $lastPayment, 2);
                                                            $data["leftToPay"] = round($data["leftToPay"] - $data["amount"], 2);
                                                        } else {
                                                            $data["amount"] = round($rangePay, 2);
                                                            $data["leftToPay"] = round($data["leftToPay"] - $rangePay, 2);
                                                        }
                                                        array_push($arrDATA, $data);

                                                    }

                                                    if ($lastPayment) {
                                                        $number++;
                                                        $data["payment"] = $number;
                                                        $data["dateWithFirstDay"] = $lastPayDate;
//                                            $data["date"] = dateWorkingDays($data["$lastPayDate"]);
                                                        $data["date"] = $lastPayDate;
                                                        $data["amount"] = round($lastPayment, 2);
                                                        $data["leftToPay"] = round($data["leftToPay"] - $lastPayment, 2);
                                                        array_push($arrDATA, $data);
                                                    }

                                                    $result["status"] = 200;
                                                    $result["result"] = $arrDATA;
                                                    $result["endDate"] = $arrDATA;
                                                } else {
                                                    $result["status"] = 400;
                                                    $result["errorTXT"] = "თანხა არასწორად არის შევსებული";
                                                }
                                            }else{
                                                $result["status"] = 400;
                                                $result["errorTXT"] = "ჯავშნის ავანსი არასწორად არის შევსებული";
                                            }
                                        } else {

                                            $result["status"] = 400;
                                            $result["errorTXT"] = "დაწყების და დასრულების თარიღები არასწორადაა შევსებული";
                                        }
                                    } else {
                                        $result["status"] = 400;
                                        $result["errorTXT"] = "ბოლო გადახდის თარიღი არ არის სწორად შევსებული, ბოლო გადახდა უნდა მოხდეს გრაფიკის დასრულების შემდეგ";
                                    }
                                } else {
                                    $result["status"] = 400;
                                    $result["errorTXT"] = "ბოლო გადახდის თარიღი არ არის სწორად შევსებული";
                                }
                            } else {
                                $result["status"] = 400;
                                $result["errorTXT"] = "პირველადი შეტანის თარიღი უნდა იყოს გრაფიკის დაწყების თარიღზე ნაკლები";
                            }
                        } else {
                            $result["status"] = 400;
                            $result["errorTXT"] = "პირველადი გადახდის თარიღი არ არის შევსებული";
                        }
                    } else {
                        $result["status"] = 400;
                        $result["errorTXT"] = "ჯავშნის თარიღი არ არის შევსებული";
                    }
                } else {
                    $result["status"] = 400;
                    $result["errorTXT"] = "დაწყების და დასრულების თარიღები არასწორადაა შევსებული";
                }
            } else {
                $result["status"] = 400;
                $result["errorTXT"] = "დაწყების ან დასრულების თარიღი არ არის ვალიდური";
            }
        }
        else{
            $result["status"] = 400;
            $result["errorTXT"] = $endDateCheck["result"];
        }
    }
    else{
        $result["status"] = 400;
        $result["errorTXT"] = "price is not correct";
    }
}
else{
    $result["status"] = 400;
    $result["errorTXT"] = "deal ID is not valid";
}





ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

echo json_encode($result,JSON_UNESCAPED_UNICODE);
