$(document).ready(function () {
    $('button#adjust').on("click", function (e) {
        e.preventDefault();
        if ($('#add_person').val() == '') {
            return;
        }
        if ($('#add_stu_id').val() == '') {
            return;
        }
        if (!$('input[name="status"]:checked').val()) {
            return;
        }
        if (!$('input[name="level"]:checked').val()) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: '/add-user',
            data: {
                stu_id: $('#add_stu_id').val(),
                name: $('#add_person').val(),
                level: $('input[name="level"]:checked').val(),
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

    $('button#delete').on("click", function (e) {
        e.preventDefault();
        if(confirm('確定刪除？')){
            $.ajax({
                type: 'POST',
                url: '/delete-user',
                data: {
                    stu_id: $('#add_stu_id').val(),
                    name: $('#add_person').val(),
                    level: $('input[name="level"]:checked').val(),
                    status: $('input[name="status"]:checked').val(),
                    _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {
                        alert('刪除成功!');
                        location.reload();
                    }
    
                },
                error: function (error) {
                    console.log(error);
                }
            });
        }
        
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
        const stu_id = currentRow.find('td').eq(0).text();  // 第一個 <td> 的內容
        const name = currentRow.find('td').eq(1).text();  // 第2個 <td> 的內容
        const status = currentRow.find('td').eq(3).text(); // 第3個 <td> 的內容
        const status_radio_val = status == 'DOWN' ? 'd' : status == 'UP' ? 'u' : 'N/A';

        const level = currentRow.find('td').eq(2).text(); // 第4個 <td> 的內容
        const level_radio_val = level == 'Admin' ? 'admin' : level == 'Normal' ? 'normal' : level == 'Muggle' ? 'muggle': 'N/A';


        // 將第一個 <td> 的內容填入 input #inputA
        $('#add_stu_id').val(stu_id);
        $('#add_person').val(name);
        //console.log(status);
        // 根據第二個 <td> 的內容選擇對應的 radio 按鈕
        $(`input[name="status"][value="${status_radio_val}"]`).prop('checked', true);
        $(`input[name="level"][value="${level_radio_val}"]`).prop('checked', true);
    });
});



function genTable(data) {
    $('#people_status').empty();
    $.each(data, function (index, item) {
        console.log(item);
        const status_radio_val = item.status == '0' ? 'DOWN' : item.status == '1' ? 'UP' : 'N/A';
        const level_radio_val = item.auth_level == 'admin' ? 'Admin' : item.auth_level == 'normal' ? 'Normal' : item.auth_level == 'muggle' ? "Muggle" : 'N/A';
        const level_bg_color = item.auth_level == 'admin' ? 'table-secondary' : item.auth_level == 'normal' ? 'table-success' : item.auth_level == 'muggle' ? "table-primary" : 'table-danger';
        
        var row = '<tr class="' + level_bg_color + '">' +
            '<td>' + item.stu_id + '</td>' +
            '<td>' + item.name + '</td>' +
            '<td>' + level_radio_val + '</td>' +
            '<td>' + status_radio_val + '</td>' +

            '<td>' + '<button type="button" class="data-move btn btn-outline-info">異動</button>' + '</td>' +  // Img Column
            '</tr>';
        // 將生成的行添加到表格的 tbody 中
        $('#people_status').append(row);
    });
}