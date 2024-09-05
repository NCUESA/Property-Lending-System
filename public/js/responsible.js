$(document).ready(function () {
    $('#add-person').on("submit", function (e) {
        e.preventDefault();
        if ($('#add_person').val() == '') {
            return;
        }
        if (!$('input[name="status"]:checked').val()) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: '/add-user',
            data: {
                name: $('#add_person').val(),
                status: $('input[name="status"]:checked').val(),
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                console.log(response);
                if (response.success) {
                    alert('新增成功!');
                    location.reload();
                }

            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $.ajax({
        type: 'POST',
        url: '/show-user',
        data: {
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

    $(document).on('click', '.data-move', function () {
        // 取得當前按鈕所在的資料列
        const currentRow = $(this).closest('tr');
    
        // 提取每個 td 的內容
        const name = currentRow.find('td').eq(0).text();  // 第一個 <td> 的內容
        const status = currentRow.find('td').eq(1).text(); // 第二個 <td> 的內容
        const radio_val = status == 'DOWN'? 'd': status == 'UP'? 'u': 'N/A';
        // 將第一個 <td> 的內容填入 input #inputA
        $('#add_person').val(name);
        console.log(status);
        // 根據第二個 <td> 的內容選擇對應的 radio 按鈕
        $(`input[name="status"][value="${radio_val}"]`).prop('checked', true);
    });
});



function genTable(data) {
    $('#people_status').empty();
    $.each(data, function (index, item) {
        console.log(item);
        const radio_val = item.status == '0'? 'DOWN': item.status == '1'? 'UP': 'N/A';
        var row = '<tr>' +
            
            '<td>' + item.name + '</td>' +
            '<td>' + radio_val + '</td>' +
            
            '<td>' + '<button type="button" class="data-move btn btn-outline-info">異動</button>'+ '</td>' +  // Img Column
            '</tr>';
        // 將生成的行添加到表格的 tbody 中
        $('#people_status').append(row);
    });
}