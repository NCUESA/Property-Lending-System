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
    $('#lending_status').on('change', getStatusData);
    $('#location').on('change', getStatusData);
});

function getStatusData() {
    var placeValue = $('#location').val();
    var lendingValue = $('#lending_status').val();
    $.ajax({
        type: 'POST',
        url: '/show-item-status',
        data: {
            place: placeValue,
            finding_status: lendingValue,
            _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            console.log(response);
            if (response.success) {
                genTable(response.data);
            }

        },
        error: function (error) {
            console.log(error);
        }
    });
}

function genTable(data) {
    $('#property_status').empty();
    $.each(data, function (index, item) {
        //console.log(item);
        let lending_status = '';

        if (item.lending_status == '1') {
            lending_status = '❌';
        }
        else if (item.lending_status == '2') {
            lending_status = '🔼';
        }
        else {
            lending_status = '✅';
        }


        var row = '<tr>' +
            '<td>' + item.ssid + '</td>' +
            '<td>' + item.class + '</td>' +
            '<td>' + item.name + '</td>' +
            '<td>' + item.second_name + '</td>' +
            '<td>' + item.format + '</td>' +

            '<td>' + item.borrow_department + '</td>' +
            '<td>' + item.borrow_date + '</td>' +
            '<td>' + item.returned_date + '</td>' +
            '<td>' + lending_status + '</td>' +
            '<td>' + '<img src="./storage/propertyImgs/' + item.img_url + '" alt="無圖片" style="width: 100px; height: auto;"></img>' + '</td>' +
            '</tr>';
        // 將生成的行添加到表格的 tbody 中
        $('#property_status').append(row);
    });
}


