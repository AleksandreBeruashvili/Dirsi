<?php

if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\UI\Extension;
use Bitrix\UI\Toolbar\ButtonLocation as ButtonLocation;
use Bitrix\UI\Buttons;

/** @var array $arParams */
/** @global \CMain $APPLICATION */
/** @var \CBitrixComponent $component */
/** @var \CBitrixComponentTemplate $this */

if (!\Bitrix\Main\Loader::includeModule('ui'))
{
	return;
}

CJSCore::RegisterExt('popup_menu', [
	'js' => [
		'/bitrix/js/main/popup_menu.js',
	],
]);
Extension::load('crm.client-selector');
Extension::load('ui.buttons');
Extension::load('ui.buttons.icons');

$toolbarID = $arParams['TOOLBAR_ID'];
$prefix =  $toolbarID.'_';

$items = [];
$moreItems = [];
$restAppButtons = [];
$communicationPanel = null;
$documentButton = null;
$enableMoreButton = false;

foreach($arParams['BUTTONS'] as $item)
{
	if(!$enableMoreButton && isset($item['NEWBAR']) && $item['NEWBAR'] === true)
	{
		$enableMoreButton = true;
		continue;
	}

	if(isset($item['TYPE']) && $item['TYPE'] === 'crm-communication-panel')
	{
		$communicationPanel = $item;
		continue;
	}

	if(isset($item['TYPE']) && $item['TYPE'] === 'crm-document-button')
	{
		$documentButton = $item;
		continue;
	}

	if(isset($item['TYPE']) && $item['TYPE'] === 'rest-app-toolbar')
	{
		$restAppButtons[] = $item;
		continue;
	}

	if($enableMoreButton)
	{
		$moreItems[] = $item;
	}
	else
	{
		$items[] = $item;
	}
}

$buttons = [];

$bindingMenuMask = '/(lead|deal|invoice|quote|company|contact).*?([\d]+)/i';
if (preg_match($bindingMenuMask, $arParams['TOOLBAR_ID'], $bindingMenuMatches) && Buttons\IntranetBindingMenu::isAvailable())
{
	Extension::load('bizproc.script');

	$buttons[ButtonLocation::RIGHT][] = Buttons\IntranetBindingMenu::createByComponentParameters([
		'SECTION_CODE' => \Bitrix\Crm\Integration\Intranet\BindingMenu\SectionCode::DETAIL,
		'MENU_CODE' => $bindingMenuMatches[1],
		'CONTEXT' => [
			'ID' => $bindingMenuMatches[2],
		],
	]);
}

$communications = [];

if ($communicationPanel)
{
	$data = isset($communicationPanel['DATA']) && is_array($communicationPanel['DATA']) ? $communicationPanel['DATA'] : [];
	$multifields = isset($data['MULTIFIELDS']) && is_array($data['MULTIFIELDS']) ? $data['MULTIFIELDS'] : [];

	$ownerInfo = isset($data['OWNER_INFO']) && is_array($data['OWNER_INFO']) ? $data['OWNER_INFO'] : [];

	$communications = [
		'ownerInfo' => $ownerInfo,
		'arrangedMultiFields' => $multifields,
	];
}

if($enableMoreButton)
{
	$buttons[ButtonLocation::RIGHT][] = new Buttons\SettingsButton();

	?><script>
		BX.ready(
			function()
			{
				BX.InterfaceToolBar.create(
					"<?=CUtil::JSEscape($toolbarID)?>",
					BX.CrmParamBag.create(
						{
							'containerId': 'uiToolbarContainer',
							'items': <?=CUtil::PhpToJSObject($moreItems)?>,
							"moreButtonClassName": "<?= Buttons\Icon::SETTING ?>"
						}
					)
				);
			}
		);
	</script><?php
}

if ($documentButton)
{
	$buttons[ButtonLocation::RIGHT][] = new Buttons\DocumentButton([
		'domId' => $toolbarID.'_document',
		'documentButtonConfig' => $documentButton['PARAMS'],
	]);
}

foreach($items as $item)
{
	$type = isset($item['TYPE']) ? $item['TYPE'] : '';
	$code = isset($item['CODE']) ? $item['CODE'] : '';
	$visible = isset($item['VISIBLE']) ? (bool)$item['VISIBLE'] : true;
	$text = isset($item['TEXT']) ? htmlspecialcharsbx(strip_tags($item['TEXT'])) : '';
	$title = isset($item['TITLE']) ? htmlspecialcharsbx(strip_tags($item['TITLE'])) : '';
	$link = isset($item['LINK']) ? htmlspecialcharsbx($item['LINK']) : '#';
	$icon = isset($item['ICON']) ? htmlspecialcharsbx($item['ICON']) : '';
	$onClick = isset($item['ONCLICK']) ? htmlspecialcharsbx($item['ONCLICK']) : '';

	// this button is very likely dead, but for consistecy with other templates leave it be
	if($type === 'crm-context-menu')
	{
		$menuItems = isset($item['ITEMS']) && is_array($item['ITEMS']) ? $item['ITEMS'] : [];

		$contextMenuButton = new Buttons\Split\Button([
			'text' => $text,
			'color' => Buttons\Color::PRIMARY,
			'className' => 'crm-btn-toolbar-menu', // for js
		]);
		if (!empty($onClick))
		{
			$contextMenuButton->bindEvent('click', new Buttons\JsCode($onClick));
		}
		if (!empty($menuItems))
		{
			?><script>
				BX.ready(
					function()
					{
						BX.InterfaceToolBar.create(
							"<?=CUtil::JSEscape($toolbarID)?>",
							BX.CrmParamBag.create(
								{
									'containerId': "uiToolbarContainer",
									'prefix': '',
									'menuButtonClassName': 'crm-btn-toolbar-menu',
									'items': <?=CUtil::PhpToJSObject($menuItems)?>
								}
							)
						);
					}
				);
			</script><?php
		}

		$buttons[ButtonLocation::RIGHT][] = $contextMenuButton;
	}
	elseif($type == 'toolbar-conv-scheme')
	{
		$params = isset($item['PARAMS']) ? $item['PARAMS'] : [];

		// $containerID = $params['CONTAINER_ID'] ?? null; //not used now, but can be useful later
		$labelID = $params['LABEL_ID'] ?? null;
		$buttonID = $params['BUTTON_ID'] ?? null;
		$schemeDescr = isset($params['SCHEME_DESCRIPTION']) ? $params['SCHEME_DESCRIPTION'] : null;

		$labelID = empty($labelID) ? "{$prefix}{$code}_label" : $labelID;
		$buttonID = empty($buttonID) ? "{$prefix}{$code}_button" : $buttonID;

		$convButton = new Buttons\Split\Button([
			'text' => $schemeDescr,
		]);
		if (isset($item['PRIMARY']) && $item['PRIMARY'] === true)
		{
			$convButton->setColor(Buttons\Color::PRIMARY);
		}
		else
		{
			$convButton->setColor(Buttons\Color::LIGHT_BORDER);
		}

		$convButton->getMainButton()->addAttribute('id', $labelID);
		$convButton->getMenuButton()->addAttribute('id', $buttonID);

		$buttons[ButtonLocation::RIGHT][] = $convButton;
	}
	else
	{
		$fallbackButton = new Buttons\Button([
			'color' => Buttons\Color::PRIMARY,
			'link' => $link,
			'title' => $title,
			'text' => $text,
		]);

		if (!empty($icon))
		{
			$fallbackButton->addClass($icon);
		}

		if (!empty($onClick))
		{
			$fallbackButton->bindEvent('click', new Buttons\JsCode($onClick));
		}

		$buttons[ButtonLocation::RIGHT][] = $fallbackButton;
	}
}

/** @see \Bitrix\Crm\Component\Base::addToolbar - copy-paste */

$bodyClass = $APPLICATION->GetPageProperty('BodyClass');
$APPLICATION->SetPageProperty('BodyClass', ($bodyClass ? $bodyClass . ' ' : '') . 'crm-pagetitle-view');

function getDealInfoByIDToolbar($dealID)
{
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealID), array());
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
}

$this->SetViewTarget('below_pagetitle', 100);
$APPLICATION->IncludeComponent(
	'bitrix:crm.toolbar',
	'',
	[
		'buttons' => $buttons, //ui.toolbar buttons
		'filter' => [], //filter options
		'views' => [],
		'communications' => $communications,
		'isWithFavoriteStar' => false,
		'spotlight' => null,
		'afterTitleHtml' => null,
	],
	$component,
);
$this->EndViewTarget();


GLOBAL $USER;
$userID = $USER->GetID();


function getCIBlockElementsByFilterT($arFilter = array())
{                                        
    $arElements = array();                                                                        
    $arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "DATE_CREATE", "PROPERTY_*");     
    $res = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => 999), $arSelect); 
    while ($ob = $res->GetNextElement()) {                                                       
        $arFilds = $ob->GetFields();                                                              
        $arProps = $ob->GetProperties();                                                          
        $arPushs = array();                                                                       
        foreach ($arFilds as $key => $arFild)
            $arPushs[$key] = $arFild;                            
        foreach ($arProps as $key => $arProp)
            $arPushs[$key] = $arProp["VALUE"];                   
        array_push($arElements, $arPushs);                                                        
    }                                                                                             
    return $arElements;                                                                           
}




function moneyFormatNum_CRM_ENTITY($num,$currency="USD") {
    if($currency == "GEL") {
        $num = floatval(preg_replace('/[^\d.]/', '', $num));
        $numMoney = number_format($num, 2)."₾";
    }else{
        $num = floatval(preg_replace('/[^\d.]/', '', $num));
        $numMoney = "$" . number_format($num, 2);
    }
    return $numMoney;
}

function moneyToNum_CRM_ENTITY($num) {
    $num = floatval(preg_replace('/[^\d.]/', '', $num));

    return $num;
}
function moneyFormatNum_CRM_ENTITY_GEL($num) {
    $num = floatval(preg_replace('/[^\d.]/', '', $num));
    $numMoney = "₾".number_format($num, 2);

    return $numMoney;
}



$url = "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
$url = explode('/', $url);
$dealId = $url[6];

if($dealId){
	$deal = getDealInfoByIDToolbar($dealId);

	$prods = $prods = CCrmDeal::LoadProductRows($dealId);
	if ($prods) {
		$arFilter = array("ID" => $prods[0]['PRODUCT_ID']);
		$Product = getCIBlockElementsByFilterT($arFilter);
	}

	$newStageId = $deal["STAGE_ID"];
	$arrForPipeline = explode(":", $deal["STAGE_ID"]);
	if (count($arrForPipeline) == 2) {
		$pipeline = str_replace("C", "", $arrForPipeline[0]);
	} else {
		$pipeline = 0;
	}






        $paymentsArr = array(
            "PAYMENTS_SUM" => 0,
            "PAYMENTS_SUM_GEL" => 0,
            "PAYMENTS_SUM_FORMATED" => 0,
            "PAYMENTS_SUM_FORMATED_GEL" => 0,
            "PAYMENT_PLAN_SUM" => 0,
            "PAYMENT_PLAN_SUM_FORMATED" => 0,
            "OPPORTUNITY" => 0,
        );


        $paymentsFilter = array(
            "IBLOCK_ID" => 21,
            "PROPERTY_DEAL" => $dealId
        );

        $paymentsArr["OPPORTUNITY"] = moneyFormatNum_CRM_ENTITY($deal["OPPORTUNITY"]);
        $resPayments = getCIBlockElementsByFilterT($paymentsFilter);

        foreach ($resPayments as $payment) {

            $paymentsArr["PAYMENTS_SUM"] += moneyToNum_CRM_ENTITY($payment["TANXA"]);
            $paymentsArr["PAYMENTS_SUM_GEL"] += moneyToNum_CRM_ENTITY($payment["tanxa_gel"]);
        }

        $paymentsArr["PAYMENTS_SUM_FORMATED"] = moneyFormatNum_CRM_ENTITY($paymentsArr["PAYMENTS_SUM"]);
        $paymentsArr["PAYMENTS_SUM_FORMATED_GEL"] = moneyFormatNum_CRM_ENTITY_GEL($paymentsArr["PAYMENTS_SUM_GEL"]);

        $paymentPlanFilter = array(
            "IBLOCK_ID" => 20,
            "PROPERTY_DEAL" => $dealId
        );

        $resPaymentPlan = getCIBlockElementsByFilterT($paymentPlanFilter);

        if($deal["UF_CRM_1702019032102"]==322) {
            $paymentsArr["OPPORTUNITY"] = moneyFormatNum_CRM_ENTITY($deal["UF_CRM_1701778190"],"GEL");

            foreach ($resPaymentPlan as $paymentPlan) {
                $paymentsArr["PAYMENT_PLAN_SUM"] += moneyToNum_CRM_ENTITY($paymentPlan["amount_GEL"]);
            }
        }
        else{
            $paymentsArr["OPPORTUNITY"] = moneyFormatNum_CRM_ENTITY($deal["OPPORTUNITY"],"USD");
            foreach ($resPaymentPlan as $paymentPlan) {
                $paymentsArr["PAYMENT_PLAN_SUM"] += moneyToNum_CRM_ENTITY($paymentPlan["TANXA"]);
            }

        }

        $paymentsArr["diff"] = moneyToNum_CRM_ENTITY($paymentsArr["OPPORTUNITY"]) - moneyToNum_CRM_ENTITY($paymentsArr["PAYMENT_PLAN_SUM"]);
        if ($deal["UF_CRM_1702019032102"]==322) {
            $paymentsArr["diff"] = moneyFormatNum_CRM_ENTITY($paymentsArr["diff"],"GEL");
            $paymentsArr["PAYMENT_PLAN_SUM_FORMATED"] = moneyFormatNum_CRM_ENTITY($paymentsArr["PAYMENT_PLAN_SUM"],"GEL");
        }else{
            $paymentsArr["diff"] = moneyFormatNum_CRM_ENTITY($paymentsArr["diff"],"USD");
            $paymentsArr["PAYMENT_PLAN_SUM_FORMATED"] = moneyFormatNum_CRM_ENTITY($paymentsArr["PAYMENT_PLAN_SUM"],"USD");
        }
}



?>

<script>
(function() {
    'use strict';
    
    // ===== კონფიგურაცია =====
    const CONFIG = {
        POLL_INTERVAL: 500,
        POPUP_WIDTH: 600,
        POPUP_HEIGHT: 700,
        
        // სტეიჯების კონფიგურაცია
        STAGES: {
            NEW: 'NEW',
            PREPARATION: 'PREPARATION',
            PREPAYMENT_INVOICE: 'PREPAYMENT_INVOICE',
            EXECUTING: 'EXECUTING',
            FINAL_INVOICE: 'FINAL_INVOICE',
            WON: 'WON',
            // კასტომ სტეიჯები
            UC_12CJ1Z: 'UC_12CJ1Z',
            UC_2EW8VW: 'UC_2EW8VW',
            UC_15207E: 'UC_15207E',
            UC_BAUB5P: 'UC_BAUB5P',
            UC_F3FOBF: 'UC_F3FOBF'
        },
        
        // დასამალი სტეიჯები ტაბებისთვის
        HIDDEN_STAGES_FOR_TABS: [
            'NEW', 'PREPARATION', 'PREPAYMENT_INVOICE', 'UC_12CJ1Z',
            'UC_2EW8VW', 'UC_15207E', 'EXECUTING', 'UC_BAUB5P',
            'UC_F3FOBF', 'FINAL_INVOICE'
        ],
        
        HIDDEN_STAGES_FOR_DOCS: [
            'NEW', 'PREPARATION', 'PREPAYMENT_INVOICE', 'UC_12CJ1Z',
            'UC_2EW8VW', 'UC_15207E', 'EXECUTING', 'UC_BAUB5P',
            'UC_F3FOBF', 'FINAL_INVOICE', '1'
        ],
        
        // დაბლოკილი სტეიჯები გადატანისთვის
        BLOCKED_STAGE_IDS: ['FINAL_INVOICE', '1', '3', '4']
    };

    // ===== გლობალური ცვლადები (PHP-დან) =====
    const pipeline = <?php echo json_encode($pipeline); ?>;
    const url = <?php echo json_encode($url); ?>;
    const userID = <?php echo json_encode($userID); ?>;
    const Product = <?php echo json_encode($Product); ?>;
    const deal = <?php echo json_encode($deal); ?>;
    const paymentsArr = <?php echo json_encode($paymentsArr ?? []); ?>;
    const approvedInstallment = <?php echo json_encode($approvedInstallment ?? []); ?>;
    
    const pathname = window.location.pathname.split('/');
    const dealIdForToolbar = pathname[4] || 0;
    
    let currentStageId = '';

    // ===== უტილიტა ფუნქციები =====
    const Utils = {
        isDealDetailsPage() {
            return url[3] === 'crm' && url[4] === 'deal' && url[5] === 'details';
        },
        
        hasProduct() {
            return Product && Product.length > 0;
        },

		isAdmin() {
            return userID == 1;
        },
        
        getProductStatus() {
            return this.hasProduct() ? Product[0]['STATUS'] : null;
        },
        
        getProductQueue() {
            if (!this.hasProduct()) return { full: '', parts: [] };
            const queue = Product[0]['QUEUE'] || '';
            return {
                full: queue,
                parts: queue.split('|')
            };
        },
        
        isStageIn(stageId, stages) {
            return stages.includes(stageId);
        },
        
        setElementDisplay(element, show) {
            if (element) {
                element.style.display = show ? '' : 'none';
            }
        },
        
        formatNumber(num) {
            return new Intl.NumberFormat('ka-GE').format(num);
        }
    };

    // ===== DOM მენეჯერი =====
    const DOMManager = {
        selectors: {
            moreButton: '#crm_scope_detail_c_deal__more_button',
            toolbar: '.ui-side-panel-toolbar',
            terminationPopup: '#popup-window-content-entity_progress_TERMINATION',
            
            // ტაბები
            catalog: '#crm_scope_detail_c_deal__catalog',
            ganvadeba: '#crm_scope_detail_c_deal__tab_lists_20',
            gadaxdebi: '#crm_scope_detail_c_deal__tab_lists_21',
            docs: '#crm_scope_detail_c_deal__tab_lists_19',
            restHistory: '#crm_scope_detail_c_deal__tab_lists_25',
            grafDast: '#crm_scope_detail_c_deal__tab_lists_23',
            kalkulaciebi: '#crm_scope_detail_c_deal__tab_lists_24',
            
            // სექციები
            realEstateSection: "[data-cid='user_qo1d69qy']",
            reservationSection: "[data-cid='user_iunr3z03']",
            agreementSection: "[data-cid='user_835g4q5p']"
        },
        
        getElement(selector) {
            return document.querySelector(selector);
        },
        
        getElementById(id) {
            return document.getElementById(id);
        },
        
        hideMoreButton() {
			if(Utils.isAdmin()) return; 
            const btn = this.getElement(this.selectors.moreButton);
            Utils.setElementDisplay(btn, false);
        },
        
        hideTerminationAcceptButton() {
            const popup = this.getElementById('popup-window-content-entity_progress_TERMINATION');
            if (popup && window.getComputedStyle(popup).display === 'block') {
                const acceptBtn = popup.querySelector('.webform-small-button-accept');
                Utils.setElementDisplay(acceptBtn, false);
            }
        }
    };

    // ===== სტილების მენეჯერი =====
    const StyleManager = {
        injectStyles() {
            if (document.getElementById('customButtonStyles')) return;
            
            const style = document.createElement('style');
            style.id = 'customButtonStyles';
            style.textContent = `
                .custom-action-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 20px;
                    color: white;
                    border: none;
                    border-radius: 25px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    position: relative;
                    overflow: hidden;
                    background: linear-gradient(135deg, #8f99c4 0%, #427efe 100%);
                    box-shadow: 0 4px 15px rgba(27, 55, 176, 0.4);
                }
                
                .custom-action-btn::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
                    transition: left 0.5s;
                }
                
                .custom-action-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 7px 25px rgba(102, 126, 234, 0.5);
                }
                
                .custom-action-btn:hover::before {
                    left: 100%;
                }
                
                .custom-action-btn:active {
                    transform: translateY(0);
                }
                
                .custom-action-btn--queue {
                    background: linear-gradient(135deg, #485589 0%, #6075a1 100%);
                }
                
                .custom-action-icon {
                    width: 18px;
                    height: 18px;
                    fill: currentColor;
                }
                
                .leac-title-menu {
                    padding: 10px;
                    margin-bottom: 10px;
                }
                
                .leac-title-menu span {
                    font-weight: bold;
                    color: #39c3ef;
                    font-size: 17px;
                    margin-right: 15px;
                }
            `;
            document.head.appendChild(style);
        }
    };

    // ===== ღილაკების ფაბრიკა =====
    const ButtonFactory = {
        icons: {
            sale: `<svg class="custom-action-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1.81.45 1.61 1.67 1.61 1.16 0 1.6-.64 1.6-1.46 0-.83-.44-1.61-2.11-2.14-1.93-.6-3.27-1.48-3.27-3.3 0-1.59 1.22-2.84 2.94-3.2V4h2.67v2.15c1.63.29 2.79 1.37 2.96 3.08h-1.97c-.1-.71-.44-1.5-1.54-1.5-1.03 0-1.6.61-1.6 1.39 0 .78.58 1.22 2.11 1.75 1.86.63 3.27 1.36 3.27 3.39 0 1.63-1.11 3.08-3.04 3.4z"/></svg>`,
            calendar: `<svg class="custom-action-icon" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>`,
            documents: `<svg class="custom-action-icon" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>`,
            calculator: `<svg class="custom-action-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2C5.9 2 5 2.9 5 4v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2H7zm0 2h10v4H7V4zm0 6h4v4H7v-4zm0 6h4v4H7v-4zm6-6h4v4h-4v-4zm0 6h4v4h-4v-4z"/></svg>`,
            chart: `<svg class="custom-action-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3v18h18v-2H5V3H3zm8 6h2v9h-2V9zm4-4h2v13h-2V5zM7 12h2v6H7v-6z"/></svg>`
        },
        
        createButton(config) {
            const button = document.createElement('button');
            button.id = config.id;
            button.className = `custom-action-btn ${config.extraClass || ''}`;
            button.innerHTML = `${config.icon}<span>${config.label}</span>`;
            button.onclick = config.onClick;
            return button;
        },
        
        getOrCreateContainer() {
            let container = document.getElementById('myButtonsDivEntity');
            if (container) return container;
            
            const toolbar = document.querySelector('.ui-side-panel-toolbar');
            if (!toolbar) return null;
            
            container = document.createElement('div');
            container.id = 'myButtonsDivEntity';
            container.style.cssText = 'padding-top: 10px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;';
            toolbar.appendChild(container);
            return container;
        }
    };

    // ===== პოპაპ მენეჯერი =====
    const PopupManager = {
        open(url, title) {
            if (typeof BX !== 'undefined' && BX.SidePanel) {
                BX.SidePanel.Instance.open(url, {
                    width: CONFIG.POPUP_WIDTH,
                    cacheable: false,
                    allowChangeHistory: false,
                    title: title
                });
            } else {
                window.open(url, 'popup', `width=${CONFIG.POPUP_WIDTH},height=${CONFIG.POPUP_HEIGHT},resizable=yes,scrollbars=yes`);
            }
        },
        
        openSell(dealId) {
            this.open(`/rest/popups/sell.php?DEAL_ID=${dealId}`, 'გაყიდვა');
        },
        
        openReservation(dealId, isChange = false) {
            this.open(`/rest/popups/reservation.php?ResChange=${isChange ? 1 : 0}&DEAL_ID=${dealId}`, isChange ? 'Reservation Change' : 'რეზერვაცია');
        },
        
        openDocuments(dealId) {
            this.open(`/crm/deal/buttons_page.php?dealid=${dealId}`, 'დოკუმენტები');
        },
        
        openCalculator(dealId) {
            window.open(`/custom/calculator?dealid=${dealId}`);
        },
        
        openFinancialReport(dealId) {
            window.open(`/custom/reports/FinancialReport.php?login=yes&dealid=${dealId}`);
        }
    };

    // ===== ტაბების ხილვადობის მენეჯერი =====
    const TabVisibilityManager = {
        updateTabVisibility(stageId) {
            const { STAGES } = CONFIG;
            const elements = {
                catalog: DOMManager.getElementById('crm_scope_detail_c_deal__catalog'),
                ganvadeba: DOMManager.getElementById('crm_scope_detail_c_deal__tab_lists_20'),
                gadaxdebi: DOMManager.getElementById('crm_scope_detail_c_deal__tab_lists_21'),
                docs: DOMManager.getElementById('crm_scope_detail_c_deal__tab_lists_19'),
                restHistory: DOMManager.getElementById('crm_scope_detail_c_deal__tab_lists_25'),
                grafDast: DOMManager.getElementById('crm_scope_detail_c_deal__tab_lists_23'),
                kalkulaciebi: DOMManager.getElementById('crm_scope_detail_c_deal__tab_lists_24')
            };
            
            // კატალოგი და განვადება - დამალვა NEW და PREPARATION სტეიჯებზე
            const hideForNewPrep = stageId === STAGES.NEW || stageId === STAGES.PREPARATION;
            Utils.setElementDisplay(elements.catalog, !hideForNewPrep);
            Utils.setElementDisplay(elements.ganvadeba, !hideForNewPrep);
            
            // კალკულაციები და გრაფიკი - აჩვენე თუ პროდუქტი არსებობს და არ არის NEW/PREPARATION
            const showCalculations = Utils.hasProduct() && !hideForNewPrep;
            Utils.setElementDisplay(elements.kalkulaciebi, showCalculations);
            Utils.setElementDisplay(elements.grafDast, showCalculations);
            
            // გადახდები, დოკუმენტები, ისტორია
            const hideForTabs = Utils.isStageIn(stageId, CONFIG.HIDDEN_STAGES_FOR_TABS);
            Utils.setElementDisplay(elements.gadaxdebi, !hideForTabs);
            
            // დოკუმენტები და ისტორია - უფრო მკაცრი შეზღუდვა
            const hideForDocs = Utils.isStageIn(stageId, CONFIG.HIDDEN_STAGES_FOR_DOCS);
            Utils.setElementDisplay(elements.docs, !hideForDocs);
            Utils.setElementDisplay(elements.restHistory, !hideForDocs);
        },
        
        updateSectionVisibility(stageId) {
            const { STAGES } = CONFIG;
            
            // უძრავი ქონების სექცია
            const realEstate = DOMManager.getElement(DOMManager.selectors.realEstateSection);
            if (stageId === STAGES.NEW || stageId === STAGES.PREPARATION) {
                Utils.setElementDisplay(realEstate, false);
            }
            
            // რეზერვაციის სექცია - მხოლოდ სტეიჯ 1-ზე
            const reservation = DOMManager.getElement(DOMManager.selectors.reservationSection);
            Utils.setElementDisplay(reservation, stageId === '1');
            
            // ხელშეკრულების სექცია
            const agreement = DOMManager.getElement(DOMManager.selectors.agreementSection);
            const showAgreement = ['2', '3', '4', STAGES.WON].includes(stageId);
            Utils.setElementDisplay(agreement, showAgreement);
        }
    };

    // ===== ღილაკების მენეჯერი =====
    const ButtonManager = {
        buttonsAdded: new Set(),
        
        addButton(config) {
            if (this.buttonsAdded.has(config.id)) return;
            
            const container = ButtonFactory.getOrCreateContainer();
            if (!container) return;
            
            if (document.getElementById(config.id)) {
                this.buttonsAdded.add(config.id);
                return;
            }
            
            const button = ButtonFactory.createButton(config);
            container.appendChild(button);
            this.buttonsAdded.add(config.id);
        },
        
        initButtons() {
            if (!Utils.isDealDetailsPage()) return;

            
            const stageId = deal['STAGE_ID'];
            const productStatus = Utils.getProductStatus();
            const queue = Utils.getProductQueue();
            
            // გაყიდვის ღილაკი
            if (Utils.hasProduct() && stageId === '2') {
                this.addButton({
                    id: 'sellBtn',
                    icon: ButtonFactory.icons.sale,
                    label: 'Sale',
                    onClick: () => this.handleSellClick()
                });
            }
            
            // რეზერვაციის ღილაკი
            if (Utils.hasProduct()) {
                const canReserve = !deal['UF_CRM_1762333827'] || 
                    (productStatus === 'თავისუფალი' && queue.parts[1] && queue.parts[1].trim() === String(dealIdForToolbar));
                
                const allowedStages = ['UC_12CJ1Z', 'UC_2EW8VW', 'EXECUTING', 'UC_F3FOBF'];
                const isAllowedStage = Utils.isStageIn(stageId, allowedStages) || 
                    (stageId !== 'UC_15207E' && stageId !== 'UC_BAUB5P');
                
                if (canReserve && isAllowedStage) {
                    this.addButton({
                        id: 'reservationBtn',
                        icon: ButtonFactory.icons.calendar,
                        label: 'Reservation',
                        onClick: () => PopupManager.openReservation(dealIdForToolbar)
                    });
                }
            }
            
            // რეზერვაციის ცვლილების ღილაკი
            if (Utils.hasProduct() && deal['UF_CRM_1762331240'] && stageId === '1') {
                if (productStatus !== 'თავისუფალი' && productStatus !== 'გაყიდული') {
                    this.addButton({
                        id: 'resChangeBut',
                        icon: ButtonFactory.icons.calendar,
                        label: 'Reservation Change',
                        onClick: () => PopupManager.openReservation(dealIdForToolbar, true)
                    });
                }
            }
            
            // ჯავშნის რიგის ღილაკი
            if (Utils.hasProduct() && productStatus === 'დაჯავშნილი') {
                const queueStages = ['PREPARATION', 'PREPAYMENT_INVOICE', 'EXECUTING'];
                if (Utils.isStageIn(stageId, queueStages)) {
                    this.addButton({
                        id: 'javshnisRigiBut',
                        icon: ButtonFactory.icons.calendar,
                        label: 'Reservation Queue',
                        extraClass: 'custom-action-btn--queue',
                        onClick: () => this.handleQueueClick()
                    });
                }
            }
            
            // დოკუმენტების ღილაკი
            const docStages = ['2', '3', '4', 'WON'];
            if (Utils.isStageIn(stageId, docStages)) {
                this.addButton({
                    id: 'documentsBtn',
                    icon: ButtonFactory.icons.documents,
                    label: 'Documents',
                    onClick: () => PopupManager.openDocuments(dealIdForToolbar)
                });
            }
            
            // კალკულატორის ღილაკი
            if (Utils.hasProduct() && stageId !== 'NEW' && stageId !== 'PREPARATION') {
                this.addButton({
                    id: 'kalkButton',
                    icon: ButtonFactory.icons.calculator,
                    label: 'Calculator',
                    onClick: () => PopupManager.openCalculator(dealIdForToolbar)
                });
            }
            
            // ფინანსური რეპორტის ღილაკი
            const finStages = ['1', '2', '3', 'WON'];
            if (Utils.hasProduct() && Utils.isStageIn(stageId, finStages)) {
                this.addButton({
                    id: 'finansRep',
                    icon: ButtonFactory.icons.chart,
                    label: 'Financial Report',
                    onClick: () => PopupManager.openFinancialReport(dealIdForToolbar)
                });
            }
        },
        
        async handleSellClick() {
            try {
                const response = await fetch(`${location.origin}/rest/local/checkBinisGayidva.php?dealID=${dealIdForToolbar}`);
                const data = await response.json();
                
                if (data.status === 200) {
                    PopupManager.openSell(dealIdForToolbar);
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('გაყიდვის შემოწმების შეცდომა:', error);
                alert('დაფიქსირდა შეცდომა! სცადეთ თავიდან.');
            }
        },
        
        async handleQueueClick() {
            try {
                const response = await fetch('/rest/popupsservices/javshnisRigi.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `dealId=${encodeURIComponent(dealIdForToolbar)}`
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert('ჯავშნის რიგის მოთხოვნა გაგზავნილია!');
                    setTimeout(() => window.top.location.reload(), 500);
                } else {
                    alert('შეტყობინება: ' + data.message);
                }
            } catch (error) {
                console.error('ჯავშნის რიგის შეცდომა:', error);
                alert('დაფიქსირდა შეცდომა! სცადეთ თავიდან.');
            }
        }
    };






	// =====  გავლილ სთეიჯებზე გადატანის დაბლოკვა =====
    const StageFreeazer = {
        init() {
            if (!Utils.isDealDetailsPage()) return;
			if(Utils.isAdmin()) return; 

				setTimeout(() => {
					document.querySelectorAll('.crm-entity-section-status-step').forEach(step => {
						const textEl = step.querySelector('.crm-entity-section-status-step-item-text');
						if (!textEl) return;
						if(textEl.style.color){
							step.style.pointerEvents = 'none';
							step.style.opacity = '0.8';
						}
					});
				}, 100);

        }
    };


    // ===== სტეიჯის ბლოკერი =====
    const StageBlocker = {
        init() {
            if (!Utils.isDealDetailsPage()) return;
			if(Utils.isAdmin()) return; 

				setTimeout(() => {
					CONFIG.BLOCKED_STAGE_IDS.forEach(id => {
						const element = document.querySelector(`[data-id="${id}"]`);
						if (element) {
							element.style.pointerEvents = 'none';
							element.style.opacity = '0.8';
							element.onclick = (e) => {
								e.preventDefault();
								alert('ამ სტეიჯზე ხელით გადატანა შეუძლებელია!');
								return false;
							};
						}
					});
				}, 100);
			

        }
    };

    // ===== გადახდების ინფორმაციის მენეჯერი =====
    const PaymentInfoManager = {
        paymentsInfoAdded: false,
        paymentPlanInfoAdded: false,
        
        init() {
            if (pathname[3] !== 'details' || !pathname[4] || pathname[4] <= 0) return;
            
            setTimeout(() => {
                this.setupPaymentsTab();
                this.setupPaymentPlanTab();
            }, 400);
        },
        
        setupPaymentsTab() {
            const paymentsBtn = document.getElementById('crm_scope_detail_c_deal__tab_lists_21');
            if (!paymentsBtn) return;
            
            paymentsBtn.addEventListener('click', () => {
                if (this.paymentsInfoAdded) return;
                
                setTimeout(() => {
                    const container = document.querySelector('#container_lists_attached_crm_21');
                    if (!container) return;
                    
                    const html = `
                        <div class="leac-title-menu">
                            <span>გადახდილი: ${paymentsArr['PAYMENTS_SUM_FORMATED'] || ''} - ${paymentsArr['PAYMENTS_SUM_FORMATED_GEL'] || ''}</span>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforebegin', html);
                    this.paymentsInfoAdded = true;
                }, 1000);
            });
        },
        
        setupPaymentPlanTab() {
            const planBtn = document.getElementById('crm_scope_detail_c_deal__tab_lists_20');
            if (!planBtn) return;
            
            planBtn.addEventListener('click', () => {
                if (this.paymentPlanInfoAdded) return;
                
                setTimeout(() => {
                    const container = document.querySelector('#container_lists_attached_crm_20');
                    if (!container) return;
                    
                    const html = `
                        <div class="leac-title-menu">
                            <span>პროდუქტის ღირებულება: ${paymentsArr['OPPORTUNITY'] || ''};</span>
                            <span>განვადება: ${paymentsArr['PAYMENT_PLAN_SUM_FORMATED'] || ''};</span>
                            <span>სხვაობა: ${paymentsArr['diff'] || ''};</span>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforebegin', html);
                    this.paymentPlanInfoAdded = true;
                }, 600);
            });
        }
    };

    // ===== სტეიჯის მონიტორი =====
    const StageMonitor = {
        async fetchCurrentStage() {
            try {
                const response = await fetch(`${location.origin}/rest/local/getDealStage.php?id=${dealIdForToolbar}`);
                const data = await response.json();
                return data.deal_data?.STAGE_ID || '';
            } catch (error) {
                console.error('სტეიჯის მიღების შეცდომა:', error);
                return '';
            }
        },
        
        async update() {
            const stageId = await this.fetchCurrentStage();
            if (stageId && stageId !== currentStageId) {
                currentStageId = stageId;
                TabVisibilityManager.updateTabVisibility(stageId);
            }
        },
        
        startPolling() {
            setInterval(() => this.update(), CONFIG.POLL_INTERVAL);
        }
    };

    // ===== UI მონიტორი =====
    const UIMonitor = {
        startPolling() {
            // Termination popup მონიტორინგი
            setInterval(() => {
                DOMManager.hideTerminationAcceptButton();
            }, 100);
            
            // სექციების ხილვადობის მონიტორინგი
            if (pathname[1] === 'crm' && pathname[2] === 'deal' && pathname[4] !== '0') {
                setInterval(() => {
                    TabVisibilityManager.updateSectionVisibility(deal['STAGE_ID']);
                }, CONFIG.POLL_INTERVAL);
            }
        }
    };

    // ===== ინიციალიზაცია =====
    function init() {
        // სტილების ინექცია
        StyleManager.injectStyles();
        
        // More ღილაკის დამალვა
        DOMManager.hideMoreButton();
        
        // ღილაკების ინიციალიზაცია
        const observer = new MutationObserver(() => {
            ButtonManager.initButtons();
        });
        observer.observe(document.body, { childList: true, subtree: true });
        
        // სტეიჯების ბლოკირება
        StageBlocker.init();

		//გავლილ სთეიჯებზე გადატანის დაბლოკვა
        StageFreeazer.init();
        // გადახდების ინფო
        PaymentInfoManager.init();
        
        // მონიტორინგის დაწყება
        StageMonitor.startPolling();
        UIMonitor.startPolling();
    }

    // დოკუმენტის მზადყოფნის შემდეგ
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // გლობალური ფუნქციების ექსპორტი (თუ საჭიროა სხვა სკრიპტებიდან)
    window.DealToolbar = {
        openReservation: (dealId) => PopupManager.openReservation(dealId),
        openSell: (dealId) => PopupManager.openSell(dealId),
        openDocuments: (dealId) => PopupManager.openDocuments(dealId)
    };

})();
</script>
