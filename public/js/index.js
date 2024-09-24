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

    $('#borrow').on('submit', function (e) {
        e.preventDefault();
        // The logic of precheck
        $('.form-control, .form-check-input').removeClass('is-invalid');
        $('#check_know_filling').text('');
        let isValid = true;
        if (!$('input[name="know_filling"]:checked').val()) {
            $('#check_department').addClass('invalid');
            $('#check_know_filling').text('要填');
            isValid = false;
        }
        if ($('input[name="know_filling"]:checked').val() == 'n') {
            $('#check_department').addClass('invalid');
            $('#check_know_filling').text('還敢亂填啊');
            isValid = false;
        }

        if (!$('input[name="borrow_place"]:checked').val()) {
            $('#check_department').addClass('invalid');
            $('#check_borrow_place').text('要填');
            isValid = false;
        }

        if ($('#department').val().trim() === '') {
            $('#check_department').addClass('invalid-feedback');
            $('#check_department').text('要填');
            isValid = false;
        }

        if ($('#contact_person').val().trim() === '') {
            $('#check_contact_person').addClass('invalid-feedback');
            $('#check_contact_person').text('要填');
            isValid = false;
        }

        const phoneRegex = /^[0-9]{10,15}$/;
        if (!phoneRegex.test($('#phone').val().trim())) {
            $('#check_phone').addClass('invalid-feedback');
            $('#check_phone').text('要填');
            isValid = false;
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

        if (!$('#borrow_date').val()) {
            $('#check_borrow_date').addClass('invalid-feedback');
            isValid = false;
            $('#check_borrow_date').text('要填');
        }
        if (!$('#return_date').val()) {
            $('#check_return_date').addClass('invalid-feedback');
            isValid = false;
            $('#check_return_date').text('要填');
        }

        var borrow_items = collectBorrowItems();

        if (borrow_items.length == 0) {
            isValid = false;
        }
        if (!isValid) {
            return;
        }
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

        console.log(pack_data);

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
        if(item.remark == null){
            item.remark = '';
        }
        if(item.format == null){
            item.format = '';
        }
        if(item.second_name == null){
            item.second_name = '';
        }
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