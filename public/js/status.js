$(document).ready(function () {

    getStatusData();
    $('input[name="location"]').on('change', getStatusData);
    $('input[name="lending_status"]').on('change', getStatusData);
});

function getStatusData() {
    var placeValue = $('input[name="location"]:checked').val();
    var lendingValue = $('input[name="lending_status"]:checked').val();
    $.ajax({
        type: 'POST',
        url: '/show-item-status',
        data: {
            place: placeValue,
            finding_status: lendingValue,
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
            borrowSvg = `<i class="bi bi-x-circle"></i>`;
        }
        else if (item.lending_status == '2') {
            lending_color = 'secondary';
            lending_status = '待借出';
            borrowSvg = `<i class="bi bi-exclamation-circle"></i>`;
        }
        else {
            lending_color = 'success';
            lending_status = '可借';
            borrowSvg = `<i class="bi bi-check-circle"></i>`;
        }

        const borrowBadge = `<span class="badge bg-dark" ${lending_status == '可借' ? 'hidden' : ''}>
            預定歸還：${item.returned_date}</span>
        `;

        let mobile_card = `
            <div class="col">
                <div class="card border-${lending_color}">
                    <div class="card-body">
                        <div class="d-flex position-relative hstack gap-3">
                            <div class="left-side flex-shrink-0" style="width: 40%;">
                                <div class="card-header text-white bg-${lending_color} text-center">${borrowSvg}${lending_status}</div>
                                <img src="./storage/propertyImgs/${item.img_url}" 
                                    class="card-img-top w-100"
                                    style="aspect-ratio: 1 / 1; object-fit: contain;"
                                    alt="No Image" 
                                    onerror="this.src='https://dummyimage.com/1440x1080/cccccc/000000&text=No+Image';">
                                    ${borrowBadge}
                            </div>
                            <div class="vr"></div>
                            <div class="right-side" style="width: 60%;">
                                <p><label for="${item.ssid}">${item.name} ${item.second_name}</label></p>
                                <p>
                                    <span class="badge bg-success" style="margin: 0.1rem">${item.belong_place || '未知'}</span>
                                    <span class="badge bg-info text-dark" style="margin: 0.1rem">${item.ssid}</span>
                                    <span class="badge bg-primary" style="margin: 0.1rem">${item.class}</span>
                                </p>
                                <p class="card-text">
                                    規格：${item.format || '無'}<br>
                                    ${item.remark ? `備註：${item.remark}` : ''}
                                </p>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;

        let desktop_card = `
            <div class="col">
                <div class="card border-${lending_color}">
                    <div class="card-header text-white bg-${lending_color}">${borrowSvg} ${lending_status}
                    
                    </div>
                    <img src="./storage/propertyImgs/${item.img_url}" style="width: 100%; aspect-ratio: 4 / 3; object-fit: contain;" class="card-img-top" alt="No Image" 
                         onerror="this.src='https://dummyimage.com/1440x1080/cccccc/000000&text=No+Image';">
                    <div class="card-body">
                        <label for="${item.ssid}">${item.name} ${item.second_name}</label>
                        <p>
                            <span class="badge bg-primary" style="margin: 0.1rem">${item.class}</span>
                            <span class="badge bg-info text-dark" style="margin: 0.1rem">${item.ssid}</span>
                            ${borrowBadge}
                        </p>
                        <p class="card-text">                            
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

        // 將生成的卡片添加到容器中
        if (window.innerWidth <= 768) {
            $('#property_status').append(mobile_card);
        }
        else {
            $('#property_status').append(desktop_card);
        }

    });
}


