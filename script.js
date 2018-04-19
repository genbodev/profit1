(function () {

    var quantityPart = 1000;
    var submit = document.getElementById('submit');

    submit.onclick = function (e) {
        e.preventDefault();
        submit.setAttribute('disabled', 'disabled');
        var min = document.getElementById('min').value;
        var max = document.getElementById('max').value;
        var quantity = document.getElementById('quantity').value;
        prepareAndSend(min, max, quantity);
    };

    function prepareAndSend(min, max, quantity) {
        var parts = Math.round(quantity / quantityPart);
        if (quantity % quantityPart !== 0) {
            parts = parts + 1;
        }
        if (quantity < quantityPart) {
            quantityPart = quantity;
        }
        send(min, max, quantity, quantityPart, 1, parts);
    }

    function send(min, max, quantity, quantityPart, currPart, parts) {
        var formData = new FormData();
        formData.append('min', min);
        formData.append('max', max);
        formData.append('quantity', quantity);
        formData.append('quantity_part', quantityPart);
        formData.append('curr_part', currPart);
        formData.append('parts', parts);
        var xmlHttp;
        if (window.XMLHttpRequest) {
            xmlHttp = new XMLHttpRequest();
        } else {
            xmlHttp = new ActiveXObject('Microsoft.XMLHTTP');
        }
        xmlHttp.onreadystatechange = function () {
            if (this.readyState === 4 && this.status === 200) {
                var answer = JSON.parse(this.responseText);
                console.log(answer);
                currPart = currPart + 1;
                if (currPart === parts) {
                    quantityPart = quantity - (quantityPart * (parts - 1))
                }
                if (currPart <= parts) {
                    send(min, max, quantity, quantityPart, currPart, parts);
                } else {
                    submit.removeAttribute('disabled');
                }
            }
        };
        xmlHttp.open('POST', 'ajax.php', true);
        xmlHttp.send(formData);
    }

})();