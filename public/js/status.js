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
        let lending_color = '';
        let borrowSvg = '';
        item.borrow_department = item.borrow_department || '';
        item.borrow_date = item.borrow_date || '';
        item.returned_date = item.returned_date || '';
        item.remark = item.remark || '';
        item.format = item.format || '';
        item.second_name = item.second_name || '';

        if (item.lending_status == '1') {
            lending_color = 'danger';
            lending_status = '已被借用';
            borrowSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg>`;
        }
        else if (item.lending_status == '2') {
            lending_color = 'secondary';
            lending_status = '待借出';
            borrowSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4m.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/>
</svg>`;
        }
        else {
            lending_color = 'success';
            lending_status = '可借';
            borrowSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/>
</svg>`;
        }

        const borrowBadge = lending_status !== '可借'
            ? `<span class="badge bg-secondary">借用單位：${item.borrow_department}</span>
        <span class="badge bg-secondary">期間：${item.borrow_date}~${item.returned_date}</span>`
            : '';

        let row = `
            <div class="col">
                <div class="card border-${lending_color}">
                    <div class="card-header text-white bg-${lending_color}">${borrowSvg}${lending_status}</div>
                    <input type="hidden" value="${item.ssid}">
                    <img src="./storage/propertyImgs/${item.img_url}" style="width: 100%; aspect-ratio: 4 / 3; object-fit: contain;" class="card-img-top" alt="No Image" 
                         onerror="this.src='https://dummyimage.com/1440x1080/cccccc/000000&text=No+Image';">
                    <div class="card-body">
                        <label for="${item.ssid}">${item.name} ${item.second_name}</label>
                        <p><span class="badge bg-primary">${item.class}</span></p>
                        ${borrowBadge}
                        <p class="card-text">
                            財產編號：${item.ssid}<br>
                            規格：${item.format || '無'}<br>
                            ${item.remark ? `備註：${item.remark}` : ''}
                        </p>
                    </div>
                    <div class="card-footer text-muted">
                        地點：${item.belong_place || '未知'}
                    </div>
                </div>
            </div>`;
        // 將生成的行添加到表格的 tbody 中
        $('#property_status').append(row);
    });
}


