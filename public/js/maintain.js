var all_property_data = {};

$(document).ready(function () {

    /*$.ajax({
        type: 'POST',
        url: '/get-property-info',
        data: {
            selected: 'all',
            _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            //console.log(response);
            if (response.success) {
                genTable(response.data);
            }

        },
        error: function (error) {
            console.log(error);
        }
    });*/

    $('#place').on('change', function () {
        var selectedValue = $(this).val();
        $.ajax({
            type: 'POST',
            url: '/get-property-info',
            data: {
                selected: selectedValue,
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                //console.log(response);
                if (response.success) {
                    genTable(response.data);
                    addBarCode();
                }

            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $(document).on('click', '.btn-prop-info', function () {
        let property_data = all_property_data[$(this).data('property')];
        console.log(all_property_data[$(this).data('property')]);
        bringDataIntoModal(property_data);
    });

    $('#save-data').on('click', function (e) {
        e.preventDefault();
        if (confirm('確認資料都已正確輸入?') == true) {
            sendData();
        }
    });
});


function bringDataIntoModal(property) {
    // Except Handle
    let belong_place = '';
    if (property['belong_place'] == '進德') {
        belong_place = 'jinde';
    }
    else if (property['belong_place'] == '寶山') {
        belong_place = 'baosan';
    }
    else if (property['belong_place'] == '307') {
        belong_place = '307';
    }
    else if (property['belong_place'] == '405') {
        belong_place = '405';
    }

    // Row 1
    $('#ssid').val(property['ssid']);
    $('#class').val(property['class']);
    $('#school_property').val(property['school_property']);
    $('#get_day').val(property['get_day']);

    // Row 2
    $('#name').val(property['name']);
    $('#second_name').val(property['second_name']);

    // Row 3
    $('#enable_lending').val(property['enable_lending']);
    $('#belong_place').val(belong_place);
    $('#department').val(property['department']);
    $('#depositary').val(property['depositary']);

    // Row 4
    $('#price').val(property['price']);
    $('#order_number').val(property['order_number']);
    $('#primary_key').val(property['primary_key']);
    //$('#prop_img').val(property['prop_img']);

    // Row 5 TextArea
    $('#format').val(property['format']);
    $('#remark').val(property['remark']);

    // Row 6 Img
    $('#img_url').attr("src", './storage/propertyImgs/' + property['img_url']);
}

function sendData() {
    let belong_place = '';
    if ($('#belong_place').val() == 'jinde') {
        belong_place = '進德';
    } else if ($('#belong_place').val() == 'baosan') {
        belong_place = '寶山';
    } else {
        belong_place = $('#belong_place').val();
    }

    // Create a FormData object to include form inputs and the image
    let formData = new FormData();

    // Append form fields to the FormData object
    formData.append('ssid', $('#ssid').val());
    formData.append('class', $('#class').val());
    formData.append('school_property', $('#school_property').val());
    formData.append('get_day', $('#get_day').val());
    formData.append('name', $('#name').val());
    formData.append('second_name', $('#second_name').val());
    formData.append('enable_lending', $('#enable_lending').val());
    formData.append('belong_place', belong_place);
    formData.append('department', $('#department').val());
    formData.append('depositary', $('#depositary').val());
    formData.append('price', $('#price').val());
    formData.append('order_number', $('#order_number').val());
    formData.append('primary_key', $('#primary_key').val()); //ID
    formData.append('format', $('#format').val());
    formData.append('remark', $('#remark').val());

    // Append the image file if it exists
    let imageFile = $('#prop_img')[0].files[0];
    if (imageFile) {
        formData.append('prop_img', imageFile);
    }

    // Send the AJAX request
    $.ajax({
        type: 'POST',
        url: '/update-property-info',
        data: formData,
        contentType: false,
        processData: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            if (response.success) {
                alert('修改(新增)完成');
                //console.log(response.text,response.fA);
                location.reload();
            }
        },
        error: function (error) {
            alert(error);
            console.log(error);
        }
    });
}

function genTable(data) {
    $('#property-table').empty();
    $.each(data, function (index, item) {
        //console.log(item);
        let lending_status = '不可外借';
        let bg_color = 'table-secondary';
        if (item.enable_lending == '1') {
            if (item.lending_status == '1') {
                lending_status = '外借中';
                bg_color = 'table-warning';
            }
            else if (item.lending_status == '2') {
                lending_status = '待借出';
                bg_color = 'table-primary';
            }
            else {
                lending_status = '在會辦';
                bg_color = 'table-success';
            }
        }
        let combine_data = {
            primary_key: item.id,
            ssid: item.ssid,
            class: item.class,
            name: item.name,
            second_name: item.second_name,
            school_property: item.school_property,
            enable_lending: item.enable_lending,
            order_number: item.order_number,
            price: item.price,
            department: item.department,
            depositary: item.depositary,
            belong_place: item.belong_place,
            get_day: item.get_day,
            format: item.format,
            remark: item.remark,
            img_url: item.img_url
        }
        all_property_data[item.ssid + '_prop'] = combine_data;

        let trig_btn = `<button type="button" class="btn btn-dark btn-prop-info" data-bs-toggle="modal" data-bs-target="#modal" data-property="${item.ssid}_prop">修改</button>`;
        var row = '<tr class="' + bg_color + '">' +
            '<td>' + item.ssid + '</td>' +
            '<td>' + item.class + '</td>' +
            '<td>' + item.name + '</td>' +
            '<td>' + item.second_name + '</td>' +
            '<td>' + 'N' + '</td>' +
            '<td>' + lending_status + '</td>' +
            '<td>' + item.order_number + '</td>' +
            '<td>' + item.price + '</td>' +
            '<td>' + item.department + '</td>' +
            '<td>' + item.depositary + '</td>' +
            '<td>' + item.belong_place + '</td>' +
            '<td>' + item.get_day + '</td>' +
            '<td>' + item.format + '</td>' +
            '<td>' + item.remark + '</td>' +
            '<td><img src="./storage/propertyImgs/' + item.img_url + '" alt="NO IMG" style="width: 100px; height: auto;"></td>' +
            '<td>' + trig_btn + '</td>' +
            '</tr>';
        // 將生成的行添加到表格的 tbody 中
        $('#property-table').append(row);
    });
}
function addBarCode() {
    $('tbody td[id]').each(function (indexInArray, valueOfElement) {
        //console.log("ID: " + $(this).attr('id') + ", Text: " + $(this).text());
        let word = $(this).text();
        let barcode = $('<p></p>').text(word);
        barcode.addClass('barcode');

        $(this).append(barcode);
    });
}