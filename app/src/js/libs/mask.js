const mask = inp => {
    let underlay = C().create('input').el,
        attr = {};

    attr.id = inp.id.replace('-mask', '');
    attr.disabled = 'disabled';
    attr.type = inp.getAttribute('type');

    for (let key in attr) {
        underlay.setAttribute(key, attr[key]);
    }

    inp.parentNode.insertBefore(underlay, inp);
    setPhoneMask(inp, false);
    C(inp).bind('click', () => { inp.selectionStart = inp.value.length; });
    C(inp).bind('input', (e) => setPhoneMask(e.target));
}

const setPhoneMask = (inp, mask) => {
    const hideId = `#${inp.id.replace('-mask', '')}`;
    let phone = inp.value.replace(/\D/g, '');

    if (phone === '') {
        phone = '7';
    }

    if (!mask) {
        mask = '+_(___)___-__-__';
    }

    phone = getPhoneNumbers(phone);

    C(inp).val(getValueByMask(phone, mask));
    C(hideId).val(getValueByMask(phone, mask, true));
}

const getPhoneNumbers = phone => {
    return phone ? phone.replace(/^([^7])/, '7$1').replace(/^(\d{11})(.+)/, '$1') : '7';
}

const getValueByMask = (value, mask, full) => {
    const phone = value.match(/\d/g);
    let newPhone = mask;

    phone.map((e) => newPhone = newPhone.replace(/_/, e));

    if (!full) {
        newPhone = newPhone.replace(/\)_|-_|_/g, '');
    }

    return newPhone;
}