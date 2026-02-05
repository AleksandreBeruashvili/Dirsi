<?

require_once($_SERVER["DOCUMENT_ROOT"]."/functions/bp_workflow_functions.php");

$root=$this->GetRootActivity();
$contactId = $root->GetVariable("contactId");
$dealId     =$root->GetVariable("dealId");
$clientPhone = $root->GetVariable("phone");
$clientEmail = $root->GetVariable("email");
$personalId = $root->GetVariable("personalId");
$passportId = $root->GetVariable("passportId");
$actualAddress = $root->GetVariable("actualAddress");
$legalAddress = $root->GetVariable("legalAddress");
$citizenshipType = $root->GetVariable("citizenshipType");
$citizenOf = $root->GetVariable("citizenOf");
$nationality = $root->GetVariable("nationality");

$miznobrioba = $root->GetVariable("miznobrioba");
$contType = $root->GetVariable("contType");


if($clientPhone){
    $fieldMulti = new \CCrmFieldMulti();

    // Get all existing phones in order
    $phones = [];
    $dbFieldMulti = \CCrmFieldMulti::GetList(
        ['ID' => 'ASC'],
        [
            'ENTITY_ID' => 'CONTACT',
            'TYPE_ID' => 'PHONE',
            'ELEMENT_ID' => $contactId,
        ]
    );

    while ($field = $dbFieldMulti->Fetch()) {
        $phones[] = $field;
    }

    if(count($phones) > 0) {
        // Delete all phones
        foreach($phones as $phone) {
            $fieldMulti->Delete($phone['ID']);
        }

        // Add first phone with new value
        $fieldMulti->Add([
            'ENTITY_ID' => 'CONTACT',
            'ELEMENT_ID' => $contactId,
            'TYPE_ID' => 'PHONE',
            'VALUE_TYPE' => $phones[0]['VALUE_TYPE'],
            'VALUE' => $clientPhone, // New value
        ]);

        // Add remaining phones back in same order
        for($i = 1; $i < count($phones); $i++) {
            $fieldMulti->Add([
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'PHONE',
                'VALUE_TYPE' => $phones[$i]['VALUE_TYPE'],
                'VALUE' => $phones[$i]['VALUE'], // Keep old value
            ]);
        }
    } else {
        // If no phone exists, add a new one
        $fieldMulti->Add([
            'ENTITY_ID' => 'CONTACT',
            'ELEMENT_ID' => $contactId,
            'TYPE_ID' => 'PHONE',
            'VALUE_TYPE' => 'WORK',
            'VALUE' => $clientPhone,
        ]);
    }
}

if($clientEmail){
    $fieldMulti = new \CCrmFieldMulti();

    $dbFieldMulti = \CCrmFieldMulti::GetList(
        [],
        [
            'ENTITY_ID' => 'CONTACT',
            'TYPE_ID' => 'EMAIL',
            'ELEMENT_ID' => $contactId,
        ]
    );

    while ($field = $dbFieldMulti->Fetch()) {
        $fieldMulti->Delete($field['ID']);
    }

    $newPhoneData = [
        'ENTITY_ID' => 'CONTACT',
        'ELEMENT_ID' => $contactId,
        'TYPE_ID' => 'EMAIL',
        'VALUE_TYPE' => 'WORK',
        'VALUE' => $clientEmail,
    ];

    $result = $fieldMulti->Add($newPhoneData);

}


// კონტაქტის აფდეითი

$CCrmContact = new CCrmContact();
$upd = array(
    "UF_CRM_1761651998145" => $personalId,
    "UF_CRM_1761652010097" => $passportId,
    "UF_CRM_1761653727005" => $actualAddress,
    "UF_CRM_1761653738978" => $legalAddress,
    "UF_CRM_1761651978222" => $citizenshipType,
    "UF_CRM_1770187155776" => $citizenOf,
    "UF_CRM_1769506891465" => $nationality,
);
$CCrmContact->Update($contactId, $upd);

// დილის აფდეითი
$CCrmDeal = new CCrmDeal();
$upd = array(
    "UF_CRM_1770204779269" => $miznobrioba,
    "UF_CRM_1770204855111" => $contType,
);
$CCrmDeal->Update($dealId, $upd);


