$(document).ready(function () {
    $('#add-ip').on("click", function (e) {
        e.preventDefault();
        if ($('#add_ip').val() == '') {
            return;
        }
        if ($('#level').val() == '') {
            return;
        }

        $.ajax({
            type: 'POST',
            url: '/ip/add',
            data: {
                id: $('#add_id').val(),
                ip: $('#add_ip').val(),
                description: $('#description').val(),
                level: $('#level').val(),
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                console.log(response);
                if (response.success) {
                    alert('新增(異動)成功!');
                    location.reload();
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });
    $('#delete-ip').on("click", function (e) {
        e.preventDefault();
        if ($('#add_ip').val() == '') {
            return;
        }

        $.ajax({
            type: 'POST',
            url: '/ip/delete',
            data: {
                id: $('#add_id').val(),
                ip: $('#add_ip').val(),
                description: $('#description').val(),
                level: $('#level').val(),
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
                alert(error);
            }
        });
    });

    $.ajax({
        type: 'POST',
        url: '/ip/show',
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
        const id = currentRow.find('td').eq(0).text(); 
        const ip = currentRow.find('td').eq(1).text();  // 第一個 <td> 的內容
        const level = currentRow.find('td').eq(2).text() == 'Admin' ? 10 : 5; // 第二個 <td> 的內容
        const description = currentRow.find('td').eq(3).text();
        
        // 將第一個 <td> 的內容填入 input #inputA
        $('#add_id').val(id);
        $('#add_ip').val(ip);
        $('#description').val(description);
        $('#level').val(level);
    });
});



function genTable(data) {
    $('#ip_table').empty();
    $.each(data, function (index, item) {
        console.log(item);
        const auth_level = item.auth_level == '5'? 'Normal': item.auth_level == '10'? 'Admin': 'N/A';
        var row = '<tr>' +
            '<td hidden>' + item.id + '</td>' +
            '<td>' + item.ip + '</td>' +
            '<td>' + auth_level + '</td>' +
            '<td>' + item.describe + '</td>' +

            
            '<td>' + '<button type="button" class="data-move btn btn-outline-secondary">異動</button>'+ '</td>' +  // Img Column
            '</tr>';
        // 將生成的行添加到表格的 tbody 中
        $('#ip_table').append(row);
    });
}