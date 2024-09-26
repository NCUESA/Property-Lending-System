$(document).ready(function () {
    $('input[name="borrow_place"]').on('change', function () {
        var seleced_place = $(this).val();

        $.ajax({
            type: 'POST',
            url: '/show-borrowable-item',
            data: {
                place: seleced_place,
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                //console.log(response);
                if (response.success) {
                    genPropertyTable(response.data);
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $('#borrow').on('submit', function (event) {
        // The logic of precheck
        
        //$('.form-control, .form-check-input').removeClass('is-invalid');
        //
        let isValid = true;
        if(!this.checkValidity()){
            isValid = false;
        }

        if (!$('input[name="know_filling"]:checked').val()) {
            $('#check_know_filling').addClass('invalid-feedback');
            $('#check_know_filling').text('要填');
            isValid = false;
        }
        else if ($('input[name="know_filling"]:checked').val() == 'n') {
            $('#check_know_filling').addClass('invalid-feedback');
            $('#check_know_filling').text('還敢亂填啊');
            isValid = false;
        }
        else{
            $('#check_know_filling').text('');
            $('#check_know_filling').removeClass('invalid-feedback').addClass('valid-feedback');
        }

        if (!$('input[name="borrow_place"]:checked').val()) {
            $('#check_borrow_place').addClass('invalid-feedback');
            $('#check_borrow_place').text('要填');
            isValid = false;
        }
        else{
            $('#check_borrow_place').text('');
            $('#check_borrow_place').removeClass('invalid-feedback').addClass('valid-feedback');
        }

        if ($('#department').val().trim() === '') {
            $('#check_department').addClass('invalid-feedback');
            $('#check_department').text('要填');
            isValid = false;
        }
        else{
            $('#check_department').text('');
            $('#check_department').removeClass('invalid-feedback').addClass('valid-feedback');
        }
        

        if ($('#contact_person').val().trim() === '') {
            $('#check_contact_person').addClass('invalid-feedback');
            $('#check_contact_person').text('要填');
            isValid = false;
        }
        else{
            $('#check_contact_person').text('');
            $('#check_contact_person').removeClass('invalid-feedback').addClass('valid-feedback');
        }

        const phoneRegex = /^[0-9]{10,15}$/;
        if (!phoneRegex.test($('#phone').val().trim())) {
            $('#check_phone').addClass('invalid-feedback');
            $('#check_phone').text('要填');
            isValid = false;
        }
        else{
            $('#check_phone').text('');
            $('#check_phone').removeClass('invalid-feedback').addClass('valid-feedback');
        }

        if (!$('#email').val().trim()) {
            $('#check_email').addClass('invalid-feedback');
            isValid = false;
            $('#check_email').text('要填');
        } else if (!/\S+@\S+\.\S+/.test($('#email').val().trim())) {
            $('#check_email').addClass('invalid-feedback');
            isValid = false;
            $('#check_email').text('不要亂填');
        }
        else{
            $('#check_email').text('');
            $('#check_email').removeClass('invalid-feedback').addClass('valid-feedback');
        }

        if (!$('#borrow_date').val()) {
            $('#check_borrow_date').addClass('invalid-feedback');
            isValid = false;
            $('#check_borrow_date').text('要填');
        }
        else{
            $('#check_borrow_date').text('');
            $('#check_borrow_date').removeClass('invalid-feedback').addClass('valid-feedback');
        }

        if (!$('#return_date').val()) {
            $('#check_return_date').addClass('invalid-feedback');
            isValid = false;
            $('#check_return_date').text('要填');
        }
        else{
            $('#check_return_date').text('');
            $('#check_return_date').removeClass('invalid-feedback').addClass('valid-feedback');
        }

        var borrow_items = collectBorrowItems();
        if (borrow_items.length == 0) {
            //$('#check_borrow_item').addClass('invalid-feedback');
            $('#check_borrow_item').show();
            isValid = false;
        }
        else{
            $('#check_borrow_item').hide();
            //$('#check_borrow_item').removeClass('invalid-feedback').addClass('valid-feedback');
        }
        
        if (!isValid) {
            event.preventDefault();
            event.stopPropagation();
        }

        $(this).addClass('was-validated');

        
        // Send Ajax
        var pack_data = {
            'understand': $('input[name="know_filling"]').val(),
            'borrow_place': $('input[name="borrow_place"]').val(),
            'borrow_department': $('#department').val(),
            'borrow_person_name': $('#contact_person').val(),
            'phone': $('#phone').val(),
            'email': $('#email').val(),
            'borrow_date': $('#borrow_date').val(),
            'returned_date': $('#return_date').val(),
            'borrow_items': borrow_items
        };

        $.ajax({
            type: 'POST',
            url: '/borrow-items',
            data: {
                pack_data: JSON.stringify(pack_data),
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                console.log(response);
                if (response.success && response.error == '') {
                    alert('借用表單送出成功，請等待值勤人員提供器材');
                    location.reload();
                }
                else {
                    alert(response.error);
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

});

function genPropertyTable(data) {
    $('#borrowable_item').empty();
    $.each(data, function (index, item) {
        console.log(item);
        var row = '<tr>' +
            '<td>' + '<input type="checkbox" class="form-check-input" id="' + item.ssid + '"></input>' + '</td>' +
            '<td>' + item.ssid + '</td>' +
            '<td>' + item.class + '</td>' +
            '<td>' + item.name + '</td>' +
            '<td>' + item.second_name + '</td>' +

            '<td>' + item.belong_place + '</td>' +

            '<td>' + item.format + '</td>' +
            '<td>' + item.remark + '</td>' +
            '<td>' + '<img src="./storage/propertyImgs/' + item.img_url + '" alt="NO IMG" style="width: 200px; height: auto;"></img>' + '</td>' +  // Img Column
            '</tr>';
        // 將生成的行添加到表格的 tbody 中
        $('#borrowable_item').append(row);
    });
}

function collectBorrowItems() {
    var checkItems = [];
    $('#borrowable_item input[type="checkbox"]:checked').each(function () {
        // 獲取被勾選 checkbox 的 id
        checkItems.push($(this).attr('id'));

        //console.log(checkboxId); // 在這裡可以處理 id
    });

    return checkItems;
}