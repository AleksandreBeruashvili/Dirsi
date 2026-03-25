<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

if (!CModule::IncludeModule('crm')) {
    die('CRM module not installed');
}

function getDealInfoByIDToolbar($dealId) {
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    return $res->Fetch();
}

function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        $arContact["EMAIL"] = $MAIL["VALUE"];
        return $arContact;
    }
    return $arContact;
}

function getDealFieldsToolbar($fieldName)
{
    $option = array();
    $rsUField = CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $fieldName));
    while ($arUField = $rsUField->GetNext()) {
        $option[$arUField["ID"]] = $arUField["VALUE"];
    }

    return $option;
}

$citizenOf = getDealFieldsToolbar("UF_CRM_1770187155776");
$dealId = isset($_REQUEST['DEAL_ID']) ? intval($_REQUEST['DEAL_ID']) : 0;
$deal = getDealInfoByIDToolbar($dealId);
$contact = getContactInfo($deal["CONTACT_ID"]);
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>გაყიდვა</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            color: #2d3748;
            font-size: 12px;
            line-height: 1.4;
        }

        .container {
            max-width: 860px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #4f63d2 0%, #6b46c1 100%);
            color: white;
            padding: 8px 14px;
        }

        .header h1 {
            font-size: 13px;
            font-weight: 600;
            margin: 0;
        }

        .content {
            padding: 10px 14px;
        }

        .form-group {
            margin-bottom: 5px;
        }

        .form-label {
            display: block;
            font-size: 10.5px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 2px;
        }

        .form-label.required::before {
            content: '• ';
            color: #ef4444;
            font-weight: bold;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 4px 7px;
            border: 1px solid #dde1e7;
            border-radius: 4px;
            font-size: 11.5px;
            transition: border-color 0.15s;
            background: white;
            height: 28px;
            color: #2d3748;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #4f63d2;
            box-shadow: 0 0 0 2px rgba(79, 99, 210, 0.1);
        }

        textarea.form-input {
            height: auto;
            min-height: 50px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }

        @media (max-width: 560px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
        }

        .alert {
            padding: 4px 8px;
            border-radius: 4px;
            margin-bottom: 4px;
            font-size: 10.5px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 3px solid #10b981;
        }

        .footer {
            padding: 8px 14px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            position: sticky;
            bottom: 0;
        }

        .btn {
            padding: 5px 18px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f63d2 0%, #6b46c1 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(79, 99, 210, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 99, 210, 0.35);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Section blocks */
        .section-block {
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 8px;
        }

        .section-block-blue   { background: #eff6ff; border-left: 3px solid #3b82f6; }
        .section-block-green  { background: #f0fdf4; border-left: 3px solid #22c55e; }
        .section-block-amber  { background: #fffbeb; border-left: 3px solid #f59e0b; }
        .section-block-rose   { background: #fff1f2; border-left: 3px solid #f43f5e; }
        .section-block-purple { background: #faf5ff; border-left: 3px solid #a855f7; }
        .section-block-teal   { background: #f0fdfa; border-left: 3px solid #14b8a6; }
        .section-block-slate  { background: #f8fafc; border-left: 3px solid #64748b; }
        .section-block-indigo { background: #eef2ff; border-left: 3px solid #6366f1; }
        .section-block-orange { background: #fff7ed; border-left: 3px solid #f97316; }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 6px;
        }

        input[type="file"].form-input {
            height: auto;
            padding: 3px 7px;
            font-size: 10.5px;
        }

        .error-msg {
            color: #dc2626;
            font-size: 10px;
            margin-top: 2px;
            display: none;
        }

        .error-msg.show {
            display: block;
        }

        .form-input.error-input,
        .form-select.error-input {
            border-color: #ef4444;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .content {
            animation: fadeIn 0.2s ease;
        }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>გაყიდვა</h1>
    </div>

    <div class="content">

        <!-- ხელშეკრულება -->
        <div class="section-block section-block-blue">
            <div class="section-label">📋 ხელშეკრულება</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">ხელშეკრულების გაფორმების თარიღი</label>
                    <input type="date" id="contractDate" class="form-input">
                    <div class="error-msg" id="contractDate-error">გთხოვთ მიუთითოთ თარიღი</div>
                </div>
                <div class="form-group">
                    <label class="form-label required">კონტრაქტის ტიპი</label>
                    <select id="contactType" class="form-select">
                        <option value="">აირჩიეთ...</option>
                        <option value="174">სტანდარტული</option>
                        <option value="175">არასტანდარტული</option>
                    </select>
                    <div class="error-msg" id="contactType-error">გთხოვთ აირჩიოთ კონტრაქტის ტიპი</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ხელშეკრულება (ფაილი)</label>
                    <input id="sellFlat" type="file" class="form-input" onchange="fileShetvirtva('sellFlat')">
                    <input id="sellFlatText" type="hidden">
                </div>
                <div class="form-group">
                    <label class="form-label">პიროვნების დამადასტურებელი დოკ.</label>
                    <input id="sellAttach" type="file" class="form-input" onchange="fileShetvirtva('sellAttach')">
                    <input id="sellAttachText" type="hidden">
                </div>
            </div>
        </div>

        <!-- კონტაქტი -->
        <div class="section-block section-block-teal">
            <div class="section-label">📞 საკონტაქტო ინფო</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ტელეფონი</label>
                    <input type="text" id="phone" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">მეილი</label>
                    <input type="email" id="email" class="form-input">
                </div>
            </div>
        </div>

        <!-- პირადობა -->
        <div class="section-block section-block-purple">
            <div class="section-label">🪪 პირადობა</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">პირადი ნომერი</label>
                    <input type="text" id="personalId" class="form-input" maxlength="11" inputmode="numeric">
                    <div class="error-msg" id="personalId-error">შეავსეთ პირადი ნომერი ან პასპორტი</div>
                </div>
                <div class="form-group">
                    <label class="form-label">პასპორტის ნომერი</label>
                    <input type="text" id="passportId" class="form-input">
                    <div class="error-msg" id="passportId-error">შეავსეთ პირადი ნომერი ან პასპორტი</div>
                </div>
            </div>
        </div>

        <!-- მისამართი -->
        <div class="section-block section-block-green">
            <div class="section-label">🏠 მისამართი</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">იურიდიული მისამართი</label>
                    <input type="text" id="legalAddress" class="form-input">
                    <div class="error-msg" id="legalAddress-error">გთხოვთ შეიყვანოთ იურიდიული მისამართი</div>
                </div>
                <div class="form-group">
                    <label class="form-label required">ფაქტიური მისამართი</label>
                    <input type="text" id="actualAddress" class="form-input">
                    <div class="error-msg" id="actualAddress-error">გთხოვთ შეიყვანოთ ფაქტიური მისამართი</div>
                </div>
            </div>
        </div>

        <!-- მოქალაქეობა -->
        <div class="section-block section-block-amber">
            <div class="section-label">🌍 მოქალაქეობა & ნაციონალობა</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">მოქალაქეობა</label>
                    <select id="citizenshipType" class="form-select" onchange="toggleCitizenOf()">
                        <option value="">აირჩიეთ...</option>
                        <option value="45">რეზიდენტი</option>
                        <option value="46">არა რეზიდენტი</option>
                    </select>
                    <div class="error-msg" id="citizenshipType-error">აირჩიეთ მოქალაქეობა</div>
                </div>
                <div class="form-group" id="citizenOfDiv" style="display:none;">
                    <label class="form-label required">მოქალაქე (ქვეყანა)</label>
                    <select id="citizenOf" class="form-select">
                        <option value=""></option>
                    </select>
                    <div class="error-msg" id="citizenOf-error">გთხოვთ შეავსოთ ქვეყანა</div>
                </div>
                <div class="form-group">
                    <label class="form-label required">ნაციონალობა</label>
                    <select id="nationality" class="form-select">
                        <option value="">აირჩიეთ...</option>
                        <option value="156">Georgian</option>
                        <option value="157">Russian</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- რუსული ველები -->
        <div class="section-block section-block-rose">
            <div class="section-label">🇷🇺 რუსული ველები</div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">სახელი (RU)</label>
                    <input type="text" id="nameRU" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">იურიდიული მისამართი (RU)</label>
                    <input type="text" id="legalAddressRU" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">ფაქტიური მისამართი (RU)</label>
                    <input type="text" id="actualAddressRU" class="form-input">
                </div>
            </div>
        </div>

        <!-- ინგლისური ველები -->
        <div class="section-block section-block-slate">
            <div class="section-label">🇬🇧 ინგლისური ველები</div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">სახელი (ENG)</label>
                    <input type="text" id="nameENG" class="form-input" style="text-transform:capitalize;">
                </div>
                <div class="form-group">
                    <label class="form-label">იურიდიული მისამართი (ENG)</label>
                    <input type="text" id="legalAddressENG" class="form-input" style="text-transform:capitalize;">
                </div>
                <div class="form-group">
                    <label class="form-label">ფაქტიური მისამართი (ENG)</label>
                    <input type="text" id="actualAddressENG" class="form-input" style="text-transform:capitalize;">
                </div>
            </div>
        </div>

        <!-- კლიენტის დახასიათება -->
        <div class="section-block section-block-indigo">
            <div class="section-label">📝 კლიენტის დახასიათება</div>
            <div class="form-group">
                <label class="form-label">კლიენტის დახასიათება</label>
                <textarea id="clientDesc" class="form-input" rows="2"></textarea>
            </div>
        </div>

        <!-- სხვა -->
        <div class="section-block section-block-orange">
            <div class="section-label">⚙️ სხვა</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">შეძენის მიზნობრიობა</label>
                    <select id="miznobrioba" class="form-select">
                        <option value="">აირჩიეთ...</option>
                        <option value="170">საცხოვრებელი</option>
                        <option value="171">საინვესტიციო</option>
                    </select>
                    <div class="error-msg" id="miznobrioba-error">გთხოვთ აირჩიოთ შეძენის მიზნობრიობა</div>
                </div>
                <div class="form-group">
                    <label class="form-label required">რეგისტრაცია რეესტრშია</label>
                    <select id="registrationInRest" class="form-select">
                        <option value="">აირჩიეთ...</option>
                        <option value="1">დიახ</option>
                        <option value="0">არა</option>
                    </select>
                    <div class="error-msg" id="registrationInRest-error">გთხოვთ აირჩიოთ</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">გასაღები გადაცემულია</label>
                    <select id="keytReceived" class="form-select">
                        <option value="">აირჩიეთ...</option>
                        <option value="1">დიახ</option>
                        <option value="0">არა</option>
                    </select>
                    <div class="error-msg" id="keytReceived-error">გთხოვთ აირჩიოთ</div>
                </div>
                <div class="form-group"></div>
            </div>
        </div>

        <input type="hidden" id="sellDealIdHidden" value="<?= (int)$dealId ?>">

    </div>

    <div id="alertSuccess" class="alert alert-success" style="margin: 0 14px;"></div>
    <div id="alertError" class="alert alert-error" style="margin: 0 14px;"></div>

    <div class="footer">
        <button type="button" class="btn btn-secondary" onclick="closePopup()">გაუქმება</button>
        <button type="button" id="submitBtn" class="btn btn-primary" onclick="submitForm()">გაგზავნა</button>
    </div>

</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>

    const deal = <?php echo json_encode($deal, JSON_UNESCAPED_UNICODE); ?>;
    const contact = <?php echo json_encode($contact, JSON_UNESCAPED_UNICODE); ?>;
    const citizenOfData = <?php echo json_encode($citizenOf); ?>;
    const sellDealIdPhp = <?php echo json_encode((int)$dealId); ?>;

    // Fill on load
    window.addEventListener('DOMContentLoaded', () => {

        // Contract date
        const rawDate = deal["UF_CRM_1762416342444"];
        if (rawDate) {
            const parts = rawDate.split('/');
            if (parts.length === 3) {
                document.getElementById('contractDate').value = `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
        }

        setVal('phone',           contact["PHONE"]);
        setVal('email',           contact["EMAIL"]);
        setVal('nameRU',          contact["UF_CRM_1766144180428"]);
        setVal('legalAddressRU',  contact["UF_CRM_1766144198587"]);
        setVal('actualAddressRU', contact["UF_CRM_1766144293570"]);
        setVal('nameENG',         contact["UF_CRM_1767604263120"]);
        setVal('legalAddressENG', contact["UF_CRM_1767604279485"]);
        setVal('actualAddressENG',contact["UF_CRM_1767604297086"]);
        setVal('personalId',      contact["UF_CRM_1761651998145"]);
        setVal('passportId',      contact["UF_CRM_1761652010097"]);
        setVal('legalAddress',    contact["UF_CRM_1761653738978"]);
        setVal('actualAddress',   contact["UF_CRM_1761653727005"]);

        setSelectVal('citizenshipType', contact["UF_CRM_1761651978222"]);
        setSelectVal('nationality',     contact["UF_CRM_1769506891465"], ["156", "157"]);
        setSelectVal('contactType',     deal["UF_CRM_1770204855111"],    ["174", "175"]);
        setSelectVal('miznobrioba',     deal["UF_CRM_1770204779269"],    ["170", "171"]);
        setSelectVal('registrationInRest', deal["UF_CRM_1771499394"],    ["1", "0"]);
        setSelectVal('keytReceived',    deal["UF_CRM_1771499429"],       ["1", "0"]);

        // Fill citizenOf dropdown
        const citizenSelect = document.getElementById('citizenOf');
        let html = '<option value=""></option>';
        Object.entries(citizenOfData).forEach(([key, val]) => {
            html += `<option value="${key}">${val}</option>`;
        });
        citizenSelect.innerHTML = html;
        if (contact["UF_CRM_1770187155776"]) {
            citizenSelect.value = contact["UF_CRM_1770187155776"];
        }

        toggleCitizenOf();

        // Personal ID digits only
        document.getElementById('personalId').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    });

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el && val) el.value = val;
    }

    function setSelectVal(id, val, allowed) {
        const el = document.getElementById(id);
        if (!el || !val) return;
        if (!allowed || allowed.includes(String(val))) {
            el.value = val;
        }
    }

    function toggleCitizenOf() {
        const citizenship = document.getElementById('citizenshipType').value;
        const div = document.getElementById('citizenOfDiv');
        div.style.display = (citizenship === '46') ? 'block' : 'none';
        if (citizenship !== '46') {
            document.getElementById('citizenOf').value = '';
            clearError('citizenOf');
        }
    }

    function showError(id, msg) {
        const el = document.getElementById(id);
        const err = document.getElementById(id + '-error');
        if (el) el.classList.add('error-input');
        if (err) { err.textContent = msg; err.classList.add('show'); }
    }

    function clearError(id) {
        const el = document.getElementById(id);
        const err = document.getElementById(id + '-error');
        if (el) el.classList.remove('error-input');
        if (err) err.classList.remove('show');
    }

    function showAlert(id, msg) {
        const el = document.getElementById(id);
        el.textContent = msg;
        el.classList.add('show');
    }

    function hideAlert(id) {
        document.getElementById(id).classList.remove('show');
    }

    function validateForm() {
        let valid = true;

        // Contract date
        if (!document.getElementById('contractDate').value.trim()) {
            showError('contractDate', 'გთხოვთ მიუთითოთ თარიღი');
            valid = false;
        } else { clearError('contractDate'); }

        // Personal / passport
        const pn = document.getElementById('personalId').value.trim();
        const pp = document.getElementById('passportId').value.trim();
        if (!pn && !pp) {
            showError('personalId', 'შეავსეთ პირადი ნომერი ან პასპორტი');
            showError('passportId', 'შეავსეთ პირადი ნომერი ან პასპორტი');
            valid = false;
        } else {
            if (pn && !/^\d{11}$/.test(pn)) {
                showError('personalId', 'პირადი ნომერი უნდა შეიცავდეს ზუსტად 11 ციფრს');
                valid = false;
            } else { clearError('personalId'); }
            clearError('passportId');
        }

        // Required addresses
        if (!document.getElementById('legalAddress').value.trim()) {
            showError('legalAddress', 'გთხოვთ შეიყვანოთ იურიდიული მისამართი');
            valid = false;
        } else { clearError('legalAddress'); }

        if (!document.getElementById('actualAddress').value.trim()) {
            showError('actualAddress', 'გთხოვთ შეიყვანოთ ფაქტიური მისამართი');
            valid = false;
        } else { clearError('actualAddress'); }

        // Citizenship
        if (!document.getElementById('citizenshipType').value) {
            showError('citizenshipType', 'აირჩიეთ მოქალაქეობა');
            valid = false;
        } else { clearError('citizenshipType'); }

        if (document.getElementById('citizenshipType').value === '46' && !document.getElementById('citizenOf').value) {
            showError('citizenOf', 'გთხოვთ აირჩიოთ ქვეყანა');
            valid = false;
        } else { clearError('citizenOf'); }

        // Contact type
        if (!document.getElementById('contactType').value) {
            showError('contactType', 'გთხოვთ აირჩიოთ კონტრაქტის ტიპი');
            valid = false;
        } else { clearError('contactType'); }

        // Miznobrioba
        if (!document.getElementById('miznobrioba').value) {
            showError('miznobrioba', 'გთხოვთ აირჩიოთ შეძენის მიზნობრიობა');
            valid = false;
        } else { clearError('miznobrioba'); }

        if (document.getElementById('registrationInRest').value === '') {
            showError('registrationInRest', 'გთხოვთ აირჩიოთ');
            valid = false;
        } else { clearError('registrationInRest'); }

        if (document.getElementById('keytReceived').value === '') {
            showError('keytReceived', 'გთხოვთ აირჩიოთ');
            valid = false;
        } else { clearError('keytReceived'); }

        return valid;
    }

    function submitForm() {
        hideAlert('alertError');
        hideAlert('alertSuccess');

        if (!validateForm()) return;

        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'იგზავნება...';

        const formData = new FormData();
        const dealIdParam = (document.getElementById('sellDealIdHidden').value || '').trim()
            || (new URLSearchParams(window.location.search).get('DEAL_ID') || '').trim()
            || String(sellDealIdPhp || '');
        if (!dealIdParam || dealIdParam === '0') {
            showAlert('alertError', 'Deal ID არ მოიძებნა — გაუქმება და ხელახლა გახსენით ფანჯარა დილის გვერდიდან.');
            btn.disabled = false;
            btn.textContent = 'გაგზავნა';
            return;
        }
        formData.append("dealId", dealIdParam);
        formData.append("DEAL_ID", dealIdParam);
        formData.append("contractDate",       document.getElementById('contractDate').value);
        formData.append("sellFlatFile",       document.getElementById('sellFlatText').value);
        formData.append("sellAttachFile",     document.getElementById('sellAttachText').value);
        formData.append("clientDesc",         document.getElementById('clientDesc').value);
        formData.append("phone",              document.getElementById('phone').value);
        formData.append("email",              document.getElementById('email').value);
        formData.append("personalId",         document.getElementById('personalId').value);
        formData.append("passportId",         document.getElementById('passportId').value);
        formData.append("legalAddress",       document.getElementById('legalAddress').value);
        formData.append("actualAddress",      document.getElementById('actualAddress').value);
        formData.append("citizenshipType",    document.getElementById('citizenshipType').value);
        formData.append("citizenOf",          document.getElementById('citizenOf').value);
        formData.append("nationality",        document.getElementById('nationality').value);
        formData.append("nameRU",             document.getElementById('nameRU').value);
        formData.append("legalAddressRU",     document.getElementById('legalAddressRU').value);
        formData.append("actualAddressRU",    document.getElementById('actualAddressRU').value);
        formData.append("nameENG",            document.getElementById('nameENG').value);
        formData.append("legalAddressENG",    document.getElementById('legalAddressENG').value);
        formData.append("actualAddressENG",   document.getElementById('actualAddressENG').value);
        formData.append("miznobrioba",        document.getElementById('miznobrioba').value);
        formData.append("contactType",        document.getElementById('contactType').value);
        formData.append("registrationInRest", document.getElementById('registrationInRest').value);
        formData.append("keytReceived",       document.getElementById('keytReceived').value);

        $.ajax({
            url: "/rest/popupsservices/sell.php",
            type: "POST",
            data: formData,
            dataType: "json",
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.status === "success") {
                    showAlert('alertSuccess', 'მოთხოვნა წარმატებით გაიგზავნა');
                    setTimeout(() => {
                        closePopup();
                        window.top.location.reload();
                    }, 1500);
                } else {
                    let errText = response.message || (response.errors ? JSON.stringify(response.errors) : '') || 'უცნობი შეცდომა';
                    showAlert('alertError', 'შეცდომა: ' + errText);
                    btn.disabled = false;
                    btn.textContent = 'გაგზავნა';
                }
            },
            error: function(xhr, status, error) {
                let msg = [xhr.status, error || status].filter(Boolean).join(' ');
                try {
                    const j = JSON.parse(xhr.responseText || '{}');
                    if (j.message) msg = j.message;
                } catch (e) {
                    const t = (xhr.responseText || '').trim();
                    if (t && t.length < 500) msg = (msg ? msg + ' — ' : '') + t;
                }
                showAlert('alertError', 'Server error: ' + (msg || 'უცნობი'));
                btn.disabled = false;
                btn.textContent = 'გაგზავნა';
            }
        });
    }

    function closePopup() {
        if (window.top.BX && window.top.BX.SidePanel) {
            const slider = window.top.BX.SidePanel.Instance.getTopSlider();
            if (slider) {
                slider.closedProgrammatically = true;
                slider.close();
            }
        } else if (window.BX && BX.SidePanel) {
            BX.SidePanel.Instance.close();
        } else {
            window.close();
        }
    }

    function fileShetvirtva(fieldID) {
        const input = document.getElementById(fieldID);
        const fileIdInput = document.getElementById(`${fieldID}Text`);
        fileIdInput.value = "";
        if (input && input.files.length > 0) {
            const deal_id = <?php echo json_encode((int)$dealId, JSON_UNESCAPED_UNICODE); ?>;
            const data = new FormData();
            data.append('file', input.files[0]);
            data.append('dealId', deal_id);
            fetch(`${location.origin}/rest/local/AXdocUploadFile.php`, {
                method: 'POST',
                body: data
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.uploaded) {
                        fileIdInput.value = data.uploaded;
                    } else {
                        alert("ფაილის ატვირთვა ვერ მოხერხდა!");
                    }
                })
                .catch(err => console.error("Upload error:", err));
        }
    }

</script>
</body>
</html>