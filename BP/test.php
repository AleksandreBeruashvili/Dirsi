if(!function_exists('getDealByID57')){
    function getDealByID57($id, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
        $arDeals = array();
        $res = CCrmDeal::GetList($arSort, array("ID"=>$id), array());
        if($arDeal = $res->Fetch()){
            return $arDeal;
        } else{
            return array();
        }
    }
    
}

if(!function_exists('getNbgKurs')){
    
    function getNbgKurs(){

        $date = date("Y-m-d");
        $url="https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";
        $seb = file_get_contents($url);
        $seb = json_decode($seb);

        $seb_currency=$seb[0]->currencies[0]->rate;

        return $seb_currency;
    }
}




$root=$this->GetRootActivity();
$deal_ID = $root->GetVariable("deal_ID");
$elemID = $root->GetVariable("elemID");




if($deal_ID){
    $deal =getDealByID57($deal_ID);    
    $xelshNum = trim((string)$deal["UF_CRM_1770640981002"]);
    if ($xelshNum === "") {
        $xelshNum = trim((string)$deal["UF_CRM_1766563053146"]);
    }

    $propertyValues = array();
    $propertyValues['DEAL_ID'] = $deal["ID"];
    $propertyValues['PROJECT'] = $deal["UF_CRM_1693385948133"];
    $propertyValues['KORPUSI'] = $deal["UF_CRM_1718097224965"];
    $propertyValues['BINIS_NOMERI'] = $deal["UF_CRM_1693385964548"];
    $propertyValues['ZETIPI'] = $deal["UF_CRM_1693385992603"];
    $propertyValues['KONTRAKT_DATE'] = $deal["UF_CRM_1693398443196"];
    $propertyValues['NBG'] =  getNbgKurs();
    $propertyValues['FULL_NAME'] = $deal["CONTACT_FULL_NAME"];
    $propertyValues['floor'] = $deal["UF_CRM_1709803989"];
    $propertyValues['xelshNum'] = $xelshNum;

    $element = new CIBlockElement();

    $updateResult = $element->SetPropertyValuesEx($elemID, 21, $propertyValues);


}