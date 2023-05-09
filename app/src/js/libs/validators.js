function isValidDate(dateString) {
    if (!/^\d{2}-\d{2}-\d{4}$/.test(dateString)) {
        return false;
    }

    const parts = dateString.split('-'),
          day   = parseInt(parts[0], 10),
          month = parseInt(parts[1], 10),
          year  = parseInt(parts[2], 10);
  
    let monthLength = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];

    if (year < 1000 || year > 3000 || month === 0 || month > 12) {
        return false;
    }
    
    if (year % 400 === 0 || (year % 100 !== 0 && year % 4 === 0)) {
        monthLength[1] = 29;
    }

    return day > 0 && day <= monthLength[month - 1];
}

function validateBirthdate(el, isSubmit) {
    if (!isSubmit) {
        isSubmit = false;
    }

    el.value = el.value.replace(/\D/g, '').replace(/^(\d{2})(\d)/, '$1-$2').replace(/-(\d{2})(\d)/, '-$1-$2').replace(/(\d{4})\d+/, '$1');

    if (el.value.length > 9) {
        let val = el.value,
            td  = val.split('-'),
            bd  = new Date(td[2], --td[1], td[0]),
            cd  = new Date(),
            age = (cd - bd);

        if (age < 568036800000 || age > 3155760000000 || bd == 'Invalid Date' || !isValidDate(val)) {
            showInputPopup('reg-birthdate');
        } else {
            return true;
        }
    } else if (isSubmit) {
        showInputPopup('"reg-birthdate');
    }

    return false;
}