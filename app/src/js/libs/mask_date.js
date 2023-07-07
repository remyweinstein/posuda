/* global C */

function maskDate(inp) {
    let underlay = C().create('input').el,
        attr = {};

    attr.id = inp.id.replace('-date_mask', '');
    attr.disabled = 'disabled';
    attr.type = inp.getAttribute('type');

    for (let key in attr) {
        underlay.setAttribute(key, attr[key]);
    }

    inp.parentNode.insertBefore(underlay, inp);
    setDateMask(inp, false);
    C(inp).bind('click', () => { inp.selectionStart = inp.value.length; });
    C(inp).bind('input', (e) => setDateMask(e.target));
}

function setDateMask(inp, mask) {
    const hideId = `#${inp.id.replace('-date_mask', '')}`;
    let phone = inp.value;

    if (!mask) {
        mask = '__-__-____';
    }

    phone = getDateNumbers(phone);

    C(inp).val(getValueDateByMask(phone, mask));
    C(hideId).val(getValueDateByMask(phone, mask, true));
}

function getDateNumbers(value) {
    let phone = value.replace(/\D/g, '');

    if (phone) {
        //phone = phone.replace(/^([^7])/, '7$1').replace(/^(\d{11})(.+)/, '$1');
    } else {
        //phone = '7';
    }

    return phone;
}

function getValueDateByMask(value, mask, full) {
    if (!value) {
        return value;
    }
    const phone = value.match(/\d/g);
    let newPhone = mask;

    phone.map((e) => newPhone = newPhone.replace(/_/, e));

    if (!full) {
        newPhone = newPhone.replace(/\)_|-_|_/g, '');
    }

    return newPhone;
}
