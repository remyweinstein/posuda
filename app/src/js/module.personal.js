/* global d, C, LS_CONTENTS, LS_SECTION, platform */

// Переход на пластиковую карту
C("#personal_changeCard_button").bind("click", () => changeCard());


C('input[name="enableNotifyEmail"]').els.forEach((el) => {
    C(el).bind("change", (e) => {
        changeEnableNotify(el.dataset.type, Number(e.currentTarget.checked));
    });
});

C('input[name="enableNotifyPush"]').els.forEach((el) => {
    C(el).bind("change", (e) => {
        changeEnableNotify(el.dataset.type, Number(e.currentTarget.checked));
    });
});

async function changeEnableNotify(type, value) {
    let result = await api("changeEnableNotify", {
                        value: value,
                        type: type
                    });

    if (result.status) {
        updateCashContent("personal", type, value);
    }
    
    if (result.description) {
        showPopup("", result.description);
        //showToast(result.description);
    }
}

function updateCashContent(type, method, val) {
    let cacheContent = JSON.parse(C().getStor(LS_CONTENTS));
    
    cacheContent[type][method] = val;
    C().setStor(LS_CONTENTS, JSON.stringify(cacheContent));
}

async function updatePersonalData() {
    let result = await api("getProfileData");

    if (result.status) {
        const data = result.data;

        if (data.firstname || data.middlename || data.lastname) {
            C("#personal_name").text([data.firstname, data.middlename, data.lastname].join(" "));
        }

        if (data.birthdate) {
            let date = new Date((data.birthdate).replace(new RegExp("-", 'g'), "/"));

            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timezone: 'UTC'
            };
            
            if (date != "Invalid Date") {
                date = date.toLocaleString("ru", options);
            } else {
                date = "&nbsp;";
            }
            
            C("#personal_birthdate").html(date);
        }

        if (data.phone) {
            const a = data.phone.split('');
            C("#personal_phone").text(`+${a[0]} (${a[1]}${a[2]}${a[3]}) ${a[4]}${a[5]}${a[6]}-${a[7]}${a[8]}-${a[9]}${a[10]}`);
        }

        if (data.email) {
            C("#personal_email").text(data.email);
        } else {
            C('input[name^="enableNotifyEmail"]').el.disabled = "disabled";
            C("#personal_email").text('-');
        }

    } else {
        onErrorCatch(result);
    }
}

function drawPersonal(personal) {
    if (!permitRedrawSection('personal')) {
        return;
    }
    
    C('input[name^="enableNotify"]').els.forEach((el) => {
        let type = el.dataset.type;
        el.checked = (personal[`enable_${type}_notify`] && personal[`enable_${type}_notify`] === 1);
    });

    if (personal.firstname || personal.middlename || personal.lastname) {
        C("#personal_name").text([personal.firstname, personal.middlename, personal.lastname].join(" "));
    }

    if (personal.birthdate) {
        let date = new Date((personal.birthdate).replace(new RegExp("-", 'g'), "/"));

        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timezone: 'UTC'
        };

        if (date != "Invalid Date") {
            date = date.toLocaleString("ru", options);
        } else {
            date = "&nbsp;";
        }
        
        C("#personal_birthdate").html(date);
    }
    
    if (personal.phone) {
        const a = personal.phone.split('');
        C("#personal_phone").text(`+${a[0]} (${a[1]}${a[2]}${a[3]}) ${a[4]}${a[5]}${a[6]}-${a[7]}${a[8]}-${a[9]}${a[10]}`);
    }

    if (personal.email) {
        C("#personal_email").text(personal.email);
    } else {
        C('input[name^="enableNotifyEmail"]').el.disabled = "disabled";
        C("#personal_email").text('-');
    }
}

async function changeCard() {
    let title = "Внимание";
    
    if (C("#personal_new_card").val().length < 14) {
        attentionFocus(C("#personal_new_card").el);
        return;
    }

    C("#personal_changeCard_button").el.disabled = true;

    let result = await api("changeCard", {
                        new_card: C("#personal_new_card").val()
                    });

    C("#personal_changeCard_button").el.disabled = false;

    if (result.status) {
        C("#personal_new_pass").val("");
        C("#personal_new_pass_confirmation").val("");
        title = "";
    }
    
    if (result.description) {
        showPopup(title, result.description);
        //showToast(result.description);
    }

}

async function changeProfileData() {
    const inpPass = "#personal-new-pass";
    const newPass = C(inpPass).val();
    const newConfPass = C(`${inpPass}-confirmation`).val();
    const subBut = C("#personal_changePassword_button").el;

    if (newPass.length > 5 && newPass === newConfPass) {
        subBut.disabled = true;

        const passRes = await api("changePassword", {
                            new_password: newPass
                        });

        if (passRes.status) {
            showPopup("", passRes.description);
            //showToast("Данные профиля изменены");
        } else {
            showPopup("Внимание", passRes.description);
            //showToast(cardRes.description);
        }

        C(inpPass).val("");
        C(`${inpPass}-confirmation`).val("");
        subBut.disabled = false;
    } else {
        if (newPass.length < 6) {
            showPopup("", "Пароль должен содержать не менее 6 символов.");
        } else if (newPass !== newConfPass) {
            showPopup("", "Пароли не совпадают.");
        }
    }
    
    /*
    let cardRes = await api("changeCardType", {
                            discount: C('input[name="systemChange"]:checked').val()
                        });
    */
}

async function setCard() {
    let title;
    let inp = C("#plasticNumber");
    
    inp.el.blur();
    
    if (inp.val().length < 10) {
        showPopup("Внимание", "Не указан номер карты!");
        //showToast("Не указан номер карты");
        return;
    }

    showLoader();

    let result = await api("setCard", {
                        card_number: inp.val()
                    });

    hideLoader();
    inp.val("");

    title = result.status ? "" : "Внимание";
    
    if (result.description) {
        showPopup(title, result.description);
        //showToast(result.description);
    }
}

let loadScanerScript = false;
let scanerIsEnable   = false;

C("#scanerQR").bind("click", () => {
    if (platform) {
        d.addEventListener("deviceready", function () {
            switch (device.platform) {
                case "Android":
                    cordova.plugins.barcodeScanner.scan(
                       function (result) {
                            C("#plasticNumber").el.value = result.text;
                            setCard();
                       },
                       function (error) {
                           //alert(`Scanning failed: ${error}`);
                       },
                       {
                           preferFrontCamera :      false, // iOS and Android
                           showFlipCameraButton :   true, // iOS and Android
                           showTorchButton :        true, // iOS and Android
                           torchOn:                 false, // Android, launch with the torch switched on (if available)
                           saveHistory:             true, // Android, save scan history (default false)
                           prompt :                 "Просканируйте QR код", // Android
                           resultDisplayDuration:   500, // Android, display scanned text for X ms. 0 suppresses it entirely, default 1500
                           formats :                "QR_CODE", // default: all but PDF_417 and RSS_EXPANDED
                           orientation :            "portrait", // Android only (portrait|landscape), default unset so it rotates with the device
                           disableAnimations :      true, // iOS
                           disableSuccessBeep:      false // iOS and Android
                       }
                    );

                    break;
                case "iOS":

                    break;
            }
        });
    } else {
        if (loadScanerScript) {
            startScaner();
        } else {
            loadScaner();
        }
    }
});


function loadScaner() {
    showLoader();
    loadScript('app/build/js/vendors/qrscan.min.js', () => {
        hideLoader();
        loadScanerScript = true;
        startScaner();
    });
}

if (!C("#set_card").el) {
    C("#plasticNumber").bind("textInput", (e) => {
        setTimeout(setCard, 333);
    });
}

let timerDisableFocus;

d.addEventListener("keydown", () => {
    if (C().getStor(LS_SECTION) !== "set_plastic") {
        return;
    }
        
    const inp = C("#plasticNumber").el;
    
    if (d.activeElement !== inp) {
        inp.focus();
    }
    
    timerDisableFocus = setTimeout(() => {
        inp.blur();
    }, 5000);

});

let video = C().create("video").el;

function startScaner() {
    if (!scanerIsEnable) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } }).then(function(stream) {
            video.srcObject = stream;
            video.setAttribute("playsinline", true);
            video.play();
            scanerIsEnable = true;
            promiseTimeout(stopStreamedVideo, 30000);
            requestAnimationFrame(tick);
        });
    } else {
        stopStreamedVideo();
    }
}
    
function tick() {
    let canvasElement = C("#canvas").el;
    let canvas        = canvasElement.getContext("2d");

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvasElement.hidden = false;

        canvasElement.height = video.videoHeight;
        canvasElement.width  = video.videoWidth;
        canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
        
        let imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
        let code = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: "dontInvert"
        });

        if (code) {
            C("#plasticNumber").el.value = code.data;
            stopStreamedVideo();
            setCard();
        }
    }

    requestAnimationFrame(tick);
}
    
function stopStreamedVideo() {
    if (!video.srcObject) return;
    
    video.srcObject.getTracks().forEach(function(track) {
        scanerIsEnable = false;
        track.stop();
        let canvas = C("#canvas").el.getContext("2d");
        canvas.fillStyle = "white";
        canvas.fillRect(0, 0, video.videoWidth, video.videoHeight);
    });
}