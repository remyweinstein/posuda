/* global C, Notification, fetch, Document, Window, attachEvent, DOMAIN */

const API_URL = `${DOMAIN}/api`;
const TERMS_URL = `${DOMAIN}/politika-konfidentsialnosti`;
const RULES_URL = `${DOMAIN}/pravila`;
const REF_RULES_URL = `${DOMAIN}/pravila-akcii`;
const VERSION_URL = `${DOMAIN}/version`;
const LS_TOKEN = 'LS_BearerToken';
const LS_CURR_UPDATE = 'LS_CurrentUpdate';
const LS_CONTENTS = 'LS_Contents';
const LS_NEED_UPDATE = 'LS_NeedUpdate';
const LS_SECTION = 'section';
const LS_PUSHID = 'LS_pushID';
const d = document;

let lastPhone = '';
let secondsInterval = null;
let secondsLeft = 0;
let resetCodeTimer = null;
let resetCodeTimerValue = 0;
let loadBonusesTimer = null;
let viewNewApp = 1;
let currentBrightness;
let newNotifies = false;
let firstStart = true;

const carouselSections = [
    'news',
    'stores',
    'wallet',
    'personal'
];

const sections = {
    intro: {},
    registration: {
        title: 'Регистрация',
        prevSection: 'intro'
    },
    authorization: {
        title: 'Вход',
        prevSection: 'intro'
    },
    reset: {
        title: 'Сброс пароля',
        prevSection: 'authorization'
    },
    personal: {
        title: 'Профиль',
        showMenu: true,
        needAuth: true
    },
    wallet: {
        title: 'Бонусы',
        showMenu: true,
        needAuth: true
    },
    news: {
        title: 'Акции',
        showMenu: true,
        needAuth: true
    },
    refer: {
        title: 'Приглашение',
        showMenu: true,
        prevSection: 'personal',
        needAuth: true
    },
    stores: {
        title: 'Магазин',
        showMenu: true,
        needAuth: true
    },
    reg_success: {
        title: 'Регистрация завершена',
        showMenu: true,
        needAuth: true
    },
    personal_update: {
        title: 'Смена данных',
        showMenu: true,
        prevSection: 'personal',
        needAuth: true
    },
    set_plastic: {
        title: 'Привязка карты',
        showMenu: true,
        prevSection: 'personal_update',
        needAuth: true
    },
    setting_notify: {
        title: 'Разрешения на уведомления',
        showMenu: true,
        prevSection: 'personal_update',
        needAuth: true
    },
    notifications: {
        title: 'Уведомления',
        showMenu: true,
        prevSection: 'wallet',
        needAuth: true,
    },
};

let currentSection = '',
    bearerToken = '',
    userActivityTimeout = null,
    initApp = true,
    clientInfo = 'Сайт',
    clientDevice,
    platform = null,
    versionApp = null,
    tempUpdate = {
        personalHash: '',
        walletHash: '',
        storesHash: '',
        notifyHash: '',
        newsHash: '',
        lastPurchase: '',
        lastTransaction: ''
    };

const deviceType = () => {
    const ua = navigator.userAgent;

    if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
        return 'mobile';
    } else if (/Mobile|Android|iPhone|iPad|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
        return 'mobile';
    }

    return 'desktop';
};

// Инициализация св-в приложения
d.addEventListener('DOMContentLoaded', () => {
    C(d).bind('deviceready', function () {
        let brightness = cordova.plugins.brightness;

        brightness.getBrightness((res) => {
            currentBrightness = res;
        }, (err) => {
            console.log(JSON.stringify(err));
        });

        clientDevice = `${device.platform} ${device.version} (${device.manufacturer} ${device.model})`;
        platform = device.platform;

        cordova.getAppVersion.getVersionCode(function (version) {
            versionApp = version;
            clientInfo = `${device.platform} v${versionApp}`;
        });

        show('#linkDeleteAccount');
        C('#plasticNumber').addclass('farAway');
        C('#set_card').addclass('farAway');

        switch (device.platform) {
            case "Android":
                let messaging = cordova.plugins.firebase.messaging;

                messaging.getToken().then(function (token) {
                    C().setStor(LS_PUSHID, token);
                });
                messaging.onMessage(function (payload) {
                    let gcm = payload.gcm;
                    showPopup(gcm.title, gcm.body);
                });
                messaging.onBackgroundMessage(function (payload) {
                    let gcm = payload.gcm;
                    showPopup(gcm.title, gcm.body);
                });
                break;
            case "iOS":
                cordova.plugins.firebase.messaging.requestPermission({forceShow: false}).then(function() {
                    //console.log("Push messaging is allowed");
                });
                cordova.plugins.firebase.messaging.getToken().then(function (token) {
                    C().setStor(LS_PUSHID, token);
                });
                cordova.plugins.firebase.messaging.onMessage(function (payload) {
                    let gcm = payload.gcm;
                    showPopup(gcm.title, gcm.body);
                });
                cordova.plugins.firebase.messaging.onBackgroundMessage(function (payload) {
                    //
                });
                break;
        }

        C(d).bind('backbutton', function (e) {
            e.preventDefault();

            if (!closeOpenOverlays()) {
                showPopup("Выйти из Мир посуды.Бонусы?",
                    "",
                    "",
                    ["Да", "Нет"],
                    exitApp);
            }
        });
    });

    crashClearStorage();

    let updateStore = JSON.parse(C().getStor(LS_CURR_UPDATE));
    if (updateStore && updateStore.lastNews) {
        delete updateStore.lastNews;
        updateStore.newsHash = "";
        C().setStor(LS_CURR_UPDATE, JSON.stringify(updateStore));
    }

    initPopups();

    bearerToken = C().getStor(LS_TOKEN);

    C().setStor(LS_NEED_UPDATE, JSON.stringify({
        news: 1,
        personal: 1,
        stores: 1,
        wallet: 1,
        purchases: 1
    }));

    // Применим маску ко всем полям ввода номера телефона
    C('input[id*="-mask"]').els.forEach((inp) => {
        mask(inp);
        C(inp).bind("input", (e) => {
            let phone = e.currentTarget.value;
            C('input[id*="-phone-mask"]').els.forEach((phn) => {
                phn.value = phone;
                setPhoneMask(phn);
            });
            C("#reset_button").el.disabled = (phone.length === 16 ? false : true);
        });
    });

    
    // Применим маску ко всем полям ввода даты
    
    C('input[id*="-date_mask"]').els.forEach((inp) => {
        maskDate(inp);
        C(inp).bind("input", (e) => {
            let phone = e.currentTarget.value;
            C('input[id*="-inp-date_mask"]').els.forEach((phn) => {
                phn.value = phone;
                setDateMask(phn);
            });
            C("#reset_button").el.disabled = (phone.length === 16 ? false : true);
        });
    });
    

    // Подключаем обработчики для Popup
    C('span[id*="-popup"]').els.forEach((pop) => {
        const inp = C(`#${pop.id.replace("-popup", "")}`).el;

        ["blur", "input"].map((evt) => {
            C(inp).bind(evt, (e) => {
                dropFail(e.target);
                C(`#${e.target.id}-popup`).delclass("show");
            });
        });
    });

    C("#set_card").bind("click", () => setCard());

    C("#auth-button").bind("click", () => auth());

    C(".system_tabsHead > span label").els.forEach((label) => {
        C(label).bind("click", (e) => {
            const el = e.currentTarget.parentNode,
                elCs = el.parentNode.parentNode.children[1].children,
                tabHeads = el.parentNode.children;

            [...tabHeads].map((tab) => tab.classList.remove("tab_h_active"));

            [...elCs].map((el) => el.classList.remove("tab_c_active"));

            el.classList.add("tab_h_active");
            elCs[el.dataset.tab].classList.add("tab_c_active");
        });
    });

    C("#reg-bd-date_mask").bind("input", (e) => validateBirthdate(e.target));

    C("#personal_changePassword_button").bind("click", () => changeProfileData());

    C("#reset_confirmation_code").bind("input", (e) => C("#reset_confirmation_button").el.disabled = (e.target.value.length === 4 ? false : true));

    C("#reg-confirmation-code").bind("input", (e) => C("#confirmation_button").el.disabled = (e.target.value.length === 4 ? false : true));

    C("#reset-phone-mask").bind("input", (e) => C("#reset_button").el.disabled = (e.target.value.length === 16 ? false : true));

    C(["#personal-new-pass-confirmation, #personal-new-pass"]).els.forEach((el) => {
        C(el).bind("input", () => {
            const idInp = "#personal-new-pass",
                valEl = C(idInp).val(),
                valConf = C(`${idInp}-confirmation`).val();

            C('#personal_changePassword_button').el.disabled = (valEl === valConf) ? false : true;
        });
    });

    C('#personal-new-pass-confirmation').bind('input', (e) => {
        let but = C('#personal_changePassword_button').el;
        const el = e.currentTarget,
            valPass = C('#personal-new-pass').val();

        but.disabled = (valPass === el.value) ? false : true;
    });

    passViewToggle();

    /*const checkBrowserForUpdater = setTimeout(() => {
        if (deviceType() !== "desktop" && !versionApp && C().getStor("NOW_DATE") != new Date().toLocaleDateString()) {
            C(".alertUpdater__desc_name a").el.href = `${DOMAIN}/application`;
            show(C("#alertUpdater").el);
        }
    }, 10000);*/

    C('#reg-button').bind("click", () => {
        if (checkReg()) {
            reg();
        }
    });

    C('#accept_rules').bind("click", () => {
        C('#accept_rules').el.style.boxShadow = 'none';
    });

    C('a[data-click="openBalanceView"]').bind('click', (e) => {
        const el = C('.balance-view').el.classList;

        el.toggle('open');
        e.target.innerHTML = el.contains('open') ? 'Скрыть' : 'Подробнее...';
    });

    C('#reset_button').bind('click', () => {
        if (canGetResetConfirmationCode()) {
            getResetConfirmationCode();
        }
    });

    C('#transactions-details-button').bind('click', (e) => {
        const list = C('#transactions').el.classList,
            t = C(e.target);

        list.toggle('hidden');

        if (list.contains("hidden")) {
            t.text("история операций");
            t.delclass("active");
        } else {
            t.text("скрыть историю");
            t.addclass("active");
        }
    });

    C("#feedback-submit").bind("click", () => setFeedback());

    // Навигация
    C(".bottomNav>li, .mainMenu__content_nav>li").els.forEach((el) => {
        C(el).bind("click", (e) => {
            let section = e.currentTarget.dataset.section;

            closeNav();

            if (section) {
                drawSection(section);
            }
        });
    });

    // Сокрытие всплывающего окна
    C("#popupOverlay").bind("click", (e) => {
        const el = e.currentTarget.classList;

        el.remove("animate__fadeIn", "animate__fadeOut", "animated", "animate__furious");
        el.add("animated", "animate__fadeOut", "animate__furious");

        promiseTimeout(() => {
            const cancel = C('#cancelText').el;

            if (cancel) {
                cancel.parentNode.removeChild(cancel);
            }
            hide("#popupOverlay");
            el.remove("animate__fadeIn", "animate__fadeOut", "animated", "animate__furious");
        }, 500);
    });

    renderSections();
    drawSection((bearerToken && C().getStor(LS_SECTION) !== "reg_success") ? 'wallet' : C().getStor(LS_SECTION));

    checkUpdates(() => {
        if (bearerToken && !loadBonusesTimer) {
            C('body').bind("pointerover", userActivity);
            C('body').bind("pointerdown", userActivity);
        }
    });


    ymaps.ready(function(){
        const y = 135.111677,
        x = 48.427103;

        const myMap = new ymaps.Map('map', {
            center: [x, y],
            zoom: 16
        }, {
            suppressMapOpenBlock: true
        });

        const placemark = new ymaps.Placemark(myMap.getCenter(), {
            balloonContentHeader: '<a href = "#">Мир посуды</a>',
            balloonContentBody: '<a href="tel:+7-4212-45-13-00">+7 (4212) 45-13-00</a><br/>' +
                '<b>Режим работы</b> <br/>Ежедневно <br/>С 10:00 до 20:00',
            hintContent: 'Мир посуды'
        });
        myMap.geoObjects.add(placemark);
        placemark.balloon.open();
    });
});

let startSwipeX = 0;
let stopSwipeX = 0;
let startSwipeY = 0;
let stopSwipeY = 0;

function checkSwipeX() {
    const currentSection = C().getStor(LS_SECTION);

    if (canSlidePages(currentSection)) {
        const diffX = stopSwipeX - startSwipeX;
        const diffY = Math.abs(stopSwipeY - startSwipeY);

        if (Math.abs(diffX) > 200 && diffY < 100) {
            let nextSection;

            if (diffX > 0) {
                nextSection = getPrevSection(currentSection);
            } else {
                nextSection = getNextSection(currentSection);
            }

            drawSection(nextSection);
        }
    }
}

function canSlidePages(currentSection) {
    return carouselSections.includes(currentSection);
}

function getNextSection(currentSection) {
    const i = carouselSections.indexOf(currentSection) + 1;
    let count = carouselSections.length;

    if (count === i) {
        return carouselSections[0];
    }

    return carouselSections[i];
}

function getPrevSection(currentSection) {
    const i = carouselSections.indexOf(currentSection) - 1;
    let count = carouselSections.length - 1;

    if (i < 0) {
        return carouselSections[count];
    }

    return carouselSections[i];
}

function closeOpenOverlays() {
    const list = [".storeMap", "#overlay-menu", "#feedback", ".qrcodeOverlay", ".positionOverlay", ".newsOverlay", "#popupOverlay", ".topNav__back", "#set_plastic"];
    let disp = (id) => {
        return C(id).el.style.display !== "none";
    };
    let rem = (id) => {
        const el = C(id).el;

        if (el) {
            el.parentNode.removeChild(el);
            return true;
        }

        return false;
    };
    let isFind = false;

    for (let id of list) {
        switch (id) {
            case ".storeMap": {
                isFind = rem(id);
                break;
            }

            case ".topNav__back": {
                if (disp(id)) {
                    routePrevSection();
                    isFind = true;
                }
                break;
            }

            case ".qrcodeOverlay": {
                C(d).bind("deviceready", function () {
                    cordova.plugins.brightness.setBrightness(currentBrightness, (suc) => { }, (err) => { });
                });
            }

            default: {
                if (disp(id)) {
                    if (id === "#popupOverlay") {
                        rem("#cancelText");
                    }

                    if (id === ".newsOverlay") {
                        C(".newsCatalogImage").els.forEach((el) => {
                            el.parentNode.removeChild(el);
                        });
                        C(".newsOverlay").el.scrollTop = 0;
                        hide(".newsOverlay");
                        hide(".newsOverlay .icon-cancel");
                        d.body.classList.remove("hideOverflow");
                    }

                    hide(id);
                    isFind = true;
                }
                break;
            }
        }

        if (isFind) {
            C("body").delclass("hideOverflow");
            break;
        }
    }

    return isFind;
}

function exitApp() {
    navigator.app.exitApp();
}

function closeUpdater() {
    C().setStor("NOW_DATE", new Date().toLocaleDateString());
    hide(C("#alertUpdater").el);
}

function permitRedrawSection(section) {
    let permit = true;
    const needUp = JSON.parse(C().getStor(LS_NEED_UPDATE));

    if (needUp[section] === 0) {
        permit = false;
    }

    needUp[section] = 0;
    C().setStor(LS_NEED_UPDATE, JSON.stringify(needUp));

    return permit;
}

function crashClearStorage() {
    if (!C().getStor('crash')) {
        C().delStor(LS_CURR_UPDATE);
        C().delStor(LS_CONTENTS);
        C().setStor('crash', 1);

        if (C().getStor('crash_clear')) {
            C().delStor('crash_clear');
        }
    }
}

function passViewToggle() {
    C('input + i[class^="icon-eye"]').els.forEach((el) => {
        C(el).bind('click', (e) => {
            const i = e.currentTarget;
            let inp = i.parentNode.children[0];

            i.classList.remove('icon-eye', 'icon-eye-off');

            inp.type = (inp.type === 'password' ? 'text' : 'password');
            if (inp.type === 'password') {
                i.classList.add('icon-eye-off');
            } else {
                i.classList.add('icon-eye');
            }
        });
    });
}

function initPopups() {
    C('.popup-text').els.forEach((el) => {
        C(el).bind('click', () => {
            if (el.classList.contains('show')) {
                el.classList.remove('show');
            }
        });
    });
}

function userActivity() {
    if (!userActivityTimeout) {
        userActivityTimeout = setTimeout(checkUpdates, 3333);
    }
}

function openNav() {
    show('#overlay-menu');
}

function closeNav() {
    hide('#overlay-menu');
}

async function promiseTimeout(fn, ms) {
    await new Promise((resolve) => setTimeout(resolve, ms));
    return fn();
}

function removeChildrens(el) {
    while (el.firstChild) {
        el.removeChild(el.firstChild);
    }
}

function routePrevSection() {
    const section = C().getStor(LS_SECTION);

    if (sections[section] && sections[section].prevSection) {
        drawSection(sections[section].prevSection);
    }
}

function renderSections() {
    if (!isEmpty(C().getStor(LS_CONTENTS))) {
        const contents = JSON.parse(C().getStor(LS_CONTENTS));

        drawPersonal(contents.personal);
        drawWallet(contents.wallet);
    }
}

async function drawSection(section) {
    if (!section) {
        section = "intro";
    }

    console.log("drawing section", section);

    if (section === "wallet") {
        C("main.main").addclass("noback");
    } else {
        C("main.main").delclass("noback");
    }

    switch (section) {
        default: {
            break;
        }

        case "registration": {
            show("#registration_cont");
            hide("#reg_confirmation");

            break;
        }

        case "personal": {
            break;
        }

        case "stores": {
            break;
        }

        case "wallet": {
            break;
        }

        case "refer": {
            renderReferSection();
            break;
        }

        case "reg_success": {
            C('#tempName').text(C().getStor('reg_name'));
            break;
        }

        case "news": {
            break;
        }

        case "set_plastic": {
            break;
        }
    }

    C(".main > div").els.forEach((el) => {
        if (el.id === section) {
            if (!el.classList.contains("active")) {
                el.classList.add("active");
            }
            C(".main").el.scrollIntoView();
        } else {
            el.classList.remove("active");
        }
        hideLoader();
    });

    C("header").el.style.display = (sections[section] && sections[section].title ? "" : "none");
    C(".topNav__back").el.style.display = (sections[section] && sections[section].prevSection ? "" : "none");
    C(".topNav__msg").el.style.display = (sections[section] && !sections[section].prevSection ? "" : "none");
    C("header h6").text(sections[section].title);
    C(".topNav__menu").el.style.display = (sections[section] && sections[section].showMenu ? "" : "none");
    //C(".topNav__close").el.style.display = (["alerts"].indexOf(section) === -1 ? "none" : "");
    C(".topNav__close").el.style.display = "none";

    C("footer").el.style.display = (sections[section] && sections[section].showMenu ? "" : "none");

    C(".bottomNav > li").els.forEach((el) => {
        el.classList.remove("current-section");

        if (el.dataset.section === section) {
            el.classList.add("current-section");
        }
    });

    C().setStor(LS_SECTION, section);
}

async function renderReferSection() {
    let response = await getReferLink();
    const referQr = C("#referQr").el;

    if (response.status) {
        const { data } = response;

        if (!referQr.children.length) {
            const qrCanvas = C().create("canvas").el;
            let qr = new QRious({
                element: qrCanvas,
                size: 192,
                value: data.link
            });

            referQr.appendChild(qrCanvas);
            qrCanvas.classList.add("animated", "animate__fadeIn");

            show("#referLink");

            C("#referLinkTG").attr("href", `https://t.me/share/url?url=${data.link}&text=Мир посуды: бонусы&utm_source=ref_tg`);
            C("#referLinkWA").attr("href", `https://api.whatsapp.com/send?text=Мир посуды: бонусы ${data.link}&utm_source=ref_wa`);
        }

        if (data.referrals && data.referrals.length)
            data.referrals.map((ref_row) => {
                const tr = C().create("tr"),
                    td = C().create("td");

                td.text(ref_row.last_sync);
                tr.append(td);

                td = C().create("td");
                td.text(`7-***-***-${ref_row.phone}`);
                tr.append(td);

                td = C().create("td");
                td.text((ref_row.gifted ? "Совершена покупка" : "Регистрация по приглашению"));
                tr.append(td);

                td = C().create("td");
                if (ref_row.gifted) {
                    td.style("fontWeight", "bold");
                }
                td.text((ref_row.gifted ? `+${ref_row.referral_gift}` : 'n/a'));
                td.addclass(ref_row.gifted ? 'good' : 'bad');
                tr.append(td);

                C('#referrals').append(tr);
            });
    }
}

async function checkAuthorization() {
    return await api("checkAuthorization");
}

async function auth() {
    const authPhoneEl = C("#auth-phone-mask"),
        authPassEl = C("#auth-pass"),
        authPassPop = C("#auth-pass-popup"),
        phone = getPhoneNumbers(C("#auth-phone-mask").val()),
        authButton = C("#auth-button").el;

    if (!phone || phone.length !== 11) {
        showInputPopup("auth-phone-mask");
        return;
    } else {
        authPhoneEl.delclass("fail");
    }

    if (authPassEl.val() === "") {
        authPassEl.el.scrollIntoView();
        authPassEl.togclass("fail");
        authPassEl.el.focus();
        authPassPop.togclass("show");
        return;
    }

    authButton.disabled = true;

    let result = await api("authorization", {
        phone,
        pass: authPassEl.val()
    });

    authButton.disabled = false;

    if (result.status) {
        clearLocalStorage();

        C().setStor(LS_TOKEN, result.data.token);
        C().setStor(LS_SECTION, "wallet");

        location.reload();
    } else {
        showPopup("", result.description);
        //showToast(result.description);
    }
}

function checkReg() {
    const regPhoneEl = C("#reg-phone-mask"),
        regBdEl = C("#reg-bd-date_mask").el,
        regPassEl = C("#reg-pass"),
        regPassConfEl = C("#reg-pass-confirm"),
        phone = getPhoneNumbers(regPhoneEl.val());

    if (phone.length !== 11) {
        showInputPopup("reg-phone-mask");
        return 0;
    } else {
        regPhoneEl.delclass("fail");
    }

    if (regPassEl.val().length < 6) {
        showInputPopup("reg-pass");
        return 0;
    }

    if (!validateBirthdate(regBdEl, true)) {
        return 0;
    }

    if (regPassEl.val() !== regPassConfEl.val()) {
        showPopup("Внимание", "Введенные пароли не совпадают!");
        //showToast("Введенные пароли не совпадают!");
        return 0;
    }

    if (!C('#accept_rules').el.checked) {
        C('#accept_rules').el.style.boxShadow = '0 0 10px red';
        return 0;
    }

    return 1;
}

async function reg() {
    let regPhoneEl = C("#reg-phone-mask"),
        regBdEl = C("#reg-bd-date_mask"),
        regButtonEl = C("#reg-button").el,
        phone = getPhoneNumbers(regPhoneEl.val()),
        firstname = C("#reg_firstname").val(),
        birthdate;


    if (regBdEl.val()) {
        let td = regBdEl.val().split("-");
        birthdate = [td[2], td[1], td[0]].join("-");
    }

    lastPhone = phone;

    regButtonEl.disabled = true;
    showLoader();

    let result = await api("registration", {
        phone,
        birthdate,
        pass: C("#reg-pass").val(),
        firstname,
        discount: 0,
        email: C("#reg_email").val(),
        city: 1
    });

    C().setStor('reg_name', firstname);

    regButtonEl.disabled = false;
    hideLoader();

    if (result.status) {
        if (result.data && result.data.need_confirmation) {
            let regConfCode = C("#reg-confirmation-code");

            hide("#registration_cont");
            show("#reg_confirmation");
            regConfCode.el.scrollIntoView();
            regConfCode.togclass("fail");
            regConfCode.el.focus();

            // Запускаем таймер отсчета для повторной отправки
            setConfirmationTimeout(result);
        }
    } else {

        if (result.description) {
            promiseTimeout(() => {
                showPopup('', `${result.description} Предлагаем <a href="#" onclick="drawSection('authorization');return false;>Войти</a> или <a href="#" onclick="drawSection('reset');return false;">восстановить пароль</a> выберите нужное`);
            }, 1000);
        }
    }
}

function setConfirmationTimeout(result) {
    let regConfRemindEl = C('#reg_confirmation_remind'),
        regConfCodePopupEl = C('#reg-confirmation-code-popup'),
        regConfInfoEl = C('#reg_confirmation_info');

    hide('#confirmation_button_reset');
    //show('#confirmation_button_reset_note');
    secondsLeft = result.data.seconds_left;
    regConfCodePopupEl.text(result.description);
    regConfInfoEl.text(result.description);
    regConfRemindEl.text(`Повторная отправка будет доступна через ${secondsLeft} сек.`);

    if (secondsInterval) {
        clearInterval(secondsInterval);
    }

    secondsInterval = setInterval(() => {
        secondsLeft--;
        regConfRemindEl.text(`Повторная отправка будет доступна через ${secondsLeft} сек.`);
        if (secondsLeft <= 0) {
            clearInterval(secondsInterval);
            regConfRemindEl.text('');
            sendSmsConfirmationCode();
            //show('#confirmation_button_reset');
            //show('#confirmation_button_reset_note');
        }
    }, 1000);
}

async function confirmation() {
    let regConfCodeEl = C("#reg-confirmation-code"),
        regConfCodePopupEl = C("#reg-confirmation-code-popup"),
        confButtonEl = C("#confirmation_button");

    if (regConfCodeEl.val().length < 4) {
        regConfCodeEl.el.scrollIntoView();
        regConfCodeEl.addclass("fail");
        regConfCodeEl.el.focus();
        regConfCodePopupEl.togclass("show");
        return;
    }

    if (lastPhone && regConfCodeEl.val()) {
        confButtonEl.el.disabled = true;
        showLoader();

        let result = await api("confirmation", {
            phone: lastPhone,
            code: regConfCodeEl.val()
        });

        confButtonEl.el.disabled = false;
        hideLoader();

        if (result.status) {
            clearLocalStorage();

            C().setStor(LS_SECTION, "reg_success");
            C().setStor(LS_TOKEN, result.data.token);

            location.reload();
            // if (result.data.setNewPassword == undefined) {
            //   drawSection("reg_success");
            // } else {
            //   drawSection("intro");
            // }
        } else {
            if (result.description) {
                regConfCodeEl.val("");
                showPopup("Внимание", result.description);
                //showToast(result.description);
            }
        }
    }
}

async function confirmationReset() {
    let confButtonReset = C("#confirmation_button_reset").el;
    if (lastPhone) {
        confButtonReset.disabled = true;

        let result = await api("confirmationReset", {
            phone: lastPhone
        });

        confButtonReset.disabled = false;

        if (result.status) {
            setConfirmationTimeout(result);
        }
    }
}

function canGetResetConfirmationCode() {
    let resetPhoneEl = C("#reset-phone-mask"),
        resetPhonePopEl = C("#reset-phone-popup");

    if (resetPhoneEl.val().length < 16) {
        resetPhoneEl.el.scrollIntoView();
        resetPhoneEl.addclass("fail");
        resetPhoneEl.el.focus();
        resetPhonePopEl.togclass("show");
        return 0;
    }

    return 1;
}

async function getResetConfirmationCode() {
    let resPhoneEl = C("#reset-phone-mask"),
        resButtonEl = C("#reset_button").el,
        resConfInfoEl = C("#reset_confirmation_info");

    if (resPhoneEl.val()) {
        resButtonEl.disabled = true;

        let result = await api("getResetConfirmationCode", {
            phone: resPhoneEl.val()
        });

        if (result.status) {
            show("#reset_confirmation");
            resConfInfoEl.text(result.description);
            if (result.data.seconds_left) {
                restartResetConfirmationTimer(result.data.seconds_left);
            }
        } else {
            resButtonEl.disabled = false;
            promiseTimeout(() => {
                showPopup("Внимание", result.description);
            }, 1000);
            //showToast(result.description);
        }
    }
}

async function sendSmsConfirmationCode() {
    let resPhoneEl = C("#reset-phone-mask"),
        resButtonEl = C("#reset_button").el,
        resConfInfoEl = C("#reset_confirmation_info");

    if (resPhoneEl.val()) {
        resButtonEl.disabled = true;

        let result = await api("getResetConfirmationSms", {
            phone: resPhoneEl.val()
        });

        if (result.status) {
            show("#reset_confirmation");
            resConfInfoEl.text(result.description);
        } else {
            promiseTimeout(() => {
                showPopup("Внимание", result.description);
            }, 1000);
            //showToast(result.description);
        }
    }
}

function restartResetConfirmationTimer(seconds) {
    let resConfTimeEl = C("#reset_confirmation_time");

    resetCodeTimerValue = seconds - 1;

    show("#reset_confirmation_time");
    resConfTimeEl.text(`${resetCodeTimerValue} сек.`);

    if (resetCodeTimer) {
        clearInterval(resetCodeTimer);
    }

    resetCodeTimer = setInterval(() => {
        show("#reset_confirmation_time");
        resConfTimeEl.text(`${resetCodeTimerValue} сек.`);
        resetCodeTimerValue--;

        if (!resetCodeTimerValue) {
            C("#reset_button").el.disabled = false;
            //sendSmsConfirmationCode();
            hide("#reset_confirmation_time");
            if (resetCodeTimer)
                clearInterval(resetCodeTimer);
        }
    }, 1000);
}

async function checkResetConfirmationCode() {
    let resPhoneEl = C("#reset-phone"),
        resConfCodeEl = C("#reset_confirmation_code"),
        resPhonePopEl = C("#reset-phone-popup"),
        resConfButEl = C("#reset_confirmation_button");

    if (resPhoneEl.val().length < 16) {
        resPhoneEl.el.scrollIntoView();
        resPhoneEl.addclass("fail");
        resPhoneEl.el.focus();
        resPhonePopEl.togclass("show");
        return;
    }

    if (resConfCodeEl.val().length < 4) {
        resConfCodeEl.el.scrollIntoView();
        resConfCodeEl.addclass("fail");
        resConfCodeEl.el.focus();
        return;
    }

    resConfButEl.el.disabled = true;

    let result = await api("checkResetConfirmationCode", {
        phone: resPhoneEl.val(),
        code: resConfCodeEl.val()
    });

    resConfButEl.el.disabled = false;

    if (result.status) {
        C().setStor(LS_SECTION, "wallet");
        C().setStor(LS_TOKEN, result.data.token);

        location.reload();
    } else {
        showPopup("Внимание", result.description, null, null, () => {
            resConfCodeEl.val("");
            resConfCodeEl.el.focus();
            C("#reset_confirmation_button").el.disabled = true;
        });
    }
}

async function getReferLink() {
    return await api("getReferLink");
}

async function getResetConfirmationSms() {
    const resPhoneEl = C("#reset-phone-mask"),
        resButtonEl = C("#reset_button").el,
        resConfInfoEl = C("#reset_confirmation_info");

    if (resPhoneEl.val()) {
        resButtonEl.disabled = true;

        let result = await api("getResetConfirmationSms", {
            "phone": resPhoneEl.val()
        });

        if (result.status) {
            show("#reset_confirmation");
            resConfInfoEl.text(result.description);
            if (result.data.seconds_left) {
                restartResetConfirmationTimer(result.data.seconds_left);
            }
        } else {
            resButtonEl.disabled = false;
            promiseTimeout(() => {
                showPopup("Внимание", result.description);
            }, 1000);
            //showToast(result.description);
        }
    }
}

function attentionFocus(el) {
    el.scrollIntoView();
    el.classList.add('fail');
    el.focus();
    C(`#${el.id}-popup`).togclass('show');
}

function deleteAccount() {
    showPopup(
        '',
        '',
        'Вы уверены, что хотите удалить свою учетную запись?<p><small>Если да, то все ваши привилегии исчезнут.</small></p>',
        ['Да, уверен', 'Нет, отменить'],
        apiDeleteAccount,
    );
}

async function apiDeleteAccount() {
    const result = await api('deleteAccount');

    if (result.status) {
        clearLocalStorage();
        location.reload();
    }

    return result;
}

async function logOff() {
    showLoader();

    let result = await api("logOff");

    if (result.status) {
        clearLocalStorage();
        location.reload();
    }

    return result;
}

const clearLocalStorage = () => {
    const name = C().getStor('reg_name');
    localStorage.clear();

    if (name) {
        C().setStor('reg_name', name)
    }
}

const showRequestSms = () => {
    showPopup("Вы не получили код?",
        "",
        "Попробуйте получить код подтверждения с помощью СМС",
        ["Отправить код", "Попробую позже"],
        getResetConfirmationSms);
}

function showTerms() {
    loadRules(TERMS_URL);
}

function showRules() {
    loadRules(RULES_URL);
}

function showRefRules() {
    loadRules(REF_RULES_URL);
}

async function loadRules(url) {
    show('#terms');
    C('body').addclass('hideOverflow');
    const response = await fetch(url);

    if (response.ok) {
        const html = await response.text();
        const regexp = /<body[^>]*>([\s\S]*?)<\/body>/;
        const body = regexp.exec(html);

        C('#terms .terms__content').el.innerHTML = body[1];
    } else {
        closeTerms();
    }
}

function closeTerms() {
    C('#terms .terms__content').el.innerHTML = '';
    hide('#terms');
    C('body').delclass('hideOverflow');
    C('#accept_rules').el.style.boxShadow = 'none';
    C('#accept_rules').el.checked = true;
}

function showIndicator() {
    show("#top-nav-indicator");
}

function hideIndicator() {
    hide("#top-nav-indicator");
}

function hideFeedback() {
    hide("#feedback");
    d.body.classList.remove("hideOverflow");
}

function showInputPopup(id) {
    const et = C(`#${id}`);

    et.el.scrollIntoView();
    et.addclass("fail");
    et.el.focus();

    C(`#${id}-popup`).addclass('show');
}

async function setFeedback() {
    const phone = getPhoneNumbers(C("#feedback-phone-mask").val()),
        message = C("#feedback-message").val(),
        submitBut = C("#feedback-submit").el;

    if (phone.length !== 11) {
        showInputPopup("feedback-phone-mask");
        return;
    }

    if (message.length < 3) {
        showInputPopup("feedback-message");
        return;
    }

    submitBut.disabled = true;
    showLoader();

    let result = await api("setFeedback", {
        phone,
        name: C("#feedback-name").val(),
        email: C("#feedback-email").val(),
        reason: C("#feedback-reason").val(),
        message: `${C("#feedback-message").val()} (${clientInfo})`
    });

    if (result.status) {
        showPopup("Готово", "Ваше сообщение передано оператору");
        //showToast("Ваше сообщение передано оператору");
        hideFeedback();
        C("#feedback-message").val("");
    } else {
        onErrorCatch(result);
    }

    submitBut.disabled = false;
    hideLoader();
}

function onErrorCatch(error) {
    showPopup("Внимание", error.description);
    //showToast(error.description);
    console.warn(error);
}

function openFaq() {
    temp = `<h4><center>Частые вопросы</center></h4>
        <div class="close-positions"><i class="icon-cancel" onClick="closePositions()"></i></div>
        <ul class="faq">
            <li onClick="this.classList.toggle('active')">
                Как накопить бонусы?
                <div>
                    <p>Бонусы начисляются при совершении покупок в магазине "Мир посуды", с использованием пластиковой или виртуальной карты программы лояльности. Начисление бонусов за покупки товаров, участвующих в программе лояльности, происходит исходя из стоимости товара, оплаченной рублями. На часть стоимости товара, оплаченную бонусами, вознаграждение не начисляется. Максимальный размер бонусов, который может быть начислен при покупке конкретного товара, указан на ценнике. Процент начисления бонусов составляет до 15% от стоимости товара.</p>
                </div>
            </li>
            <li onClick="this.classList.toggle('active')">Как потратить бонусы?
                <div><p>Бонусами возможна частичная оплата товара. На каждом ценнике указано количество бонусов, которыми можно оплатить товар.<br>
                        Для списания и начисления бонусов, необходимо при совершении покупки на кассе предъявить кассиру QR код, расположенный на пластиковой либо виртуальной карте.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Какие бонусы бывают?
                <div>
                   <p>Бонусы бывают трех видов:</p>
                    <ul>
                        <li>Приветственные - 2000 бонусов дарим при регистрации.</li>
                        <li>Базовые бонусы – бонусы, начисленные за покупки.</li>
                        <li>Подарочные - бонусы на День рождения - начисляем за 7 дней до Дня рождения.</li>
                    </ul>
                </div>
            </li>
            <li onClick="this.classList.toggle('active')">Сколько стоит один бонус?
                <div><p>Стоимость одного бонуса равна одному рублю.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Как пользоваться приложением?
                <div><p>Для использования приложения необходимо быть зарегистрированным участником бонусной программы.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Зачем мне приложение?
                <div><p>В приложении удобно отслеживать актуальный баланс бонусных баллов и акции, проводимые в магазине "Мир посуды".</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">У меня пропали бонусы
                <div><p>Возможно, Ваши бонусы сгорели. У бонусов есть срок действия, а именно:</p>
                    <ul>
                        <li>Приветственные бонусы (2000 бонусов) - срок действия 90 дней с момента начисления.</li>
                        <li>Базовые бонусы до 10% от суммы, оплаченной рублями - срок действия 90 дней с момента начисления.</li>
                        <li>Бонусы в честь Дня рождения (1000 бонусов) - срок действия составляет семь дней до, в день Рождения, и семь дней после Дня рождения, после чего такие бонусы сгорают без права восстановления.</li>
                    </ul>
                    <p>Если Вы считаете, что бонусы не должны были сгореть, проверьте детализацию операций в приложении.</p>
                </div>
            </li>
            <li onClick="this.classList.toggle('active')">Как восстановить пароль?
                <div><p>Для восстановления пароля, перейдите к форме восстановления пароля, введите Ваш номер телефона и нажмите кнопку "забыли пароль", после этого нажмите "получить код для входа".</p><p>На указанный Вами номер телефона поступит звонок, после чего в поле для ввода нужно ввести 4 последние цифры номера телефона входящего звонка. Если звонок не поступает, у Вас есть возможность запросить смс в форме восстановления пароля. В смс Вам придет четырехзначный код - его необходимо ввести в поле для ввода.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Какой срок действия у бонусов?
                <div>
                    <ul>
                        <li>Приветственные 2000 бонусов - срок действия 90 дней с момента начисления.</li>
                        <li>Базовые бонусы до 10% от суммы, оплаченной рублями* - срок действия 90 дней с момента начисления.</li>
                        <li>Бонусы в честь Дня рождения (1000 бонусов) - срок действия составляет семь дней до, в день Рождения, и семь дней после Дня рождения, после чего такие бонусы сгорают без права восстановления.</li>
                    </ul>
                </div>
            </li>
            <li onClick="this.classList.toggle('active')">Можно ли оплатить 100% покупки бонусами?
                <div><p>Бонусами возможна только частичная оплата товара. На ценнике указано количество бонусов, которыми можно оплатить товар, исходя из того, что один бонус равен одному рублю.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Как изменить номер телефона, указанный в личном кабинете программы лояльности?	
                <div><p>Необходимо обратиться на горячую линию, озвучить новый номер, после чего оператор изменит номер телефона в программе лояльности.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">При восстановлении пароля мне не поступает звонок/смс, что делать?
                <div><p>Обратитесь на горячую линию, либо напишите запрос в форму обратной связи в личном кабинете Приложения программы лояльности.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Как поменять адрес e-mail, указанный в личном кабинете программы лояльности?
                <div><p>Обратитесь на горячую линию, либо напишите в форму обратной связи в личном кабинете Приложения программы лояльности данные, которые необходимо поменять.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Почему бонусы не списываются/не начисляются?
                <div><p>Возможно, Вы приобретаете товары, не участвующие в бонусной программе. К таким товарам относятся:</p>
                     <ul>
                        <li>Товар, которые реализуется по установленной Правительством РФ минимальной розничной цене (при покупке такого товара не списываются бонусы).</li>
                        <li>Гигиенические маски.</li>
                        <li>Пакеты-майки.</li>
                        <li>Любой товар, на ценнике к которому не указан процент списания и начисления бонусов.</li>
                    </ul>
                </div>
            </li>
            <li onClick="this.classList.toggle('active')">Как изменить дату рождения, указанную в личном кабинете программы лояльности?
                <div><p>Вам необходимо обратиться на горячую линию по номеру 8(4212)451-300, либо написать запрос в форму обратной связи программы лояльности.</p></div>
            </li>
            <li onClick="this.classList.toggle('active')">Что означает цвет ценника?	
                <div><ul><li>Белый ценник – возможна оплата товара бонусами от 5% до 30% стоимости товара, и начисление кэшбека до 10% бонусами.</li><li>Желтый ценник – возможна оплата товара бонусами до 50% стоимости товара, и начисление кэшбека до 10% бонусами.</li><li>Зеленый ценник – цена товара на таком ценнике указана с учетом всех скидок, поэтому скидка по бонусной карте не предоставляется, но при покупке такого товара начисляется кэшбек до 10% бонусами.</li></ul></div>
            </li>
        </ul>
        <center><button onClick="closePositions()">Закрыть</button></center>`;

    fillOverlay(temp);
}


function setNeedUpdate(contents, result, section) {
    const needUp = JSON.parse(C().getStor(LS_NEED_UPDATE));

    if (contents[section] !== result.data[section]) {
        needUp[section] = 1;
    }

    C().setStor(LS_NEED_UPDATE, JSON.stringify(needUp));
}

async function checkUpdates(callback) {
    userActivityTimeout = null;
    
    if (!bearerToken && callback) callback();

    if (!bearerToken) return;

    const marcus = await api("updateWalletData");

    const result = await getUpdates();
    const { data, status } = result;
    
    if (viewNewApp && versionApp && platform) {
        fetch(`${VERSION_URL}?platform=${platform}`).then(r => r.text()).then(t => {
            if (Number(t) > Number(versionApp)) {
                showPopup("Внимание", "Вышла новая версия, пожалуйста, обновите приложение!", "", ["Обновить", "link:Напомнить позже"], linkToApp);
            }

            viewNewApp = null;
        });
    }

    const curSection = C().getStor(LS_SECTION),
          updates    = !isEmpty(C().getStor(LS_CURR_UPDATE)) ? JSON.parse(C().getStor(LS_CURR_UPDATE)) : tempUpdate;
    let contents = !isEmpty(C().getStor(LS_CONTENTS)) ? JSON.parse(C().getStor(LS_CONTENTS)) : { "personal": "", "wallet": "" };

    if (status) {
        if (data.news.length) {
            updates.newsHash = data.newsHash;
            drawNews(data.news);
        }

        if (data.serverVersion) {
            C().setStor("versions", JSON.stringify(data.serverVersion));
        }

        if (data.notifyHash) {
            updates.notifyHash = data.notifyHash;
            drawNotifications(data.notifications);
        }

        if (data.personalHash) {
            setNeedUpdate(contents, result, 'personal');
            contents.personal = data.personal;
            updates.personalHash = data.personalHash;

            let userName = `${data.personal.firstname} ${data.personal.middlename}`;
            C("#feedback-name").val((userName ? userName : ""));
        }

        if (data.walletHash) {
            setNeedUpdate(contents, result, 'wallet');
            contents.wallet = data.wallet;
            updates.walletHash = data.walletHash;
        }

        if (data.lastPurchase) {
            updates.lastPurchase = data.lastPurchase;
            drawPurchases(data.purchases, data.transactions);
        }

        if (data.transactions.length) {
            updates.lastTransaction = data.transactions[data.transactions.length - 1].date;
            if (!data.lastPurchase) {
                drawPurchases([], data.transactions);
            }
        }

        // Всех авторизованных отправляем на страницу кошелька
        if (sections[curSection] && !sections[curSection].needAuth) {
            C().setStor(LS_SECTION, "wallet");
        }

        C().setStor(LS_CURR_UPDATE, JSON.stringify(updates));
        C().setStor(LS_CONTENTS, JSON.stringify(contents));

        renderSections();
    } else {
        // Не авторизованных отправляем на авторизацию
        if (sections[curSection] && sections[curSection].needAuth) {
            logOff();
        }
    }
}

async function getUpdates() {
    let data = !isEmpty(C().getStor(LS_CURR_UPDATE)) ? JSON.parse(C().getStor(LS_CURR_UPDATE)) : tempUpdate;
    const contents = !isEmpty(C().getStor(LS_CONTENTS)) ? JSON.parse(C().getStor(LS_CONTENTS)) : { "personal": "", "wallet": "" };

    if (contents.personal === "") {
        data = tempUpdate;
    }

    if (initApp) {
        data.newsHash = "";
        data.storesHash = "";
        data.notifyHash = "";
        data.lastPurchase = "";
        data.lastTransaction = "";
        initApp = false;
    }

    data.pushId = C().getStor(LS_PUSHID);

    if (clientDevice) {
        data.clientDevice = clientDevice;
    }

    return await api("getUpdates", data);
}

function drawNotifications(notifiesList) {

    if (!notifiesList) {
        return false;
    }

    const container = C(".notifications");
    container.html("");

    notifiesList.forEach((notify) => {
        const clas = notify.is_unread ? " class = 'unread'" : "";
        const title = notify.title ? `${notify.title}<br>` : "";
        const temp = `<li${clas} onclick="clickNotify(${notify.id})" data-id="${notify.id}">${title}${notify.description}</li>`;
        const newsContEl = C().strToNode(temp);

        container.el.prepend(newsContEl.el);
        if (notify.is_unread) {
            newNotifies = true;
        }
    });

    const icon = C(".icon-bell.topNav__msg");

    if (newNotifies) {
        icon.addclass("unread");
    } else {
        icon.delclass("unread");
    }
}

async function clickNotify(id) {
    C(`[data-id="${id}"]`).delclass("unread");

    await api("readNotificaton", {
        id
    });
    checkUnreadNotify();
}

function checkUnreadNotify() {
    const containers = C(".notifications li").els;
    let yep = false;

    containers.forEach((li) => {
        if (li.classList.contains("unread")) {
            yep = true;
        }
    });

    newNotifies = yep;
    const icon = C(".icon-bell.topNav__msg");

    if (yep) {
        icon.addclass("unread");
    } else {
        icon.delclass("unread");
    }
}