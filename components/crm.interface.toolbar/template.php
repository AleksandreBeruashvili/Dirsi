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

}


?>

<script>
    let pipeline = <?php echo json_encode($pipeline); ?>;
    let pathname = window.location.pathname.split("/");
    let oldStageFromService = '';
    let stageIdFromService = '';
    let dealIdForToolbar = (pathname[4] == undefined) ? (0) : (pathname[4]);
    let catalog = document.getElementById('crm_scope_detail_c_deal__catalog');

	url = <? echo json_encode($url); ?>;
	userID = <? echo json_encode($userID); ?>;
	Product = <? echo json_encode($Product); ?>;
	deal = <? echo json_encode($deal); ?>;


	setInterval(() => {
        fetch(`${location.origin}/rest/local/getDealStage.php?id=${dealIdForToolbar}`)
            .then(data => {
                return data.json();
            })
            .then(data => {
                stageIdFromService = data["deal_data"]["STAGE_ID"];

				// if(userID!=1){
					if (stageIdFromService === "NEW" || stageIdFromService === "PREPARATION" ) {
						catalog.style.display = "none";
					} else {
						catalog.style.display = "";
					}
				// }

            })
            .catch(error => {
                console.log(error);
            });
    }, 500);

	//სანდროს კოდები


		setInterval(function() {
			var terminationDiv = document.getElementById("popup-window-content-entity_progress_TERMINATION");
			if (terminationDiv && window.getComputedStyle(terminationDiv).display === "block") {
				var acceptBtn = terminationDiv.querySelector(".webform-small-button-accept");
				if (acceptBtn) acceptBtn.style.display = "none";
			}
		}, 100);

	//ბათონები და ფუნქციები

		if (!document.getElementById('customButtonStyles')) {
			const style = document.createElement('style');
			style.id = 'customButtonStyles';
			style.innerHTML = `
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
				}
				
				.custom-action-btn:before {
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
				}
				
				.custom-action-btn:hover:before {
					left: 100%;
				}
				
				.custom-action-btn:active {
					transform: translateY(0);
				}
				
				.custom-action-icon {
					width: 18px;
					height: 18px;
					fill: currentColor;
				}
			`;
			document.head.appendChild(style);
		}

		function createButtonContainer() {
			const headerTitle = document.querySelector('.ui-side-panel-toolbar');
			if (headerTitle && !document.getElementById('myButtonsDivEntity')) {
				var buttonContainer = document.createElement('div');
				buttonContainer.id = 'myButtonsDivEntity';
				buttonContainer.style.cssText = 'padding-top: 10px; display: flex; gap: 10px; align-items: center;';
				headerTitle.appendChild(buttonContainer);
				return buttonContainer;
			}
			return document.getElementById('myButtonsDivEntity');
		}

		//გაყიდვა
			if (url[3] == "crm" && url[4] == "deal" && url[5] == "details" && Product && deal["STAGE_ID"] == "2") {
				const observer = new MutationObserver(() => {
					const buttonContainer = createButtonContainer();
					
					if (buttonContainer && !document.getElementById('sellBtn')) {
						var sellButton = document.createElement('button');
						sellButton.id = 'sellBtn';
						sellButton.className = 'custom-action-btn';
						// მწვანე ფერი გაყიდვისთვის
						sellButton.style.cssText = `
								background: linear-gradient(135deg, #8f99c4ff 0%, #427efeff 100%);
								box-shadow: 0 4px 15px rgba(27, 55, 176, 0.4);
						`;
						sellButton.innerHTML = `
							<svg class="custom-action-icon" viewBox="0 0 24 24">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1.81.45 1.61 1.67 1.61 1.16 0 1.6-.64 1.6-1.46 0-.83-.44-1.61-2.11-2.14-1.93-.6-3.27-1.48-3.27-3.3 0-1.59 1.22-2.84 2.94-3.2V4h2.67v2.15c1.63.29 2.79 1.37 2.96 3.08h-1.97c-.1-.71-.44-1.5-1.54-1.5-1.03 0-1.6.61-1.6 1.39 0 .78.58 1.22 2.11 1.75 1.86.63 3.27 1.36 3.27 3.39 0 1.63-1.11 3.08-3.04 3.4z"/>
							</svg>
							<span>Sale</span>
						`;
						
						sellButton.onmouseover = function() {
							this.style.boxShadow = '0 7px 25px rgba(102, 126, 234, 0.5)';
						};
						
						sellButton.onmouseout = function() {
							this.style.boxShadow = '0 4px 15px rgba(27, 55, 176, 0.4)';
						};
						
						sellButton.onclick = function () {
							sellPopup(dealIdForToolbar);
						};
						
						buttonContainer.appendChild(sellButton);
						observer.disconnect();
					}
				});

				observer.observe(document.body, { childList: true, subtree: true });
			}
			function sellPopup(dealIdForToolbar) {
				if (typeof BX !== 'undefined' && BX.SidePanel) {
					BX.SidePanel.Instance.open(
						'/rest/popups/sell.php?DEAL_ID=' + dealIdForToolbar,
						{
							width: 600,
							cacheable: false,
							allowChangeHistory: false,
							title: 'გაყიდვა'
						}
					);
				} else {
					window.open(
						'/rest/popups/sell.php?DEAL_ID=' + dealIdForToolbar,
						'reservationPopup',
						'width=600,height=700,resizable=yes,scrollbars=yes'
					);
				}
			}
			window.reservationPopup = reservationPopup;
		//

		//რეზერვაცია
			if(Product){

				let queue = Product[0]["QUEUE"];         
				let queueParts = queue.split('|');        

				// console.log("rigi");
				// console.log(queue);                       
				// console.log(queueParts);
				// console.log(queueParts[1]);
				// console.log(Product[0]["_WJ6N47"])
				
				if ((((url[3] == "crm" && url[4] == "deal" && url[5] == "details")) 
				&& ((!deal["UF_CRM_1762333827"]) || (Product[0]["_WJ6N47"] == "თავისუფალი" && (isset($queueParts[1]) && trim($queueParts[1]) == dealIdForToolbar)))) 
				&& 
				(deal["STAGE_ID"] == "UC_12CJ1Z" || deal["STAGE_ID"] == "UC_2EW8VW" || deal["STAGE_ID"] !== "UC_15207E" || deal["STAGE_ID"] == "EXECUTING" || deal["STAGE_ID"] !== "UC_BAUB5P" || deal["STAGE_ID"] == "UC_F3FOBF")) {

					// console.log("shemovida")

					const observer = new MutationObserver(() => {
						const buttonContainer = createButtonContainer();
						
						if (buttonContainer && !document.getElementById('reservationBtn')) {
							var reservationButton = document.createElement('button');
							reservationButton.id = 'reservationBtn';
							reservationButton.className = 'custom-action-btn';
							// ლურჯი ფერი რეზერვაციისთვის
							reservationButton.style.cssText = `
								background: linear-gradient(135deg, #8f99c4ff 0%, #427efeff 100%);
								box-shadow: 0 4px 15px rgba(27, 55, 176, 0.4);
							`;
							reservationButton.innerHTML = `
								<svg class="custom-action-icon" viewBox="0 0 24 24">
									<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
								</svg>
								<span>Reservation</span>
							`;
							
							reservationButton.onmouseover = function() {
								this.style.boxShadow = '0 7px 25px rgba(102, 126, 234, 0.5)';
							};
							
							reservationButton.onmouseout = function() {
								this.style.boxShadow = '0 4px 15px rgba(27, 55, 176, 0.4)';
							};
							
							reservationButton.onclick = function () {
								reservationPopup(dealIdForToolbar);
							};
							
							buttonContainer.appendChild(reservationButton);
							observer.disconnect();
						}
					});

					observer.observe(document.body, { childList: true, subtree: true });

					
				}
				function reservationPopup(dealIdForToolbar) {
					if (typeof BX !== 'undefined' && BX.SidePanel) {
						BX.SidePanel.Instance.open(
							'/rest/popups/reservation.php?ResChange=0&DEAL_ID=' + dealIdForToolbar,
							{
								width: 600,
								cacheable: false,
								allowChangeHistory: false,
								title: 'რეზერვაცია'
							}
						);
					} else {
						window.open(
							'/rest/popups/reservation.php?ResChange=0&DEAL_ID=' + dealIdForToolbar,
							'reservationPopup',
							'width=600,height=700,resizable=yes,scrollbars=yes'
						);
					}
				}
				window.reservationPopup = reservationPopup;
			}
		//

		//რეზერვაციის ცვლილება
			if (url[3] == "crm" && url[4] == "deal" && url[5] == "details" && Product && deal["UF_CRM_1762331240"] && deal["STAGE_ID"] != "WON") {
				if(Product[0]["_WJ6N47"] != "თავისდაუფალი" || Product[0]["_WJ6N47"] != "გაყიდული" ){
				const observer = new MutationObserver(() => {
					const buttonContainer = createButtonContainer();
					
					if (buttonContainer && !document.getElementById('resChangeBut')) {
						var resChangeButton = document.createElement('button');
						resChangeButton.id = 'resChangeBut';
						resChangeButton.className = 'custom-action-btn';
						// ლურჯი ფერი რეზერვაციისთვის
						resChangeButton.style.cssText = `
							background: linear-gradient(135deg, #8f99c4ff 0%, #427efeff 100%);
							box-shadow: 0 4px 15px rgba(27, 55, 176, 0.4);
						`;
						resChangeButton.innerHTML = `
							<svg class="custom-action-icon" viewBox="0 0 24 24">
								<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
							</svg>
							<span>Reservation Change</span>
						`;
						
						resChangeButton.onmouseover = function() {
							this.style.boxShadow = '0 7px 25px rgba(102, 126, 234, 0.5)';
						};
						
						resChangeButton.onmouseout = function() {
							this.style.boxShadow = '0 4px 15px rgba(27, 55, 176, 0.4)';
						};
						
						resChangeButton.onclick = function () {
							resChangePopUp(dealIdForToolbar);
						};
						
						buttonContainer.appendChild(resChangeButton);
						observer.disconnect();
					}
				});

				observer.observe(document.body, { childList: true, subtree: true });

				}
			}
			function resChangePopUp(dealIdForToolbar) {
				if (typeof BX !== 'undefined' && BX.SidePanel) {
					BX.SidePanel.Instance.open(
						'/rest/popups/reservation.php?ResChange=1&DEAL_ID=' + dealIdForToolbar,
						{
							width: 600,
							cacheable: false,
							allowChangeHistory: false,
							title: 'რეზერვაციის ცვლილება'
						}
					);
				} else {
					window.open(
						'/rest/popups/reservation.php?ResChange=1&DEAL_ID=' + dealIdForToolbar,
						'resChangePopUp',
						'width=600,height=700,resizable=yes,scrollbars=yes'
					);
				}
			}
			window.resChangePopUp = resChangePopUp;
		//

		//ჯავშნის რიგი
			if (url[3] == "crm" && url[4] == "deal" && url[5] == "details" && Product) {
				if (Product[0]["_WJ6N47"] == "დაჯავშნილი" && (deal["STAGE_ID"] == "PREPARATION" ||  deal["STAGE_ID"] == "PREPAYMENT_INVOICE" ||  deal["STAGE_ID"] == "EXECUTING")) {

					const observer = new MutationObserver(() => {
						const buttonContainer = createButtonContainer();

						if (buttonContainer && !document.getElementById('javshnisRigiBut')) {
							var javshnisRigi = document.createElement('button');
							javshnisRigi.id = 'javshnisRigiBut';
							javshnisRigi.className = 'custom-action-btn';
							javshnisRigi.style.cssText = `
								background: linear-gradient(135deg, #485589ff 0%, #6075a1ff 100%);
								box-shadow: 0 4px 15px rgba(27, 55, 176, 0.4);
							`;
							javshnisRigi.innerHTML = `
								<svg class="custom-action-icon" viewBox="0 0 24 24">
									<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
								</svg>
								<span>Reservation Queue</span>
							`;

							javshnisRigi.onmouseover = function() {
								this.style.boxShadow = '0 7px 25px rgba(102, 126, 234, 0.5)';
							};
							javshnisRigi.onmouseout = function() {
							this.style.boxShadow = '0 4px 15px rgba(27, 55, 176, 0.4)';
							};

							javshnisRigi.onclick = function () {
								javshnisRigiFunc(dealIdForToolbar);
							};

							buttonContainer.appendChild(javshnisRigi);
							observer.disconnect();
						}
					});

					observer.observe(document.body, { childList: true, subtree: true });
				}
			}

			function javshnisRigiFunc(dealIdForToolbar) {
				fetch("/rest/popupsservices/javshnisRigi.php", {
					method: "POST",
					headers: {
						"Content-Type": "application/x-www-form-urlencoded"
					},
					body: "dealId=" + encodeURIComponent(dealIdForToolbar)
				})
				.then(response => response.json())
				.then(data => {
					if (data.status === "success") {
						alert("ჯავშნის რიგის მოთხოვნა გაგზავნილია!");
						setTimeout(() => window.top.location.reload(), 500);
					} else {
						alert("შეტყობინება: " + data.message);
					}
				})
				.catch(() => {
					alert("დაფიქსირდა შეცდომა! სცადეთ თავიდან.");
				});
			}
		//

		//დოკუმენტები
			if ( url[3] == "crm" && url[4] == "deal" && url[5] == "details" && (deal["STAGE_ID"] == "2" || deal["STAGE_ID"] == "3" || deal["STAGE_ID"] == "4"|| deal["STAGE_ID"] == "WON" )) {
				
				const observer = new MutationObserver(() => {
					const buttonContainer = createButtonContainer();
					
					if (buttonContainer && !document.getElementById('documentsBtn')) {
						var documentsButton = document.createElement('button');
						documentsButton.id = 'documentsBtn';
						documentsButton.className = 'custom-action-btn';
						// ნარინჯისფერი დოკუმენტებისთვის
						documentsButton.style.cssText = `
								background: linear-gradient(135deg, #8f99c4ff 0%, #427efeff 100%);
								box-shadow: 0 4px 15px rgba(27, 55, 176, 0.4);
						`;
						documentsButton.innerHTML = `
							<svg class="custom-action-icon" viewBox="0 0 24 24">
								<path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
							</svg>
							<span>Documents</span>
						`;
						
						documentsButton.onmouseover = function() {
							this.style.boxShadow = '0 7px 25px rgba(102, 126, 234, 0.5)';
						};
						
						documentsButton.onmouseout = function() {
							this.style.boxShadow = '0 4px 15px rgba(27, 55, 176, 0.4)';
						};
						
						documentsButton.onclick = function () {
							documentsPopup(dealIdForToolbar);
						};
						
						buttonContainer.appendChild(documentsButton);
						observer.disconnect();
					}
				});

				observer.observe(document.body, { childList: true, subtree: true });
			}
			function documentsPopup(dealIdForToolbar) {
				if (typeof BX !== 'undefined' && BX.SidePanel) {
					BX.SidePanel.Instance.open(
						'/crm/deal/buttons_page.php?dealid=' + dealIdForToolbar,
						{
							width: 600,
							cacheable: false,
							allowChangeHistory: false,
							title: 'დოკუმენტები'
						}
					);
				} else {
					window.open(
						'/crm/deal/buttons_page.php?dealid=' + dealIdForToolbar,
						'reservationPopup',
						'width=600,height=700,resizable=yes,scrollbars=yes'
					);
				}
			}
			window.reservationPopup = reservationPopup;
		//
	//



	// კონკრეტულ სთეიჯებზე გადატანის დაბლოკვა
	if ( userID != 1 && url[3] == "crm" && url[4] == "deal" && url[5] == "details" ) {
		setTimeout(function() {
			// აიდების სია, რომლებიც უნდა დაიბლოკოს
			const blockedIds = ["3", "4"];

			blockedIds.forEach(id => {
				const element = document.querySelector(`[data-id="${id}"]`);
				if (element) {
					element.style.pointerEvents = 'none';
					element.style.opacity = '0.8';

					element.onclick = function(e) {
						e.preventDefault();
						alert('ამ სტეიჯზე ხელით გადატანა შეუძლებელია!');
						return false;
					};
				}
			});
		}, 100);
	}

    setInterval(() => {

		if (pathname[1] == "crm" && pathname[2] == "deal" && pathname[4] != "0" &&  deal["STAGE_ID"] == "NEW") {

			const realEstateSection = document.querySelector("[data-cid='user_qo1d69qy']");
			if (realEstateSection) {
				realEstateSection.style.display = "none";
			}

			const agr = document.querySelector("[data-cid='user_835g4q5p']");
			if (agr) {
				agr.style.display = "none";
			}

			var reservation = document.querySelector("[data-cid='user_iunr3z03']");
			if (reservation) {
				reservation.style.display = 'none';
			}
		}

    }, 500);
</script>

