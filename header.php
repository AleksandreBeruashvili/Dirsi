<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog.php");?>
<?php


$url = $_SERVER['REQUEST_URI'];
$url = explode('/', trim($url, '/'));

global $USER;

$userID = 0; // default if not logged in

if ($USER->IsAuthorized()) {
    $userID = (int)$USER->GetID(); // make sure it's integer
}

?>

<script>

    const userID = <?php echo $userID; ?>;
	url = <? echo json_encode($url); ?>;

    setInterval(() => {
        
            
        let divToCheck=document.querySelectorAll('.crm-kanban-item-fields-item');
        if(divToCheck){        
            
            divToCheck= divToCheck.forEach(element => {
                let divTitle=element.children[0].textContent;
                if(divTitle=='Priority'){
                    divValue=element.children[1].textContent;

                    if(divValue=='Yes'){        
                        element.parentElement.parentElement.style.background='linear-gradient(35deg,#FFE400,#FFA500)';
                    }
                }
            });        
        }
            
    }, 1000);


    if(userID !== 1){
        setInterval(() => {
    
            header=document.getElementById("air-header-menu");
            if(header){
                header.style.display = "none";
            }
            sidePanel=document.querySelector(".menu-items-body");
            if(sidePanel){
                sidePanel.style.display = "none";
            }

            sidePanel2=document.querySelector(".menu-items-footer");
            if(sidePanel2){
                sidePanel2.style.display = "none";
            }

            if (url[0] == "crm" && url[1] == "deal"){ 
                
                    // ლისტ ხედვა
                    stageCvlileba=document.querySelectorAll('.crm-list-stage-bar-table');
                    if(stageCvlileba){        
                        stageCvlileba.forEach(element => {
                            element.style.pointerEvents = 'none';
                        });        
                    }
                    // კენბან ხედვა
                    document.querySelectorAll('.main-kanban-item-wrapper').forEach(item => {
                        item.removeAttribute('draggable');
                        item.addEventListener('dragstart', event => {
                            event.preventDefault();
                            event.stopPropagation();
                        }, true);
                        item.addEventListener('mousedown', event => {
                            if (event.button === 0) {
                                event.stopPropagation();
                            }
                        }, true);

                        item.addEventListener('touchstart', event => {
                            event.stopPropagation();
                        }, true);
                    });
                        
                
            }
        }, 500);
        //

        
        setTimeout(() => {
            if (url[0] == "crm" && url[1] == "deal" && url[3] == "category") {
                document.querySelectorAll('.crm-kanban-column-add-item-button').forEach(btn => {
                    btn.style.display = 'none';
                });
            }
        }, 200);
    }


    setTimeout(() => {
        // CSS დამატება loader-ის დასამალად
        const style = document.createElement('style');
        style.textContent = `
            .gt_loader,
            .gt-loading,
            .gtranslate-loading,
            .skiptranslate .goog-te-spinner-pos,
            .goog-te-spinner-animation {
                display: none !important;
            }
        `;
        document.head.appendChild(style);

        const settingsScript = document.createElement('script');
        settingsScript.textContent = `
            window.gtranslateSettings = {
                "default_language":"en",
                "languages":["ka","en","ru"],
                "wrapper_selector":".gtranslate_wrapper",
                "flag_size":24
            };
        `;
        document.body.appendChild(settingsScript);

        const gtranslateScript = document.createElement('script');
        gtranslateScript.src = "https://cdn.gtranslate.net/widgets/latest/flags.js";
        gtranslateScript.defer = true;
        document.body.appendChild(gtranslateScript);

        var logo = document.getElementsByClassName('menu-items-header');

        if(logo && logo[0]){
            var translatehtml = `<div class="gtranslate_wrapper"></div>`;
            logo[0].insertAdjacentHTML('afterbegin', translatehtml);
        }
    }, 3000);

</script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>